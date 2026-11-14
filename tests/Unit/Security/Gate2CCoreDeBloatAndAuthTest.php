<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Magma\assets\AssetVersionManager;
use Magma\container\Container;
use Magma\contracts\ClockInterface;
use Magma\domain\AuthUser;
use Magma\dto\TenantDTO;
use Magma\error\DebugErrorPresenter;
use Magma\http\Request;
use Magma\interfaces\cqrs\TenantQueryInterface;
use Magma\routing\Route;
use Magma\security\TenantContext;
use Magma\security\TenantContextProviderInterface;
use Magma\services\AuthenticationResult;
use Magma\services\AuthenticationService;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Title: Gate 2C Core De-Bloating, Strict Types & Offline Diagnostics Unit Suite
 *
 * Purpose:
 * - Verifies `readonly class AuthenticationResult` immutability, `AuthenticationService` constructor
 *   collaborator bounds (`<= 4` non-clock collaborators), offline `DebugErrorPresenter` rendering
 *   (zero external CDNs, zero fabricated mock telemetry, secret redaction, CSS color whitelisting),
 *   `AssetVersionManager` deterministic hashing, and 100% `declare(strict_types=1);` + `readonly` DTO
 *   purity across `/magma/`.
 *
 * Why / Why this design:
 * - Locks down every structural invariant from Gate 2C deterministically so future edits cannot
 *   reintroduce missing `strict_types=1`, mutable DTOs, domain bindings in `magma/providers/`, or
 *   fabricated telemetry strings in `DebugErrorPresenter`.
 *
 * Teaching notes:
 * - Combining fast reflection/AST-free file scans with behavioral tests enforces architectural rules
 *   automatically inside `phpunit` alongside PHPStan Level 9.
 */
final class Gate2CCoreDeBloatAndAuthTest extends TestCase
{
    /**
     * Verifies `AuthenticationResult` is a native `readonly class` and returns new instances on cookie chaining.
     */
    public function testAuthenticationResultIsReadonlyAndImmutable(): void
    {
        $ref = new ReflectionClass(AuthenticationResult::class);
        $this->assertTrue($ref->isReadOnly());

        $user = new AuthUser(['id' => 7, 'email' => 'dev@magma.example', 'role' => 'admin', 'tenant_id' => 1]);
        $base = AuthenticationResult::success($user);
        $withCookie = $base->withCookie('remember_user', 'sel:val', 1800000000);
        $withClear = $withCookie->clearCookie('legacy_cookie');

        $this->assertNotSame($base, $withCookie);
        $this->assertNotSame($withCookie, $withClear);
        $this->assertSame([], $base->getCookiesToSet());
        $this->assertSame([], $base->getCookiesToClear());
        $this->assertCount(1, $withCookie->getCookiesToSet());
        $this->assertSame([], $withCookie->getCookiesToClear());
        $this->assertCount(1, $withClear->getCookiesToSet());
        $this->assertSame(['legacy_cookie'], $withClear->getCookiesToClear());
    }

    /**
     * Verifies `AuthenticationService` respects the `<= 4` non-clock constructor collaborator cap.
     */
    public function testAuthenticationServiceConstructorCollaboratorBound(): void
    {
        $ref = new ReflectionClass(AuthenticationService::class);
        $ctor = $ref->getConstructor();
        $this->assertNotNull($ctor);

        $nonClockCollaborators = 0;
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && $type->getName() !== ClockInterface::class) {
                $nonClockCollaborators++;
            }
        }

        $this->assertLessThanOrEqual(4, $nonClockCollaborators);
    }

    /**
     * Verifies `DebugErrorPresenter` renders offline HTML without external font CDNs or fabricated telemetry,
     * redacts secrets, and rejects CSS color injection payloads.
     */
    public function testDebugErrorPresenterOfflineTemplateRedactionAndCssSanitization(): void
    {
        $container = new Container();
        $tenantCtx = new TenantContext(new class implements TenantContextProviderInterface {
            public function resolveTenantId(\Magma\http\RequestInterface $request): ?int
            {
                return 42;
            }
            public function resolveVenueId(\Magma\http\RequestInterface $request): ?int
            {
                return null;
            }
            public function resolveDomainByTenantId(int $tenantId, ?string $currentHost = null): ?string
            {
                return null;
            }
        });
        $tenantCtx->setTenantId(42);

        $maliciousCss = '#112233; } body { ' . 'display:none';
        $secretQuery = 'super_secret_' . 'query_token';
        $secretPass = 'PlainText' . 'Secret123!';
        $secretCsrf = 'csrf_raw_' . '999';
        $secretJwt = 'jwt_secret_' . 'xyz';
        $secretCookie = 'sel:secret_' . 'cookie_val';
        $googleFontsHost = 'fonts.googleapis' . '.com';
        $gstaticHost = 'fonts.gstatic' . '.com';
        $mockProxy = 'HA' . 'Proxy';
        $mockHitRate = 'Hit Rate: ' . '98.5%';
        $mockCmd = 'AuthenticateUser' . 'Command';

        $maliciousTenant = new TenantDTO(
            id: 42,
            name: 'Acme <Labs>',
            tagline: null,
            email: 'ops@acme.example',
            plan_id: 2,
            subscription_status: 'active',
            billing_cycle_anchor: null,
            payment_gateway_customer_id: null,
            theme_settings: [
                'primary_color'   => $maliciousCss,
                'secondary_color' => '#cc8800',
            ]
        );

        $container->set(TenantContext::class, fn() => $tenantCtx);
        $container->set(TenantQueryInterface::class, fn() => new class ($maliciousTenant) implements TenantQueryInterface {
            public function __construct(private readonly TenantDTO $dto) {}
            public function getAll(int $limit = 100, ?int $lastId = null): iterable
            {
                return [$this->dto];
            }
            public function find(int $id): ?TenantDTO
            {
                return $id === $this->dto->id ? $this->dto : null;
            }
            public function getPrimaryTenant(): ?TenantDTO
            {
                return $this->dto;
            }
        });

        $presenter = new DebugErrorPresenter($container);
        $request = new Request(
            'POST',
            '/login?token=' . $secretQuery,
            ['token' => $secretQuery, 'page' => '2'],
            ['email' => 'alice@acme.example', 'password' => $secretPass, 'csrf_token' => $secretCsrf],
            ['REMOTE_ADDR' => '10.0.0.9', 'HTTP_USER_AGENT' => 'MagmaTest/1.0', 'HTTP_AUTHORIZATION' => 'Bearer ' . $secretJwt],
            ['remember_user' => $secretCookie]
        );

        $exceptionResponse = $presenter->present(self::buildSampleException(), $request, 500);
        $html = $exceptionResponse->getContent();

        $this->assertSame(500, $exceptionResponse->getStatusCode());
        $this->assertStringContainsString('Database constraint failed', $html);
        $this->assertStringContainsString('Acme &lt;Labs&gt;', $html);

        // Zero external Google Fonts or fabricated mock telemetry strings
        $this->assertStringNotContainsString($googleFontsHost, $html);
        $this->assertStringNotContainsString($gstaticHost, $html);
        $this->assertStringNotContainsString($mockProxy, $html);
        $this->assertStringNotContainsString($mockHitRate, $html);
        $this->assertStringNotContainsString($mockCmd, $html);

        // Secrets redacted; non-sensitive inputs preserved
        $this->assertStringNotContainsString($secretPass, $html);
        $this->assertStringNotContainsString($secretQuery, $html);
        $this->assertStringNotContainsString($secretCsrf, $html);
        $this->assertStringNotContainsString($secretJwt, $html);
        $this->assertStringNotContainsString($secretCookie, $html);
        $this->assertStringContainsString('[REDACTED]', $html);
        $this->assertStringContainsString('alice@acme.example', $html);

        // Malicious CSS injection rejected in favor of safe fallback while valid hex color is preserved
        $this->assertStringNotContainsString('display:' . 'none', $html);
        $this->assertStringContainsString('--color-primary: #622E00;', $html);
        $this->assertStringContainsString('--color-secondary: #cc8800;', $html);

        // 404 Route Explorer renders registered routes cleanly
        $notFound = $presenter->presentNotFound($request, [
            new Route('GET', '/health', 'App\\controllers\\HealthController@check', 'health.check', ['AuthMiddleware']),
        ]);
        $this->assertSame(404, $notFound->getStatusCode());
        $this->assertStringContainsString('/health', $notFound->getContent());
        $this->assertStringContainsString('HealthController@check', $notFound->getContent());
    }

    /**
     * Verifies `AssetVersionManager` content hashing and legacy `?v=...` query normalization.
     */
    public function testAssetVersionManagerContentHashingAndLegacyQueryNormalization(): void
    {
        $tmpDir = sys_get_temp_dir() . '/magma_asset_test_' . bin2hex(random_bytes(4));
        mkdir($tmpDir . '/css', 0777, true);
        $cssFile = $tmpDir . '/css/app.css';
        file_put_contents($cssFile, 'body { color: #111; }');

        try {
            $expectedHash = substr((string) md5_file($cssFile), 0, 8);
            $manager = new AssetVersionManager($tmpDir, '');

            $this->assertSame("/css/app.css?v={$expectedHash}", $manager->getAssetUrl('/css/app.css'));
            $this->assertSame("/css/app.css?v={$expectedHash}", $manager->getAssetUrl('/css/app.css?v=28'));
            $this->assertSame("/css/app.css?theme=dark&v={$expectedHash}", $manager->getAssetUrl('/css/app.css?theme=dark'));
            $this->assertSame($expectedHash, $manager->getVersion('/css/app.css?v=28'));
            $this->assertSame('/css/missing.css', $manager->getAssetUrl('/css/missing.css'));
        } finally {
            @unlink($cssFile);
            @rmdir($tmpDir . '/css');
            @rmdir($tmpDir);
        }
    }

    /**
     * Enforces 100% `declare(strict_types=1);`, 100% `readonly class` DTOs, and zero module/ghost pollution in `magma/`.
     */
    public function testMagmaCoreStrictTypesReadonlyDtosAndDomainPurity(): void
    {
        $magmaDir = dirname(__DIR__, 3) . '/magma';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($magmaDir));

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                continue;
            }
            $path = $fileInfo->getPathname();
            $contents = (string) file_get_contents($path);

            $this->assertStringContainsString(
                'declare(strict_types=1);',
                $contents,
                "Core file missing declare(strict_types=1): {$path}"
            );

            if (str_contains($path, '/magma/dto/')) {
                $this->assertMatchesRegularExpression(
                    '/\breadonly\s+class\s+\w+/',
                    $contents,
                    "Core DTO must be a native readonly class: {$path}"
                );
            }

            if (str_contains($path, '/magma/providers/')) {
                $this->assertDoesNotMatchRegularExpression(
                    '/use\s+Modules\\\\|new\s+\\\\?Modules\\\\|TenantAdminController|UserDashboardController/',
                    $contents,
                    "Core provider contains downstream module or ghost controller binding: {$path}"
                );
            }
        }
    }

    /**
     * Constructs a sample exception well outside assertion lines so source-snippet extraction does not echo test literals.
     */
    private static function buildSampleException(): RuntimeException
    {
        return new RuntimeException('Database constraint failed');
    }
}

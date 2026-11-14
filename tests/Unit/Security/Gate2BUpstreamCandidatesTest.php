<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use DateTimeImmutable;
use InvalidArgumentException;
use Magma\config\Config;
use Magma\container\Container;
use Magma\domain\AuthUser;
use Magma\dto\TenantDTO;
use Magma\http\Request;
use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\infrastructure\time\MockClock;
use Magma\logging\NativeLogger;
use Magma\middleware\TenantSecurityMiddleware;
use Magma\security\AuthTenantMode;
use Magma\security\TenantContext;
use Magma\security\TenantContextProviderInterface;
use Magma\services\AuthenticationResult;
use Magma\services\AuthenticationService;
use Magma\services\SmtpMailTransport;
use PHPUnit\Framework\TestCase;

/**
 * Title: Gate 2B Upstream Security & Multi-Tenant Candidates Unit Suite
 *
 * Purpose:
 * - Deterministically verifies `AuthTenantMode`, `Container` PHP 8.2+ enum auto-wiring safety, `TenantSecurityMiddleware` session rebinding vs strict logout, `TenantDTO` white-label metadata accessors, `Config::getBool()`, `NativeLogger` lazy `TenantContext` resolution, and `SmtpMailTransport` RFC 5321 protocol + header sanitization.
 *
 * Why / Why this design:
 * - Exercises real framework components (including a real loopback TCP socket server for `SmtpMailTransport` when `pcntl_fork` is available) without mock shortcuts.
 *
 * Teaching notes:
 * - Regression-locks the exact failure modes discovered during the cross-repository architectural audit (`Container::has()` on backed enums, landing-host `TenantContext` bleed in `GlobalGateway` mode, and SMTP CRLF header injection).
 */
final class Gate2BUpstreamCandidatesTest extends TestCase
{
    /**
     * Verifies `AuthTenantMode::fromString()` normalization, empty fallback, and invalid value rejection.
     */
    public function testAuthTenantModeFromStringValidation(): void
    {
        $this->assertSame(AuthTenantMode::TenantScoped, AuthTenantMode::fromString(''));
        $this->assertSame(AuthTenantMode::TenantScoped, AuthTenantMode::fromString('  TENANT_SCOPED '));
        $this->assertSame(AuthTenantMode::GlobalGateway, AuthTenantMode::fromString('global_gateway'));

        $this->expectException(InvalidArgumentException::class);
        AuthTenantMode::fromString('invalid_mode');
    }

    /**
     * Verifies `Container::has()` returns false for unbound PHP enums so default enum constructor parameters auto-wire cleanly.
     */
    public function testContainerDoesNotTreatUnboundEnumAsInstantiableClass(): void
    {
        $container = new Container();
        $this->assertFalse($container->has(AuthTenantMode::class));

        $consumer = $container->get(EnumDefaultConsumerFixture::class);
        $this->assertInstanceOf(EnumDefaultConsumerFixture::class, $consumer);
        $this->assertSame(AuthTenantMode::TenantScoped, $consumer->mode);
    }

    /**
     * Verifies `TenantSecurityMiddleware` rebinds `TenantContext` from landing-host tenant 1 to authenticated user tenant 7 in `GlobalGateway` mode.
     */
    public function testTenantSecurityMiddlewareRebindsAuthenticatedUserTenantInGlobalGatewayMode(): void
    {
        $tenantContext = new TenantContext();
        $provider = $this->createHostTenantProvider(1, null);
        $authService = $this->createStubAuthService(new AuthUser([
            'id' => 42,
            'name' => 'SaaS Customer',
            'email' => 'ceo@customer7.example',
            'role' => 'admin',
            'tenant_id' => 7,
        ]), $loggedOut);

        $middleware = new TenantSecurityMiddleware(
            $tenantContext,
            $authService,
            $provider,
            AuthTenantMode::GlobalGateway
        );

        $request = new Request('GET', '/console', [], [], ['HTTP_HOST' => 'portal.example.com']);
        $capturedAttribute = null;

        $response = $middleware->process($request, function (RequestInterface $req) use (&$capturedAttribute): Response {
            $capturedAttribute = $req->getAttribute('tenant_id');
            return new Response('ok', 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($loggedOut);
        $this->assertSame(7, $tenantContext->getTenantId());
        $this->assertSame(7, $capturedAttribute);
    }

    /**
     * Verifies `TenantSecurityMiddleware` destroys mismatched sessions in `TenantScoped` mode when no SSO target domain exists.
     */
    public function testTenantSecurityMiddlewareLogsOutMismatchedTenantSessionWhenNoSsoDomainExists(): void
    {
        $tenantContext = new TenantContext();
        $provider = $this->createHostTenantProvider(1, null);
        $authService = $this->createStubAuthService(new AuthUser([
            'id' => 99,
            'name' => 'Wrong Domain User',
            'email' => 'user@tenant2.example',
            'role' => 'user',
            'tenant_id' => 2,
        ]), $loggedOut);

        $middleware = new TenantSecurityMiddleware(
            $tenantContext,
            $authService,
            $provider,
            AuthTenantMode::TenantScoped
        );

        $request = new Request('GET', '/dashboard', [], [], ['HTTP_HOST' => 'tenant1.example.com']);
        $middleware->process($request, fn(RequestInterface $req): Response => new Response('ok', 200));

        $this->assertTrue($loggedOut);
        $this->assertSame(1, $tenantContext->getTenantId());
    }

    /**
     * Verifies `TenantDTO` readonly white-label metadata helpers across flat and dot-notated JSONB keys.
     */
    public function testTenantDtoWhiteLabelMetadataHelpers(): void
    {
        $dto = new TenantDTO(
            id: 2,
            name: 'Artisanal Bakery',
            tagline: 'Fresh daily',
            email: 'hello@bakery.example',
            plan_id: 3,
            subscription_status: 'active',
            billing_cycle_anchor: null,
            payment_gateway_customer_id: null,
            theme_settings: [
                'orders_email' => '  orders@bakery.example  ',
                'blank_value' => '   ',
                'show_social' => 'true',
                'footer' => [
                    'description' => 'Handcrafted in small batches.',
                    'links' => ['Menu', 'Policies'],
                ],
            ]
        );

        $this->assertTrue($dto->hasSetting('orders_email'));
        $this->assertTrue($dto->hasSetting('footer.description'));
        $this->assertFalse($dto->hasSetting('footer.missing'));

        $this->assertSame('orders@bakery.example', $dto->setting('orders_email', 'fallback@example.com'));
        $this->assertSame('fallback@example.com', $dto->setting('blank_value', 'fallback@example.com'));
        $this->assertSame('Handcrafted in small batches.', $dto->setting('footer.description'));
        $this->assertTrue($dto->settingBool('show_social', false));
        $this->assertSame(['Menu', 'Policies'], $dto->settingArray('footer.links'));
        $this->assertSame([], $dto->settingArray('orders_email'));
    }

    /**
     * Verifies `Config::getBool()` parses standard environment truthy/falsy representations accurately.
     */
    public function testConfigGetBoolParsing(): void
    {
        $_ENV['MAGMA_TEST_BOOL_TRUE'] = 'yes';
        $_ENV['MAGMA_TEST_BOOL_FALSE'] = 'off';

        try {
            $this->assertTrue(Config::getBool('MAGMA_TEST_BOOL_TRUE', false));
            $this->assertFalse(Config::getBool('MAGMA_TEST_BOOL_FALSE', true));
            $this->assertTrue(Config::getBool('MAGMA_TEST_BOOL_UNSET_KEY', true));
        } finally {
            unset($_ENV['MAGMA_TEST_BOOL_TRUE'], $_ENV['MAGMA_TEST_BOOL_FALSE']);
        }
    }

    /**
     * Verifies `NativeLogger` invokes a lazy `TenantContext` callable only during `log()` rather than construction.
     */
    public function testNativeLoggerResolvesTenantContextCallableLazily(): void
    {
        $invocations = 0;
        $context = new TenantContext();

        $logger = new NativeLogger(function () use (&$invocations, $context): TenantContext {
            $invocations++;
            return $context;
        });

        $this->assertSame(0, $invocations);

        $tmpLog = tempnam(sys_get_temp_dir(), 'magma_log_');
        $this->assertIsString($tmpLog);
        $prevErrorLog = ini_set('error_log', $tmpLog);

        try {
            $context->setTenantId(314);
            $logger->info("Order\r\nCreated", ['order_id' => 88]);
        } finally {
            if ($prevErrorLog !== false) {
                ini_set('error_log', $prevErrorLog);
            }
        }

        $contents = (string) file_get_contents($tmpLog);
        @unlink($tmpLog);

        $this->assertSame(1, $invocations);
        $this->assertStringContainsString('[Tenant: 314]', $contents);
        $this->assertStringContainsString('Order  Created', $contents);
    }

    /**
     * Verifies `SmtpMailTransport` executes a full RFC 5321 session over a real loopback socket, strips CRLF header injections, dot-stuffs leading periods, and stamps `MockClock` RFC 2822 dates.
     */
    public function testSmtpMailTransportLoopbackHandshakeHeaderSanitizationAndDotStuffing(): void
    {
        if (!function_exists('pcntl_fork')) {
            $transport = new SmtpMailTransport(['host' => '', 'from_email' => '']);
            $this->assertFalse($transport->send('user@example.com', 'Subject', 'Body'));
            return;
        }

        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertIsResource($server, "Failed to bind loopback test socket: {$errstr}");

        $socketName = stream_socket_get_name($server, false);
        $this->assertIsString($socketName);
        $parts = explode(':', $socketName);
        $port = (int) end($parts);

        $captureFile = tempnam(sys_get_temp_dir(), 'magma_smtp_');
        $this->assertIsString($captureFile);

        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid, 'pcntl_fork failed');

        if ($pid === 0) {
            $conn = @stream_socket_accept($server, 5);
            $transcript = '';
            if (is_resource($conn)) {
                fwrite($conn, "220 localhost ESMTP MagmaTest\r\n");
                while (($line = fgets($conn, 1024)) !== false) {
                    $transcript .= $line;
                    if (str_starts_with($line, 'EHLO ')) {
                        fwrite($conn, "250-localhost\r\n250 AUTH LOGIN\r\n");
                    } elseif (str_starts_with($line, 'AUTH LOGIN')) {
                        fwrite($conn, "334 VXNlcm5hbWU6\r\n");
                        $transcript .= (string) fgets($conn, 1024);
                        fwrite($conn, "334 UGFzc3dvcmQ6\r\n");
                        $transcript .= (string) fgets($conn, 1024);
                        fwrite($conn, "235 2.7.0 Authentication successful\r\n");
                    } elseif (str_starts_with($line, 'MAIL FROM:') || str_starts_with($line, 'RCPT TO:')) {
                        fwrite($conn, "250 2.1.0 OK\r\n");
                    } elseif (str_starts_with($line, 'DATA')) {
                        fwrite($conn, "354 End data with <CR><LF>.<CR><LF>\r\n");
                        while (($dataLine = fgets($conn, 1024)) !== false) {
                            $transcript .= $dataLine;
                            if ($dataLine === ".\r\n") {
                                break;
                            }
                        }
                        fwrite($conn, "250 2.0.0 Queued\r\n");
                    } elseif (str_starts_with($line, 'QUIT')) {
                        fwrite($conn, "221 2.0.0 Bye\r\n");
                        break;
                    }
                }
                fclose($conn);
            }
            fclose($server);
            file_put_contents($captureFile, $transcript);
            exit(0);
        }

        fclose($server);

        $clock = new MockClock(new DateTimeImmutable('2026-11-14T18:30:00+00:00'));
        $transport = new SmtpMailTransport([
            'host'        => '127.0.0.1',
            'port'        => $port,
            'username'    => 'mailer_user',
            'password'    => 'mailer_secret',
            'encryption'  => 'none',
            'from_email'  => 'noreply@magma.test',
            'from_name'   => 'Magma Core',
            'timeout'     => 5,
            'verify_tls'  => true,
        ], $clock);

        $sent = $transport->send(
            "victim@example.test\r\nBcc: attacker@evil.test",
            "Invoice Ready\r\nX-Injected: pwned",
            "Line 1\n.HiddenDotLine\nLine 3"
        );

        pcntl_waitpid($pid, $status);
        $rawTranscript = (string) file_get_contents($captureFile);
        @unlink($captureFile);

        $this->assertTrue($sent);
        $this->assertStringContainsString('X-Mailer: Magma SMTP Transport', $rawTranscript);
        $this->assertStringContainsString('Date: Sat, 14 Nov 2026 18:30:00 +0000', $rawTranscript);
        $this->assertStringNotContainsString("\r\nX-Injected:", $rawTranscript);
        $this->assertStringNotContainsString("\r\nBcc:", $rawTranscript);
        $this->assertStringContainsString("\r\n..HiddenDotLine\r\n", $rawTranscript);
    }

    /**
     * Builds a deterministic host `TenantContextProviderInterface` test double.
     */
    private function createHostTenantProvider(?int $hostTenantId, ?string $ssoDomain): TenantContextProviderInterface
    {
        return new class ($hostTenantId, $ssoDomain) implements TenantContextProviderInterface {
            public function __construct(
                private readonly ?int $hostTenantId,
                private readonly ?string $ssoDomain
            ) {}

            public function resolveTenantId(RequestInterface $request): ?int
            {
                return $this->hostTenantId;
            }

            public function resolveVenueId(RequestInterface $request): ?int
            {
                return null;
            }

            public function resolveDomainByTenantId(int $tenantId, ?string $currentHost = null): ?string
            {
                return $this->ssoDomain;
            }
        };
    }

    /**
     * Builds a lightweight `AuthenticationService` subclass tracking `logout()` calls without external mocking magic.
     */
    private function createStubAuthService(AuthUser $user, ?bool &$loggedOut): AuthenticationService
    {
        $loggedOut = false;

        return new class ($user, $loggedOut) extends AuthenticationService {
            private bool $isLoggedOut = false;

            public function __construct(
                private readonly AuthUser $stubUser,
                private bool &$logoutFlag
            ) {}

            public function getAuthenticatedUser(): ?AuthUser
            {
                return $this->isLoggedOut ? null : $this->stubUser;
            }

            public function logout(?string $rememberToken = null): AuthenticationResult
            {
                $this->isLoggedOut = true;
                $this->logoutFlag = true;
                return AuthenticationResult::failure();
            }
        };
    }
}

/**
 * Title: Enum Default Consumer Test Fixture
 *
 * Purpose:
 * - Minimal auto-wirable class verifying `Container::resolve()` falls back to a default backed `enum` parameter when the enum is unbound.
 *
 * Why / Why this design:
 * - Isolates `Container` reflection auto-wiring from optional service graphs.
 *
 * Teaching notes:
 * - Used exclusively by `Gate2BUpstreamCandidatesTest`.
 */
final readonly class EnumDefaultConsumerFixture
{
    public function __construct(
        public TenantContext $tenantContext,
        public AuthTenantMode $mode = AuthTenantMode::TenantScoped
    ) {}
}

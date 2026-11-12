<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use DateTimeImmutable;
use Magma\config\ConfigInterface;
use Magma\contracts\ClockInterface;
use Magma\contracts\RolePolicyInterface;
use Magma\domain\AuthUser;
use Magma\http\RedirectResponse;
use Magma\http\Request;
use Magma\http\Response;
use Magma\http\SessionInterface;
use Magma\middleware\AuthMiddleware;
use Magma\middleware\RoleMiddleware;
use Magma\middleware\SecurityHeadersMiddleware;
use Magma\middleware\SessionTimeoutMiddleware;
use Magma\security\ConfigRolePolicy;
use Magma\security\PostLoginRedirectResolver;
use Magma\services\AuthenticationService;
use PHPUnit\Framework\TestCase;

/**
 * Title: Role Policy and Authentication Security Middleware Unit Test Suite
 *
 * Purpose:
 * - Comprehensively tests `ConfigRolePolicy`, `PostLoginRedirectResolver`,
 *   `SessionTimeoutMiddleware`, `RoleMiddleware`, and `SecurityHeadersMiddleware`.
 *
 * Why / Why this design:
 * - Isolation: Uses pure in-memory test doubles for `SessionInterface`, `ClockInterface`, and `ConfigInterface`
 *   to ensure deterministic, millisecond-fast test execution without filesystem or database I/O.
 *
 * Teaching notes:
 * - In Magma, the `lava` branch provides the downstream testing harness for testing core framework contracts
 *   before upstream release.
 */
class RolePolicyAndAuthMiddlewareTest extends TestCase
{
    // =========================================================================
    // 1. ConfigRolePolicy Tests
    // =========================================================================

    public function test_config_role_policy_resolves_default_landing_path_when_unmapped(): void
    {
        $config = $this->createMockConfig([
            'AUTH_DEFAULT_REDIRECT' => '/home',
        ]);
        $policy = new ConfigRolePolicy($config);

        $this->assertSame('/home', $policy->resolveDashboardPath(null));
        $this->assertSame('/home', $policy->resolveDashboardPath(''));
        $this->assertSame('/home', $policy->resolveDashboardPath('unmapped_role'));
    }

    public function test_config_role_policy_resolves_array_and_string_role_dashboard_map(): void
    {
        // 1. Array map
        $configArray = $this->createMockConfig([
            'ROLE_DASHBOARD_MAP' => [
                'admin' => '/admin/dashboard',
                'staff' => '/staff/portal',
            ],
            'AUTH_DEFAULT_REDIRECT' => '/',
        ]);
        $policyArray = new ConfigRolePolicy($configArray);

        $this->assertSame('/admin/dashboard', $policyArray->resolveDashboardPath('admin'));
        $this->assertSame('/staff/portal', $policyArray->resolveDashboardPath('staff'));
        $this->assertSame('/', $policyArray->resolveDashboardPath('user'));

        // 2. Comma-delimited string map
        $configString = $this->createMockConfig([
            'ROLE_DASHBOARD_MAP' => 'admin:/admin/dashboard, staff:/staff/portal',
            'AUTH_DEFAULT_REDIRECT' => '/',
        ]);
        $policyString = new ConfigRolePolicy($configString);

        $this->assertSame('/admin/dashboard', $policyString->resolveDashboardPath('admin'));
        $this->assertSame('/staff/portal', $policyString->resolveDashboardPath('staff'));
    }

    public function test_config_role_policy_sanitizes_mapped_paths_and_rejects_open_redirects(): void
    {
        $config = $this->createMockConfig([
            'ROLE_DASHBOARD_MAP' => [
                'attacker' => 'https://evil.com/hack',
                'protocol_rel' => '//evil.com',
                'login_loop' => '/login',
            ],
            'AUTH_DEFAULT_REDIRECT' => '/fallback',
        ]);
        $policy = new ConfigRolePolicy($config);

        // All invalid or external targets must be rejected by RedirectSanitizer and fall back
        $this->assertSame('/fallback', $policy->resolveDashboardPath('attacker'));
        $this->assertSame('/fallback', $policy->resolveDashboardPath('protocol_rel'));
        $this->assertSame('/fallback', $policy->resolveDashboardPath('login_loop'));
    }

    public function test_config_role_policy_resolves_session_timeout_with_array_string_and_floor(): void
    {
        // Array map with custom timeouts
        $config = $this->createMockConfig([
            'ROLE_TIMEOUT_MAP' => [
                'admin' => 7200,
                'staff' => '3600',
                'broken_zero' => 0,
                'broken_negative' => -50,
            ],
            'SESSION_LIFETIME' => 1800,
        ]);
        $policy = new ConfigRolePolicy($config);

        $this->assertSame(7200, $policy->resolveSessionTimeout('admin'));
        $this->assertSame(3600, $policy->resolveSessionTimeout('staff'));
        // Broken values must fall back to SESSION_LIFETIME (1800)
        $this->assertSame(1800, $policy->resolveSessionTimeout('broken_zero'));
        $this->assertSame(1800, $policy->resolveSessionTimeout('broken_negative'));
        $this->assertSame(1800, $policy->resolveSessionTimeout('unmapped'));

        // Comma-delimited string map
        $configString = $this->createMockConfig([
            'ROLE_TIMEOUT_MAP' => 'admin:7200, staff:3600',
            'SESSION_LIFETIME' => 900,
        ]);
        $policyString = new ConfigRolePolicy($configString);
        $this->assertSame(7200, $policyString->resolveSessionTimeout('admin'));
        $this->assertSame(3600, $policyString->resolveSessionTimeout('staff'));
        $this->assertSame(900, $policyString->resolveSessionTimeout('guest'));

        // Enforces minimum floor of 60 seconds
        $configFloor = $this->createMockConfig([
            'SESSION_LIFETIME' => 20,
        ]);
        $policyFloor = new ConfigRolePolicy($configFloor);
        $this->assertSame(60, $policyFloor->resolveSessionTimeout(null));
    }

    // =========================================================================
    // 2. PostLoginRedirectResolver Tests
    // =========================================================================

    public function test_post_login_resolver_prioritizes_post_redirect_over_query_and_session(): void
    {
        $session = new InMemorySession();
        $session->set(AuthMiddleware::INTENDED_URL_SESSION_KEY, '/session/intended');

        $rolePolicy = $this->createMock(RolePolicyInterface::class);
        $resolver = new PostLoginRedirectResolver($session, $rolePolicy);

        $request = new Request('POST', '/login?redirect=/query/target', get: ['redirect' => '/query/target'], post: ['redirect' => '/post/target']);

        $destination = $resolver->resolveDestination(null, $request);
        $this->assertSame('/post/target', $destination);

        // Session intended URL must have been consumed
        $this->assertFalse($session->has(AuthMiddleware::INTENDED_URL_SESSION_KEY));
    }

    public function test_post_login_resolver_prioritizes_query_over_session_and_consumes_session(): void
    {
        $session = new InMemorySession();
        $session->set(AuthMiddleware::INTENDED_URL_SESSION_KEY, '/session/intended');

        $rolePolicy = $this->createMock(RolePolicyInterface::class);
        $resolver = new PostLoginRedirectResolver($session, $rolePolicy);

        $request = new Request('POST', '/login?return_to=/query/return', get: ['return_to' => '/query/return']);

        $destination = $resolver->resolveDestination(null, $request);
        $this->assertSame('/query/return', $destination);
        $this->assertFalse($session->has(AuthMiddleware::INTENDED_URL_SESSION_KEY));
    }

    public function test_post_login_resolver_uses_and_consumes_session_intended_url_when_no_params(): void
    {
        $session = new InMemorySession();
        $session->set(AuthMiddleware::INTENDED_URL_SESSION_KEY, '/deep/protected/resource');

        $rolePolicy = $this->createMock(RolePolicyInterface::class);
        $resolver = new PostLoginRedirectResolver($session, $rolePolicy);

        $request = new Request('POST', '/login');

        $destination = $resolver->resolveDestination(null, $request);
        $this->assertSame('/deep/protected/resource', $destination);
        $this->assertFalse($session->has(AuthMiddleware::INTENDED_URL_SESSION_KEY));
    }

    public function test_post_login_resolver_neutralizes_open_redirects_and_falls_back_to_role_policy(): void
    {
        $session = new InMemorySession();
        $rolePolicy = $this->createMock(RolePolicyInterface::class);
        $rolePolicy->expects($this->once())
            ->method('resolveDashboardPath')
            ->with('staff')
            ->willReturn('/staff/portal');

        $resolver = new PostLoginRedirectResolver($session, $rolePolicy);

        // Malicious candidate: protocol-relative open-redirect
        $request = new Request('POST', '/login', post: ['redirect' => '//evil.com/phish']);
        $user = new AuthUser(['id' => 1, 'email' => 'staff@magma.test', 'role' => 'staff']);

        $destination = $resolver->resolveDestination($user, $request);
        $this->assertSame('/staff/portal', $destination);
    }

    public function test_post_login_resolver_failed_login_flashes_old_and_errors_preserving_intended_url(): void
    {
        $session = new InMemorySession();
        $session->set(AuthMiddleware::INTENDED_URL_SESSION_KEY, '/saved/deep/link');

        $rolePolicy = $this->createMock(RolePolicyInterface::class);
        $resolver = new PostLoginRedirectResolver($session, $rolePolicy);

        $response = $resolver->redirectAfterFailedLogin('john@example.com', 'Invalid credentials');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaders()['Location'] ?? null);

        // Old email and error message must be flashed
        $this->assertSame(['email' => 'john@example.com'], $session->get('old'));
        $this->assertSame(['auth' => 'Invalid credentials'], $session->get('errors'));

        // Crucial invariant: intended_url must remain preserved across failed login attempts!
        $this->assertSame('/saved/deep/link', $session->get(AuthMiddleware::INTENDED_URL_SESSION_KEY));
    }

    // =========================================================================
    // 3. SessionTimeoutMiddleware Tests
    // =========================================================================

    public function test_session_timeout_middleware_destroys_session_and_clears_cookie_when_expired(): void
    {
        $session = new InMemorySession();
        $session->set('user', ['id' => 42, 'role' => 'admin']);
        $session->set('last_activity', 1000);

        $now = new DateTimeImmutable('@8500'); // 7500 seconds later (> 7200s timeout)
        $clock = new TestClock($now);

        $rolePolicy = $this->createMock(RolePolicyInterface::class);
        $rolePolicy->method('resolveSessionTimeout')->with('admin')->willReturn(7200);

        $middleware = new SessionTimeoutMiddleware($session, $clock, $rolePolicy);

        $request = new Request('GET', '/admin/dashboard');
        $next = function () {
            $this->fail('Downstream pipeline must not be invoked when session has expired.');
        };

        $response = $middleware->process($request, $next);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login', $response->getHeaders()['Location'] ?? null);
        $this->assertTrue($session->isDestroyed());

        // Cookie remember_user must be expired
        $ref = new \ReflectionProperty(Response::class, 'cookies');
        /** @var array<int, array{name: string, value: string, expires: int}> $cookies */
        $cookies = $ref->getValue($response);
        $cookieMap = [];
        foreach ($cookies as $c) {
            $cookieMap[$c['name']] = $c;
        }
        $this->assertArrayHasKey('remember_user', $cookieMap);
        $this->assertSame('', $cookieMap['remember_user']['value']);
        $this->assertLessThan($now->getTimestamp(), $cookieMap['remember_user']['expires']);
    }

    public function test_session_timeout_middleware_returns_401_json_when_json_expected(): void
    {
        $session = new InMemorySession();
        $session->set('user', ['id' => 42, 'role' => 'user']);
        $session->set('last_activity', 1000);

        $now = new DateTimeImmutable('@3000'); // 2000s later (> 1800s timeout)
        $clock = new TestClock($now);

        $rolePolicy = $this->createMock(RolePolicyInterface::class);
        $rolePolicy->method('resolveSessionTimeout')->with('user')->willReturn(1800);

        $middleware = new SessionTimeoutMiddleware($session, $clock, $rolePolicy);

        $request = new Request('GET', '/api/user/profile', server: ['HTTP_ACCEPT' => 'application/json']);
        $next = fn() => new Response('OK');

        $response = $middleware->process($request, $next);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('application/json', (string) ($response->getHeaders()['Content-Type'] ?? ''));
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertSame(401, $data['code']);
    }

    public function test_session_timeout_middleware_refreshes_last_activity_when_active(): void
    {
        $session = new InMemorySession();
        $session->set('user', ['id' => 10, 'role' => 'user']);
        $session->set('last_activity', 1000);

        $now = new DateTimeImmutable('@1200'); // 200 seconds later (well within 1800s timeout)
        $clock = new TestClock($now);

        $rolePolicy = $this->createMock(RolePolicyInterface::class);
        $rolePolicy->method('resolveSessionTimeout')->with('user')->willReturn(1800);

        $middleware = new SessionTimeoutMiddleware($session, $clock, $rolePolicy);

        $request = new Request('GET', '/user/dashboard');
        $passedToNext = false;
        $next = function () use (&$passedToNext) {
            $passedToNext = true;
            return new Response('Dashboard Content');
        };

        $response = $middleware->process($request, $next);

        $this->assertTrue($passedToNext);
        $this->assertSame('Dashboard Content', $response->getContent());
        $this->assertSame(1200, $session->get('last_activity'));
    }

    // =========================================================================
    // 4. RoleMiddleware Tests
    // =========================================================================

    public function test_role_middleware_captures_intended_url_on_unauthenticated_get_to_login(): void
    {
        $session = new InMemorySession();
        $authService = $this->createMock(AuthenticationService::class);
        $authService->method('getAuthenticatedUser')->willReturn(null);

        $middleware = new RoleMiddleware($authService, $session);
        $middleware->configure(['admin'], '/login');

        $request = new Request('GET', '/admin/orders?page=2');
        $next = fn() => new Response('OK');

        $response = $middleware->process($request, $next);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login', $response->getHeaders()['Location'] ?? null);
        $this->assertSame('/admin/orders?page=2', $session->get(AuthMiddleware::INTENDED_URL_SESSION_KEY));
    }

    public function test_role_middleware_returns_401_json_when_unauthenticated_and_json_expected(): void
    {
        $session = new InMemorySession();
        $authService = $this->createMock(AuthenticationService::class);
        $authService->method('getAuthenticatedUser')->willReturn(null);

        $middleware = new RoleMiddleware($authService, $session);
        $middleware->configure(['admin'], '/login');

        $request = new Request('GET', '/api/admin/metrics', server: ['HTTP_ACCEPT' => 'application/json']);
        $next = fn() => new Response('OK');

        $response = $middleware->process($request, $next);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame(401, $data['code']);
        $this->assertFalse($data['success']);
    }

    public function test_role_middleware_returns_403_json_when_role_insufficient(): void
    {
        $session = new InMemorySession();
        $user = new AuthUser(['id' => 1, 'email' => 'user@magma.test', 'role' => 'user']);

        $authService = $this->createMock(AuthenticationService::class);
        $authService->method('getAuthenticatedUser')->willReturn($user);

        $middleware = new RoleMiddleware($authService, $session);
        $middleware->configure(['admin', 'superadmin'], '/unauthorized');

        $request = new Request('GET', '/api/admin/config', server: ['HTTP_ACCEPT' => 'application/json']);
        $next = fn() => new Response('OK');

        $response = $middleware->process($request, $next);

        $this->assertSame(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame(403, $data['code']);
        $this->assertStringContainsString('Forbidden', $data['error']);
    }

    public function test_role_middleware_redirects_without_overwriting_intended_url_when_authenticated_user_lacks_role(): void
    {
        $session = new InMemorySession();
        $session->set(AuthMiddleware::INTENDED_URL_SESSION_KEY, '/prior/deep/link');

        $user = new AuthUser(['id' => 1, 'email' => 'user@magma.test', 'role' => 'user']);
        $authService = $this->createMock(AuthenticationService::class);
        $authService->method('getAuthenticatedUser')->willReturn($user);

        $middleware = new RoleMiddleware($authService, $session);
        $middleware->configure(['admin'], '/login');

        $request = new Request('GET', '/admin/secret');
        $next = fn() => new Response('OK');

        $response = $middleware->process($request, $next);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login', $response->getHeaders()['Location'] ?? null);

        // Must NOT overwrite intended_url for authenticated user lacking role (prevents redirect loop)
        $this->assertSame('/prior/deep/link', $session->get(AuthMiddleware::INTENDED_URL_SESSION_KEY));
    }

    public function test_role_middleware_allows_authorized_user_to_proceed(): void
    {
        $session = new InMemorySession();
        $user = new AuthUser(['id' => 1, 'email' => 'admin@magma.test', 'role' => 'admin']);
        $authService = $this->createMock(AuthenticationService::class);
        $authService->method('getAuthenticatedUser')->willReturn($user);

        $middleware = new RoleMiddleware($authService, $session);
        $middleware->configure(['admin', 'staff'], '/');

        $request = new Request('GET', '/admin/dashboard');
        $passed = false;
        $next = function () use (&$passed) {
            $passed = true;
            return new Response('Admin Access Granted');
        };

        $response = $middleware->process($request, $next);

        $this->assertTrue($passed);
        $this->assertSame('Admin Access Granted', $response->getContent());
    }

    // =========================================================================
    // 5. SecurityHeadersMiddleware Tests (RFC 6797 HSTS)
    // =========================================================================

    public function test_security_headers_middleware_omits_hsts_on_plain_http(): void
    {
        $middleware = new SecurityHeadersMiddleware();
        $request = new Request('GET', 'http://magma.test/home', server: ['HTTPS' => 'off']);
        $next = fn() => new Response('Plain HTTP Response');

        $response = $middleware->process($request, $next);
        $headers = $response->getHeaders();
        $this->assertSame('Plain HTTP Response', $response->getContent());
        $this->assertSame('DENY', $headers['X-Frame-Options'] ?? null);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options'] ?? null);
        $this->assertArrayHasKey('Content-Security-Policy', $headers);

        // RFC 6797 §7.2: HSTS must NOT be emitted on plain HTTP connections!
        $this->assertArrayNotHasKey('Strict-Transport-Security', $headers);
    }

    public function test_security_headers_middleware_includes_hsts_on_https(): void
    {
        $middleware = new SecurityHeadersMiddleware();
        $request = new Request('GET', 'https://magma.test/home', server: ['HTTPS' => 'on']);
        $next = fn() => new Response('HTTPS Response');

        $response = $middleware->process($request, $next);

        $headers = $response->getHeaders();
        $this->assertSame('HTTPS Response', $response->getContent());
        $this->assertSame('DENY', $headers['X-Frame-Options'] ?? null);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options'] ?? null);

        // On secure connections, Strict-Transport-Security must be emitted
        $this->assertSame('max-age=31536000; includeSubDomains', $headers['Strict-Transport-Security'] ?? null);
    }

    // =========================================================================
    // Helper Factories and Doubles
    // =========================================================================

    /**
     * @param array<string, mixed> $settings
     */
    private function createMockConfig(array $settings): ConfigInterface
    {
        return new class($settings) implements ConfigInterface {
            /** @param array<string, mixed> $settings */
            public function __construct(private readonly array $settings) {}

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->settings[$key] ?? $default;
            }

            public function getRequired(string $key): string
            {
                $val = $this->settings[$key] ?? null;
                if (!is_scalar($val) || (string) $val === '') {
                    throw new \RuntimeException("Missing config key: {$key}");
                }
                return (string) $val;
            }

            public function getDatabaseSettings(): array
            {
                return ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => '5432', 'dbname' => 'test', 'user' => 'u', 'password' => 'p'];
            }

            public function getReplicaDatabaseSettings(): array
            {
                return [];
            }

            public function getMailerSettings(): array
            {
                return ['host' => 'localhost', 'port' => 25, 'username' => '', 'password' => '', 'encryption' => '', 'from_email' => 'test@test.com', 'from_name' => 'Test'];
            }
        };
    }
}

/**
 * In-memory Session test double for isolated unit testing.
 */
class InMemorySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];
    private bool $destroyed = false;

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function flash(string $key, mixed $default = null): mixed
    {
        $val = $this->get($key, $default);
        $this->remove($key);
        return $val;
    }

    public function regenerate(bool $deleteOldSession = true): bool
    {
        return true;
    }

    public function save(): void
    {
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->destroyed = true;
    }

    public function isDestroyed(): bool
    {
        return $this->destroyed;
    }
}

/**
 * Deterministic Clock test double.
 */
class TestClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $currentTime) {}

    public function now(): DateTimeImmutable
    {
        return $this->currentTime;
    }

    public function setTime(DateTimeImmutable $time): void
    {
        $this->currentTime = $time;
    }
}

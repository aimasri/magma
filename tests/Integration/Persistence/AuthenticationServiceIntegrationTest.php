<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Magma\database\DatabaseTransactionManager;
use Magma\http\Session;
use Magma\infrastructure\time\MockClock;
use Magma\repositories\RememberTokenRepository;
use Magma\repositories\UserCommandRepository;
use Magma\repositories\UserQueryRepository;
use Magma\security\AuthTenantMode;
use Magma\services\AuthenticationService;
use Magma\services\RememberMeService;

/**
 * Title: Cohesive AuthenticationService & RememberMeService PostgreSQL Integration Suite
 *
 * Purpose:
 * - Verifies end-to-end SQL, Argon2id verification, transparent bcrypt-to-Argon2id rehashing,
 *   atomic `consumeAndRotate()` single-use token rotation, SSO token TTLs, and logout cleanup
 *   against live PostgreSQL tables without mocks.
 *
 * Why / Why this design:
 * - Uses `DatabaseIntegrationTestCase` rolled-back transactions with `pg_temp.users` and
 *   `pg_temp.user_tokens` so real SQL queries, savepoint transactions, and Argon2id hashes execute
 *   deterministically.
 *
 * Teaching notes:
 * - Replaying a consumed `remember_user` selector/validator token must fail immediately and emit a
 *   `clearCookie('remember_user')` instruction.
 */
final class AuthenticationServiceIntegrationTest extends DatabaseIntegrationTestCase
{
    private AuthenticationService $authService;
    /** @var array<string, mixed> */
    private array $sessionBacking = [];
    private int $argonUserId = 0;
    private int $bcryptUserId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbManager->forceWriteForReads(true);
        $db = $this->dbManager->getWriteConnection();

        $db->exec("
            CREATE TEMP TABLE pg_temp.users (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                role VARCHAR(64) NOT NULL DEFAULT 'user',
                password VARCHAR(255) NOT NULL,
                tenant_id INT NULL,
                password_changed_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
        ");

        $db->exec("
            CREATE TEMP TABLE pg_temp.user_tokens (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL,
                type VARCHAR(64) NOT NULL,
                selector VARCHAR(64) NOT NULL UNIQUE,
                token_hash VARCHAR(128) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP NOT NULL
            )
        ");

        $argonHash = password_hash('CorrectHorseBatteryStaple!', PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 1,
        ]);
        $legacyBcryptHash = password_hash('LegacyBcryptSecret!', PASSWORD_BCRYPT, ['cost' => 4]);

        $stmt = $db->prepare("
            INSERT INTO pg_temp.users (name, email, role, password, tenant_id)
            VALUES (?, ?, ?, ?, ?)
            RETURNING id
        ");

        $stmt->execute(['Argon User', 'argon@tenant101.example', 'admin', $argonHash, 101]);
        $this->argonUserId = (int) $stmt->fetchColumn();

        $stmt->execute(['Legacy User', 'legacy@tenant101.example', 'user', $legacyBcryptHash, 101]);
        $this->bcryptUserId = (int) $stmt->fetchColumn();

        $this->actAsTenant(101);
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-14 12:00:00', new DateTimeZone('UTC')));
        $this->sessionBacking = [];

        $userQuery = new UserQueryRepository($this->dbManager, $this->tenantContext, AuthTenantMode::TenantScoped);
        $userCommand = new UserCommandRepository($this->dbManager, $this->tenantContext, $this->clock);
        $tokenRepo = new RememberTokenRepository($this->dbManager, $this->tenantContext, $this->clock);
        $rememberMe = new RememberMeService($tokenRepo, $this->clock, $this->transactionManager);
        $session = new Session(null, $this->sessionBacking);

        $this->authService = new AuthenticationService(
            $userQuery,
            $userCommand,
            $rememberMe,
            $session,
            $this->clock
        );
    }

    /**
     * Verifies credential authentication with remember-me persistence, atomic auto-login rotation,
     * replay rejection, and full logout token cleanup.
     */
    public function testCredentialLoginAutoLoginRotationAndLogoutLifecycle(): void
    {
        // 1. Unknown email and wrong password both fail cleanly without populating session
        $this->assertFalse($this->authService->attempt('nobody@tenant101.example', 'wrong')->isSuccessful());
        $this->assertFalse($this->authService->attempt('argon@tenant101.example', 'wrong')->isSuccessful());
        $this->assertNull($this->authService->getAuthenticatedUser());

        // 2. Valid Argon2id login with $remember = true attaches immutable remember_user cookie
        $loginResult = $this->authService->attempt('argon@tenant101.example', 'CorrectHorseBatteryStaple!', true);
        $this->assertTrue($loginResult->isSuccessful());
        $this->assertSame($this->argonUserId, $loginResult->getUser()?->getId());
        $this->assertSame($this->argonUserId, $this->authService->getAuthenticatedUser()?->getId());

        $cookies = $loginResult->getCookiesToSet();
        $this->assertCount(1, $cookies);
        $this->assertSame('remember_user', $cookies[0]['name']);
        $initialToken = $cookies[0]['value'];
        $this->assertStringContainsString(':', $initialToken);
        $this->assertSame($this->clock->now()->getTimestamp() + 2592000, $cookies[0]['expiry']);

        // 3. Simulate browser restart (clear session memory) and auto-login with persistent token
        $this->sessionBacking = [];
        $this->assertNull($this->authService->getAuthenticatedUser());

        $this->clock->advance('+60 seconds');
        $autoResult = $this->authService->attemptAutoLogin($initialToken);
        $this->assertTrue($autoResult->isSuccessful());
        $this->assertSame($this->argonUserId, $autoResult->getUser()?->getId());
        $this->assertSame($this->argonUserId, $this->authService->getAuthenticatedUser()?->getId());

        $rotatedCookies = $autoResult->getCookiesToSet();
        $this->assertCount(1, $rotatedCookies);
        $rotatedToken = $rotatedCookies[0]['value'];
        $this->assertNotSame($initialToken, $rotatedToken);

        // 4. Replaying the consumed initial token MUST fail and clear remember_user
        $replayResult = $this->authService->attemptAutoLogin($initialToken);
        $this->assertFalse($replayResult->isSuccessful());
        $this->assertContains('remember_user', $replayResult->getCookiesToClear());

        // 5. Logout invalidates the rotated DB token, clears session, and expires cookie
        $logoutResult = $this->authService->logout($rotatedToken);
        $this->assertFalse($logoutResult->isSuccessful());
        $this->assertContains('remember_user', $logoutResult->getCookiesToClear());
        $this->assertNull($this->authService->getAuthenticatedUser());
        $this->assertFalse($this->authService->attemptAutoLogin($rotatedToken)->isSuccessful());
    }

    /**
     * Verifies transparent legacy bcrypt -> Argon2id password hash upgrade and 60-second SSO token issuance.
     */
    public function testTransparentPasswordRehashAndShortLivedSsoToken(): void
    {
        $result = $this->authService->attempt('legacy@tenant101.example', 'LegacyBcryptSecret!', false);
        $this->assertTrue($result->isSuccessful());
        $this->assertSame([], $result->getCookiesToSet());

        $stmt = $this->dbManager->getWriteConnection()->prepare('SELECT password, password_changed_at FROM pg_temp.users WHERE id = ?');
        $stmt->execute([$this->bcryptUserId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertStringStartsWith('$argon2id$', (string) $row['password']);
        $this->assertSame('2026-11-14 12:00:00', $row['password_changed_at']);

        $sso = $this->authService->issueSsoToken($this->bcryptUserId);
        $this->assertSame($this->clock->now()->getTimestamp() + 60, $sso['expiry']);
        $this->assertTrue($this->authService->attemptAutoLogin($sso['token'])->isSuccessful());
    }
}

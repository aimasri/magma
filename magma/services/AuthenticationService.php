<?php

declare(strict_types=1);

namespace Magma\services;

use Magma\contracts\ClockInterface;
use Magma\domain\AuthUser;
use Magma\http\SessionInterface;
use Magma\interfaces\cqrs\UserCommandInterface;
use Magma\interfaces\cqrs\UserQueryInterface;

/**
 * Title: Cohesive Authentication Domain Service
 *
 * Purpose:
 * - Owns user authentication lifecycle orchestration: primary email/password verification,
 *   transparent Argon2id password hash upgrades, persistent remember-me auto-login & SSO token
 *   issuance, and session fixation-safe login/logout state management.
 *
 * Why / Why this design:
 * - High Cohesion over Pass-Through Fragmentation: Consolidates credential verification, session
 *   binding, and persistent-token coordination into one focused domain service with exactly 4
 *   cohesive collaborators (`UserQueryInterface`, `UserCommandInterface`, `RememberMeService`,
 *   `SessionInterface`) plus `ClockInterface`, eliminating ceremonial pass-through wrappers.
 * - Connection-Pool Safety: In `attemptAutoLogin()`, database token validation and rotation complete
 *   inside `RememberMeService::consumeAndRotate()` *before* `$this->login()` mutates session storage,
 *   preventing Redis session locks from holding open PostgreSQL `SERIALIZABLE` transactions.
 *
 * Teaching notes:
 * - Plain-text passwords never leave `attempt()`. Unknown emails execute a constant-cost Argon2id
 *   dummy verification to neutralize user-enumeration timing side-channels.
 */
class AuthenticationService
{
    /**
     * Precomputed Argon2id dummy hash (`memory_cost=65536, time_cost=4, threads=1`) for timing parity.
     */
    private const DUMMY_ARGON2ID_HASH = '$argon2id$v=19$m=65536,t=4,p=1$Zm04bHJFUWpKU29VajAucw$ytNQl3ClKZDw1BmVgzOl+7LR1Wp2DK27AjH+cwQV9lM';

    /**
     * Canonical Argon2id hashing parameters.
     */
    private const ARGON2_OPTIONS = [
        'memory_cost' => 65536,
        'time_cost'   => 4,
        'threads'     => 1,
    ];

    /**
     * Initializes the cohesive authentication service.
     *
     * @param UserQueryInterface $userQuery Read repository for authentication lookups.
     * @param UserCommandInterface $userCommand Write repository for transparent password hash upgrades.
     * @param RememberMeService $rememberMe Selector/validator persistent token service.
     * @param SessionInterface $session Transport-abstracted session state contract.
     * @param ClockInterface $clock Deterministic wall-clock source.
     */
    public function __construct(
        private readonly UserQueryInterface $userQuery,
        private readonly UserCommandInterface $userCommand,
        private readonly RememberMeService $rememberMe,
        private readonly SessionInterface $session,
        private readonly ClockInterface $clock
    ) {}

    /**
     * Verifies primary email/password credentials, establishes the session, and optionally issues a remember-me cookie.
     *
     * Execution Flow:
     * 1. Queries `UserQueryInterface::findForAuth()` for the candidate email.
     * 2. If absent, runs `password_verify()` against `DUMMY_ARGON2ID_HASH` to equalize response latency.
     * 3. Verifies the password hash and transparently upgrades outdated hashes to Argon2id.
     * 4. Regenerates the session ID and binds the authenticated `AuthUser`.
     * 5. When `$remember` is true, issues a persistent token and attaches it to the immutable `AuthenticationResult`.
     *
     * @param string $email
     * @param string $password
     * @param bool $remember
     * @return AuthenticationResult
     */
    public function attempt(string $email, string $password, bool $remember = false): AuthenticationResult
    {
        $user = $this->userQuery->findForAuth($email);

        if ($user === null) {
            password_verify($password, self::DUMMY_ARGON2ID_HASH);
            return AuthenticationResult::failure();
        }

        $hash = is_scalar($user['password'] ?? null) ? (string) $user['password'] : '';
        if ($hash === '' || !password_verify($password, $hash)) {
            return AuthenticationResult::failure();
        }

        if (password_needs_rehash($hash, PASSWORD_ARGON2ID, self::ARGON2_OPTIONS)) {
            $newHash = password_hash($password, PASSWORD_ARGON2ID, self::ARGON2_OPTIONS);
            $userId = isset($user['id']) && is_scalar($user['id']) ? (int) $user['id'] : 0;
            if ($userId > 0) {
                $this->userCommand->updatePassword($userId, $newHash, $this->clock->now());
            }
        }

        $authUser = new AuthUser($user);
        $this->login($authUser);

        $result = AuthenticationResult::success($authUser);
        if ($remember) {
            $tokenData = $this->rememberMe->generateToken($authUser->getId());
            $result = $result->withCookie('remember_user', $tokenData['token'], $tokenData['expiry']);
        }

        return $result;
    }

    /**
     * Attempts automated persistent login and single-use token rotation from a `remember_user` cookie.
     *
     * Execution Flow:
     * 1. Delegates atomic token validation, user resolution, and rotation to `RememberMeService::consumeAndRotate()`.
     * 2. On invalid/expired token, returns a failure result instructing the controller to clear `remember_user`.
     * 3. Regenerates and populates the active session *outside* the database transaction.
     * 4. Returns a successful `AuthenticationResult` carrying the rotated cookie.
     *
     * @param string $token
     * @return AuthenticationResult
     */
    public function attemptAutoLogin(string $token): AuthenticationResult
    {
        $rotationResult = $this->rememberMe->consumeAndRotate(
            $token,
            fn(int $userId): ?AuthUser => $this->userQuery->findById($userId)
        );

        if ($rotationResult === null) {
            return AuthenticationResult::failure()->clearCookie('remember_user');
        }

        $authUser = $rotationResult['user'];
        $this->login($authUser);

        return AuthenticationResult::success($authUser)->withCookie(
            'remember_user',
            $rotationResult['tokenData']['token'],
            $rotationResult['tokenData']['expiry']
        );
    }

    /**
     * Destroys the active session, invalidates any persistent remember-me token, and queues cookie removal.
     *
     * @param string|null $token Optional raw `remember_user` cookie token.
     * @return AuthenticationResult
     */
    public function logout(?string $token = null): AuthenticationResult
    {
        if ($token !== null && $token !== '') {
            $this->rememberMe->invalidateToken($token);
        }

        $this->session->destroy();

        return AuthenticationResult::failure()->clearCookie('remember_user');
    }

    /**
     * Issues a short-lived (60-second) selector/validator token for cross-domain SSO handoff.
     *
     * @param int $userId
     * @return array{token: string, expiry: int}
     */
    public function issueSsoToken(int $userId): array
    {
        return $this->rememberMe->generateToken($userId, 60);
    }

    /**
     * Establishes an authenticated user session, immediately rotating the session identifier to prevent fixation.
     *
     * @param AuthUser $authUser
     * @return void
     */
    public function login(AuthUser $authUser): void
    {
        $this->session->regenerate(true);
        $this->session->set('user', $authUser->toSessionArray());
        $this->session->set('login_time', $this->clock->now()->getTimestamp());
    }

    /**
     * Hydrates and returns the currently authenticated user from session storage, or null when unauthenticated.
     *
     * @return AuthUser|null
     */
    public function getAuthenticatedUser(): ?AuthUser
    {
        $userData = $this->session->get('user');
        if (!is_array($userData)) {
            return null;
        }

        $cleanData = [];
        foreach ($userData as $key => $value) {
            if (is_string($key)) {
                $cleanData[$key] = $value;
            }
        }

        return new AuthUser($cleanData);
    }
}

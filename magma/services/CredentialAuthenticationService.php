<?php

namespace Magma\services;

use Magma\interfaces\cqrs\UserQueryInterface;
use Magma\interfaces\cqrs\UserCommandInterface;
use Magma\contracts\ClockInterface;
use Magma\domain\AuthUser;
use Magma\services\AuthenticationResult;

/**
 * Title: Credential Authentication Service
 *
 * Purpose:
 * - Manage primary authentication (verifying email/password pairs).
 *
 * Why / Why this design:
 * - Extracted from the monolithic AuthenticationService to enforce SRP.
 * - This service handles standard login logic without coupling to persistent cookies.
 *
 * Teaching notes:
 * - This service encapsulates the password hashing boundary. Never allow plain-text passwords to escape this layer.
 */
class CredentialAuthenticationService
{
    protected UserQueryInterface $userRepository;
    protected UserCommandInterface $userCommandRepository;
    protected SessionAuthenticationService $sessionAuth;
    protected ClockInterface $clock;

    /**
     * Initializes the service with dependencies for user retrieval, modification, and session handling.
     *
     * @param UserQueryInterface $userRepository
     * @param UserCommandInterface $userCommandRepository
     * @param SessionAuthenticationService $sessionAuth
     * @param ClockInterface $clock
     */
    public function __construct(
        UserQueryInterface $userRepository,
        UserCommandInterface $userCommandRepository,
        SessionAuthenticationService $sessionAuth,
        ClockInterface $clock
    ) {
        $this->userRepository = $userRepository;
        $this->userCommandRepository = $userCommandRepository;
        $this->sessionAuth = $sessionAuth;
        $this->clock = $clock;
    }

    /**
     * Validates user credentials.
     * 
     * Execution Flow:
     * 1. Query the UserRepositoryInterface using `findForAuth` to securely retrieve the user record.
     * 2. If no user is found, immediately return failure (with timing attack mitigation).
     * 3. Verify the plain-text password against the stored bcrypt/argon2 hash.
     * 4. Call `SessionAuthenticationService->login()` to establish the active session.
     * 5. Return an `AuthenticationResult` payload.
     */
    public function attempt(string $email, string $password): AuthenticationResult
    {
        $user = $this->userRepository->findForAuth($email);

        if (!$user) {
            // Mitigate timing attacks by performing a dummy hash comparison
            password_verify($password, '$2y$10$abcdefghijklmnopqrstuv');
            return AuthenticationResult::failure();
        }

        $hash = is_scalar($user['password'] ?? null) ? (string) $user['password'] : '';

        if (!password_verify($password, $hash)) {
            return AuthenticationResult::failure();
        }

        // Transparently upgrade legacy hashes
        if (password_needs_rehash($hash, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1])) {
            $newHash = password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);
            $userId = isset($user['id']) && is_scalar($user['id']) ? (int) $user['id'] : 0;
            if ($userId > 0) {
                $this->userCommandRepository->updatePassword($userId, $newHash, $this->clock->now());
            }
        }

        $authUser = new AuthUser($user);
        $this->sessionAuth->login($authUser);

        return AuthenticationResult::success($authUser);
    }
}

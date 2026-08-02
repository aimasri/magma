<?php

namespace Magma\services;

use Magma\models\RememberTokenRepository;

/**
 * Persistent Authentication Service (Remember Me)
 *
 * Purpose:
 * - Manage the generation, validation, and invalidation of long-lived persistent login tokens.
 * - Implement the Selector/Validator pattern to prevent timing attacks and database leak exploitation.
 *
 * Why / Why this design:
 * - This service is entirely transport-agnostic; it does not read from `$_COOKIE` or modify 
 *   HTTP responses. By keeping transport mechanisms out of domain services, it maintains a 
 *   Single Responsibility and stays highly decoupled, making it usable in CLI or testing.
 *
 * Teaching notes:
 * - The Selector/Validator pattern is an industry standard for "Remember Me" functionality. 
 *   If an attacker steals the `remember_tokens` database table, they cannot hijack active 
 *   sessions because the database only contains the hashed validator, while the user's 
 *   cookie contains the plain-text validator.
 */
class RememberMeService
{
    protected RememberTokenRepository $userTokenRepository;

    public function __construct(RememberTokenRepository $userTokenRepository)
    {
        $this->userTokenRepository = $userTokenRepository;
    }

    /**
     * Validates a persistent token and returns the associated user ID.
     * 
     * @param string $token The raw token string (format: "selector:validator")
     * @return int|null The user ID if valid, null otherwise
     */
    public function validateToken(string $token): ?int
    {
        if (strpos($token, ':') === false) return null;

        [$selector, $validator] = explode(':', $token);
        $tokenRecord = $this->userTokenRepository->findValidRememberToken($selector);

        if ($tokenRecord && hash_equals($tokenRecord['hashed_validator'], hash('sha256', $validator))) {
            return (int) $tokenRecord['user_id'];
        }

        return null;
    }

    /**
     * Generates and stores a new persistent login token.
     * 
     * Execution Flow:
     * 1. Generate a random 12-byte Selector.
     * 2. Generate a random 32-byte Validator.
     * 3. Calculate an expiration timestamp (e.g., 30 days in the future).
     * 4. Hash the Validator using SHA-256.
     * 5. Store the Selector and the Hashed Validator in the database.
     * 6. Return the plain-text "Selector:Validator" string to be stored in the user's cookie.
     * 
     * Logic behind the logic:
     * - We use `bin2hex(random_bytes())` to ensure cryptographically secure pseudo-random 
     *   string generation, preventing attackers from predicting session tokens.
     * 
     * @param int $userId The user ID to issue the token for
     * @return array{token: string, expiry: int}
     */
    public function generateToken(int $userId): array
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expiry = time() + (86400 * 30); // 30 days

        $this->userTokenRepository->saveRememberToken(
            $userId, 
            $selector, 
            hash('sha256', $validator), 
            date('Y-m-d H:i:s', $expiry)
        );

        return [
            'token' => "$selector:$validator",
            'expiry' => $expiry
        ];
    }

    /**
     * Rotates a persistent token by invalidating the old one and issuing a new one.
     * 
     * Execution Flow:
     * 1. Invalidate the old token (if it exists).
     * 2. Generate and return a new token payload.
     * 
     * Logic behind the logic:
     * - This encapsulates the token rotation lifecycle entirely within the domain service, 
     *   preventing the Controller from needing to orchestrate multiple domain actions.
     */
    public function rotateToken(string $oldToken, int $userId): array
    {
        $this->invalidateToken($oldToken);
        return $this->generateToken($userId);
    }

    /**
     * Invalidates an existing persistent token.
     * 
     * @param string $token The raw token string
     */
    public function invalidateToken(string $token): void
    {
        if (strpos($token, ':') !== false) {
            [$selector] = explode(':', $token);
            $this->userTokenRepository->deleteRememberToken($selector);
        }
    }
}

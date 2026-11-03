<?php

declare(strict_types=1);

namespace Magma\services;

use Magma\repositories\RememberTokenRepository;
use Magma\contracts\ClockInterface;
use Magma\contracts\TransactionManagerInterface;

/**
 * Title: Persistent Authentication Service (Remember Me)
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
    protected ClockInterface $clock;
    protected ?TransactionManagerInterface $transactionManager;

    /**
     * Initializes the RememberMeService (strictly adheres to <= 3 injected dependencies).
     *
     * Execution Flow:
     * 1. Injects the RememberTokenRepository, ClockInterface, and optional TransactionManagerInterface.
     * 2. Assigns them to protected properties to interact with persistence and transaction management.
     *
     * @param RememberTokenRepository $userTokenRepository
     * @param ClockInterface $clock
     * @param TransactionManagerInterface|null $transactionManager
     */
    public function __construct(
        RememberTokenRepository $userTokenRepository,
        ClockInterface $clock,
        ?TransactionManagerInterface $transactionManager = null
    ) {
        $this->userTokenRepository = $userTokenRepository;
        $this->clock = $clock;
        $this->transactionManager = $transactionManager;
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

        $expectedHash = $tokenRecord && isset($tokenRecord['hashed_validator']) && is_string($tokenRecord['hashed_validator']) 
            ? $tokenRecord['hashed_validator'] 
            : hash('sha256', 'dummy_validator_to_prevent_timing_attacks');

        if (hash_equals($expectedHash, hash('sha256', $validator))) {
            return $tokenRecord && isset($tokenRecord['user_id']) && is_scalar($tokenRecord['user_id']) ? (int) $tokenRecord['user_id'] : null;
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
     * @param int $ttlSeconds Time to live in seconds (default 30 days)
     * @return array{token: string, expiry: int}
     */
    public function generateToken(int $userId, int $ttlSeconds = 2592000): array
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $now = $this->clock->now();
        $expiryTimestamp = $now->getTimestamp() + $ttlSeconds;
        $expiryDate = $now->setTimestamp($expiryTimestamp);

        $this->userTokenRepository->saveRememberToken(
            $userId, 
            $selector, 
            hash('sha256', $validator), 
            $expiryDate->format('Y-m-d H:i:s')
        );

        return [
            'token' => "$selector:$validator",
            'expiry' => $expiryTimestamp
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
     * 
     * @param string $oldToken
     * @param int $userId
     * @return array{token: string, expiry: int}
     */
    public function rotateToken(string $oldToken, int $userId): array
    {
        if ($this->transactionManager !== null) {
            /** @var array{token: string, expiry: int} */
            return $this->transactionManager->transactional(function () use ($oldToken, $userId): array {
                $this->invalidateToken($oldToken);
                return $this->generateToken($userId);
            });
        }

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

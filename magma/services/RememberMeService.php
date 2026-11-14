<?php

declare(strict_types=1);

namespace Magma\services;

use Magma\contracts\ClockInterface;
use Magma\database\TransactionManagerInterface;
use Magma\interfaces\repositories\RememberTokenRepositoryInterface;

/**
 * Title: Persistent Authentication Token Service (Remember Me & Short-Lived SSO)
 *
 * Purpose:
 * - Manages generation, constant-time validation, atomic consumption/rotation, and invalidation
 *   of selector/validator persistent login tokens.
 *
 * Why / Why this design:
 * - Selector/Validator Split: Lookups occur by public selector while the validator is compared in
 *   constant time (`hash_equals`) against a SHA-256 digest, preventing timing side-channels and
 *   neutralizing read-only database leaks.
 * - Atomic Rotation (`consumeAndRotate`): Wraps token validation, user lookup, deletion, and
 *   replacement token issuance inside a single `SERIALIZABLE` transaction when a `TransactionManagerInterface`
 *   is provided, eliminating concurrent auto-login TOCTOU race conditions.
 *
 * Teaching notes:
 * - Depending on `RememberTokenRepositoryInterface` rather than a concrete repository adheres to DIP
 *   and allows deterministic in-memory unit testing alongside live PostgreSQL integration tests.
 */
class RememberMeService
{
    /**
     * Initializes the persistent token service.
     *
     * @param RememberTokenRepositoryInterface $userTokenRepository Token persistence contract.
     * @param ClockInterface $clock Deterministic wall-clock source.
     * @param TransactionManagerInterface|null $transactionManager Optional transaction manager for atomic token rotation.
     */
    public function __construct(
        protected RememberTokenRepositoryInterface $userTokenRepository,
        protected ClockInterface $clock,
        protected ?TransactionManagerInterface $transactionManager = null
    ) {}

    /**
     * Validates a raw `"selector:validator"` token and returns the associated user ID.
     *
     * @param string $token Raw cookie token string (`selector:validator`).
     * @return int|null Associated user ID when valid, or null otherwise.
     */
    public function validateToken(string $token): ?int
    {
        if (!str_contains($token, ':')) {
            return null;
        }

        [$selector, $validator] = explode(':', $token, 2);
        if ($selector === '' || $validator === '') {
            return null;
        }

        $tokenRecord = $this->userTokenRepository->findValidRememberToken($selector);

        $expectedHash = $tokenRecord !== null && isset($tokenRecord['hashed_validator']) && is_string($tokenRecord['hashed_validator'])
            ? $tokenRecord['hashed_validator']
            : hash('sha256', 'dummy_validator_to_prevent_timing_attacks');

        if (hash_equals($expectedHash, hash('sha256', $validator))) {
            return $tokenRecord !== null && isset($tokenRecord['user_id']) && is_scalar($tokenRecord['user_id'])
                ? (int) $tokenRecord['user_id']
                : null;
        }

        return null;
    }

    /**
     * Generates and persists a new selector/validator token.
     *
     * @param int $userId User ID receiving the token.
     * @param int $ttlSeconds Time-to-live in seconds (defaults to 30 days).
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
            'token'  => "{$selector}:{$validator}",
            'expiry' => $expiryTimestamp,
        ];
    }

    /**
     * Rotates an existing persistent token by invalidating it and issuing a fresh replacement.
     *
     * @param string $oldToken
     * @param int $userId
     * @return array{token: string, expiry: int}
     */
    public function rotateToken(string $oldToken, int $userId): array
    {
        if ($this->transactionManager !== null) {
            /** @var array{token: string, expiry: int} $rotated */
            $rotated = $this->transactionManager->transactional(function () use ($oldToken, $userId): array {
                $this->invalidateToken($oldToken);
                return $this->generateToken($userId);
            });
            return $rotated;
        }

        $this->invalidateToken($oldToken);
        return $this->generateToken($userId);
    }

    /**
     * Atomically validates a persistent token, resolves the user via callback, and rotates the token.
     *
     * Execution Flow:
     * 1. Runs inside a single database transaction when `$this->transactionManager` is present.
     * 2. Validates the selector/validator pair in constant time.
     * 3. Resolves the active user record; aborts without rotating if the user no longer exists.
     * 4. Deletes the consumed token and persists a newly generated selector/validator pair.
     *
     * @template TUser of object
     * @param string $token Raw `"selector:validator"` token string.
     * @param callable(int): (?TUser) $userResolver Callback returning the user domain object for a user ID.
     * @return array{user: TUser, tokenData: array{token: string, expiry: int}}|null
     */
    public function consumeAndRotate(string $token, callable $userResolver): ?array
    {
        $operation = function () use ($token, $userResolver): ?array {
            $userId = $this->validateToken($token);
            if ($userId === null) {
                return null;
            }

            $user = $userResolver($userId);
            if ($user === null) {
                return null;
            }

            $this->invalidateToken($token);
            $tokenData = $this->generateToken($userId);

            return [
                'user'      => $user,
                'tokenData' => $tokenData,
            ];
        };

        if ($this->transactionManager !== null) {
            /** @var array{user: TUser, tokenData: array{token: string, expiry: int}}|null $result */
            $result = $this->transactionManager->transactional($operation);
            return $result;
        }

        return $operation();
    }

    /**
     * Deletes a persistent token by its selector component.
     *
     * @param string $token Raw `"selector:validator"` string.
     * @return void
     */
    public function invalidateToken(string $token): void
    {
        if (str_contains($token, ':')) {
            [$selector] = explode(':', $token, 2);
            if ($selector !== '') {
                $this->userTokenRepository->deleteRememberToken($selector);
            }
        }
    }
}

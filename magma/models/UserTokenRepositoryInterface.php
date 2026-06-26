<?php

namespace Magma\models;

/**
 * User Token Repository Interface
 *
 * Purpose:
 * - Defines the contract for user security token storage (Remember Me, Password Resets).
 *
 * Why / Why this design:
 * - Dependency Inversion Principle: Services like `PasswordResetService` and `RememberMeService` 
 *   should depend on this abstraction rather than the concrete PDO implementation, making 
 *   them easier to unit test.
 *
 * Teaching notes:
 * - Interfaces define the "what" without the "how". This ensures that if we later decide 
 *   to store tokens in Redis instead of PostgreSQL, the domain services remain completely untouched.
 */
interface UserTokenRepositoryInterface
{
    // Remember Me Tokens
    public function saveRememberToken(int $userId, string $selector, string $hashedValidator, string $expiresAt): void;
    public function findValidRememberToken(string $selector): ?array;
    public function deleteRememberToken(string $selector): void;

    // Password Reset Tokens
    public function createPasswordResetToken(int $userId, \Magma\domain\PasswordResetToken $token): void;
    public function findValidPasswordResetToken(string $tokenHash): ?array;
    public function deleteAllPasswordResetTokensForUser(int $userId): void;
    public function deletePasswordResetToken(string $tokenHash): void;

    // Maintenance
    public function deleteExpiredTokens(): int;
}

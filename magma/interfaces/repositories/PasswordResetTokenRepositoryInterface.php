<?php

namespace Magma\interfaces\repositories;

use Magma\domain\PasswordResetToken;

/**
 * Title: Password Reset Token Repository Interface
 *
 * Purpose:
 * - Abstracts the database storage mechanism for password reset tokens.
 *
 * Why this design:
 * - Decouples the user identity domain from infrastructure-level persistence, adhering to the Repository Pattern.
 *
 * Teaching notes:
 * - Security note: Tokens must be stored via one-way cryptographic hashes.
 */
interface PasswordResetTokenRepositoryInterface
{
    public function createPasswordResetToken(int $userId, PasswordResetToken $token): void;
    /**
     * @return array<string, mixed>|null
     */
    public function findValidPasswordResetToken(string $tokenHash): ?array;
    public function deleteAllPasswordResetTokensForUser(int $userId): void;
    public function deletePasswordResetToken(string $tokenHash): void;
    public function deleteExpiredTokens(): int;
}

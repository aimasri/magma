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
    /**
     * Persists a new password reset token for a given user.
     *
     * @param int $userId
     * @param PasswordResetToken $token
     * @return void
     */
    public function createPasswordResetToken(int $userId, PasswordResetToken $token): void;

    /**
     * Finds a valid, unexpired password reset token by its hash.
     *
     * @param string $tokenHash
     * @return array<string, mixed>|null
     */
    public function findValidPasswordResetToken(string $tokenHash): ?array;

    /**
     * Deletes all pending password reset tokens for a given user.
     *
     * Logic behind the logic:
     * - Executed before generating a new token to ensure only one valid reset token exists per user at any time, reducing the attack surface.
     *
     * @param int $userId
     * @return void
     */
    public function deleteAllPasswordResetTokensForUser(int $userId): void;

    /**
     * Deletes a specific password reset token after it has been successfully used.
     *
     * @param string $tokenHash
     * @return void
     */
    public function deletePasswordResetToken(string $tokenHash): void;

    /**
     * Purges all expired password reset tokens from the storage.
     *
     * @return int The number of tokens deleted.
     */
    public function deleteExpiredTokens(): int;
}

<?php

namespace Magma\interfaces\repositories;

/**
 * Title: Remember Token Repository Interface
 *
 * Purpose:
 * - Abstracts the database storage mechanism for "remember me" authentication tokens.
 *
 * Why this design:
 * - Decouples the authentication service from the SQL schema, allowing us to swap the database driver or use an external caching mechanism (like Redis) for session persistence without changing the core domain logic.
 *
 * Teaching notes:
 * - Security note: Always hash the validator portion of the token before persisting to this repository.
 */
interface RememberTokenRepositoryInterface
{
    public function saveRememberToken(int $userId, string $selector, string $hashedValidator, string $expiresAt): void;
    /**
     * @return array<string, mixed>|null
     */
    public function findValidRememberToken(string $selector): ?array;
    public function deleteRememberToken(string $selector): void;
    public function deleteExpiredTokens(): int;
    public function deleteAllRememberTokensForUser(int $userId): void;
}

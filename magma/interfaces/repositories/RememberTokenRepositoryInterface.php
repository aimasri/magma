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
    /**
     * Persists a newly generated remember-me token.
     *
     * @param int $userId
     * @param string $selector
     * @param string $hashedValidator
     * @param string $expiresAt
     * @return void
     */
    public function saveRememberToken(int $userId, string $selector, string $hashedValidator, string $expiresAt): void;

    /**
     * Finds a valid, unexpired remember-me token by its selector.
     *
     * @param string $selector
     * @return array<string, mixed>|null
     */
    public function findValidRememberToken(string $selector): ?array;

    /**
     * Deletes a specific remember-me token.
     *
     * Logic behind the logic:
     * - Usually triggered upon user logout or when a token is successfully rotated, preventing reuse of stale sessions.
     *
     * @param string $selector
     * @return void
     */
    public function deleteRememberToken(string $selector): void;

    /**
     * Purges all expired remember-me tokens from storage.
     *
     * @return int The number of tokens deleted.
     */
    public function deleteExpiredTokens(): int;

    /**
     * Invalidates all active remember-me sessions for a specific user.
     *
     * Logic behind the logic:
     * - Allows a user to remotely sign out of all other devices by wiping all associated persistent sessions.
     *
     * @param int $userId
     * @return void
     */
    public function deleteAllRememberTokensForUser(int $userId): void;
}

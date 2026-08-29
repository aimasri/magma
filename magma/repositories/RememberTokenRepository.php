<?php

namespace Magma\repositories;

use Magma\models\AbstractCommandRepository;
use Magma\interfaces\repositories\RememberTokenRepositoryInterface;

/**
 * Title: Remember Token Repository
 * Purpose:
 * - Handles the persistence and retrieval of "remember me" session tokens.
 * - Coordinates token creation, validation lookups, and expired token cleanup.
 * - Extends AbstractCommandRepository to ensure token operations are performed on the write database connection.
 * Why/Why this design:
 * - Uses the Repository pattern to encapsulate all database interactions related to user session tokens.
 * - Intentionally uses the write connection even for reads (`findValidRememberToken`) to prevent replication lag from causing failed logins immediately after token creation.
 * Teaching notes:
 * - The conscious breaking of strict CQRS (reading from a write connection) is a pragmatic industry standard for critical auth flows to avoid race conditions with replica databases.
 */
class RememberTokenRepository extends AbstractCommandRepository implements RememberTokenRepositoryInterface
{


    /**
     * Saves a new "remember me" session token into the database.
     *
     * Execution Flow:
     * 1. Prepares an INSERT statement for the user_tokens table with type 'remember_me'.
     * 2. Binds the user ID, public selector, hashed validator, and expiration time.
     * 3. Executes the query on the write database connection.
     *
     * Logic behind the logic:
     * - Splitting the token into a public selector and a hashed validator mitigates timing attacks
     *   and database leak vulnerabilities. The database only stores the secure hash.
     *
     * @param int $userId The user ID associated with the token.
     * @param string $selector The public lookup selector.
     * @param string $hashedValidator The securely hashed validator string.
     * @param string $expiresAt The expiration timestamp.
     * @return void
     */
    public function saveRememberToken(int $userId, string $selector, string $hashedValidator, string $expiresAt): void
    {
        $stmt = $this->getDb()->prepare("INSERT INTO user_tokens (user_id, type, selector, token_hash, expires_at) VALUES (?, 'remember_me', ?, ?, ?)");
        $stmt->execute([$userId, $selector, $hashedValidator, $expiresAt]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findValidRememberToken(string $selector): ?array
    {
        // Even though this is a find method, we keep it here for simplicity of SRP over CQRS strictness, 
        // or we use the read connection. Since we extend AbstractCommandRepository, we only have write connection.
        // It's acceptable to use write connection for session lookup to prevent replica lag issues on login.
        $stmt = $this->getDb()->prepare("
            SELECT user_id, token_hash as hashed_validator 
            FROM user_tokens 
            WHERE selector = ? AND type = 'remember_me' AND expires_at > NOW()
        ");
        $stmt->execute([$selector]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($result) ? $result : null;
    }

    /**
     * Deletes a specific "remember me" token using its public selector.
     *
     * Execution Flow:
     * 1. Prepares a DELETE statement filtering by the given selector and token type.
     * 2. Executes the removal on the database.
     *
     * Logic behind the logic:
     * - This is invoked during user logout or when a token is intentionally revoked,
     *   ensuring the persistent session is securely terminated.
     *
     * @param string $selector The public selector of the token to delete.
     * @return void
     */
    public function deleteRememberToken(string $selector): void
    {
        $stmt = $this->getDb()->prepare("DELETE FROM user_tokens WHERE selector = ? AND type = 'remember_me'");
        $stmt->execute([$selector]);
    }

    /**
     * Removes all expired "remember me" tokens from the database.
     *
     * Execution Flow:
     * 1. Prepares a DELETE statement targeting 'remember_me' tokens where the expiration is in the past.
     * 2. Executes the query and returns the count of deleted records.
     *
     * Logic behind the logic:
     * - Prevents database bloat by continuously pruning stale session tokens. Usually executed by a background job.
     *
     * @return int The number of tokens successfully deleted.
     */
    public function deleteExpiredTokens(): int
    {
        $stmt = $this->getDb()->prepare("DELETE FROM user_tokens WHERE expires_at < NOW() AND type = 'remember_me'");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Deletes all active "remember me" tokens for a given user.
     *
     * Execution Flow:
     * 1. Prepares a DELETE statement for all 'remember_me' tokens belonging to the user ID.
     * 2. Executes the statement on the write connection.
     *
     * Logic behind the logic:
     * - Useful for global sign-out scenarios, such as when a user changes their password
     *   or suspects their account has been compromised, forcing all active sessions to re-authenticate.
     *
     * @param int $userId The ID of the user whose tokens should be cleared.
     * @return void
     */
    public function deleteAllRememberTokensForUser(int $userId): void
    {
        $stmt = $this->getDb()->prepare("DELETE FROM user_tokens WHERE user_id = ? AND type = 'remember_me'");
        $stmt->execute([$userId]);
    }
}

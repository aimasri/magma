<?php

namespace Magma\models;

/**
 * User Token Data Access
 *
 * Purpose:
 * - Handle the storage, retrieval, and deletion of all user security tokens 
 *   (e.g., remember me, password resets) in the polymorphic `user_tokens` table.
 *
 * Why / Why this design:
 * - Consolidates all database operations for the `user_tokens` table into a single 
 *   repository, adhering to the Single Responsibility Principle. Having multiple 
 *   repositories for the same table was an SRP and DRY violation.
 *
 * Teaching notes:
 * - The Repository Pattern acts as an in-memory collection interface. This class hides 
 *   the raw SQL implementation details from the domain services, allowing us to swap out 
 *   the database layer (e.g., from PDO to an ORM) without touching business logic.
 */
class UserTokenRepository extends BaseRepository implements UserTokenRepositoryInterface
{


    // ------------------------------------------------------------------
    // Remember Me Tokens
    // ------------------------------------------------------------------

    /**
     * Store a New Remember Me Token
     *
     * Purpose:
     * - Persists the hashed validator against the user and a unique selector.
     *
     * Execution Flow:
     * 1. Prepares the INSERT statement for the `user_tokens` table.
     * 2. Hardcodes the `type` column to 'remember_me' to distinguish from other token types.
     * 3. Executes the query with the provided bindings.
     *
     * @param int $userId The ID of the authenticated user.
     * @param string $selector The public lookup key.
     * @param string $hashedValidator The SHA-256 hashed secret.
     * @param string $expiresAt The database-formatted expiration timestamp.
     * @return void
     */
    public function saveRememberToken(int $userId, string $selector, string $hashedValidator, string $expiresAt): void
    {
        $stmt = $this->dbWrite->prepare("INSERT INTO user_tokens (user_id, type, selector, token_hash, expires_at) VALUES (?, 'remember_me', ?, ?, ?)");
        $stmt->execute([$userId, $selector, $hashedValidator, $expiresAt]);
    }

    /**
     * Retrieve a Valid Token by Selector
     *
     * Purpose:
     * - Fetches the token hash for a given selector, ensuring it hasn't expired.
     *
     * Logic behind the logic:
     * - Checking expiration inside the SQL query (`expires_at > NOW()`) ensures we never 
     *   accidentally return an expired token to the application layer, shifting the 
     *   responsibility of TTL enforcement entirely to the database engine.
     *
     * @param string $selector The public lookup key presented by the user's cookie.
     * @return array|null Returns associative array with user_id and hashed_validator, or null if invalid/expired.
     */
    public function findValidRememberToken(string $selector): ?array
    {
        $stmt = $this->dbRead->prepare("
            SELECT user_id, token_hash as hashed_validator 
            FROM user_tokens 
            WHERE selector = ? AND type = 'remember_me' AND expires_at > NOW()
        ");
        $stmt->execute([$selector]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Purge a Used or Invalid Token
     *
     * Purpose:
     * - Removes a specific token from the database, usually after a successful login 
     *   (to rotate the token) or when the user explicitly logs out.
     *
     * @param string $selector The public lookup key to delete.
     * @return void
     */
    public function deleteRememberToken(string $selector): void
    {
        $stmt = $this->dbWrite->prepare("DELETE FROM user_tokens WHERE selector = ? AND type = 'remember_me'");
        $stmt->execute([$selector]);
    }

    // ------------------------------------------------------------------
    // Password Reset Tokens
    // ------------------------------------------------------------------

    /**
     * Store a Password Reset Token
     *
     * Purpose:
     * - Persists a newly generated, hashed password reset token against a user ID.
     *
     * Execution Flow:
     * 1. Prepares the INSERT statement for the `user_tokens` table.
     * 2. Hardcodes the `type` column to 'password_reset'.
     * 3. Executes the query with the provided bindings.
     *
     * @param int $userId The ID of the user requesting the reset.
     * @param \Magma\domain\PasswordResetToken $token The token entity.
     * @return void
     */
    public function createPasswordResetToken(int $userId, \Magma\domain\PasswordResetToken $token): void
    {
        $stmt = $this->dbWrite->prepare("INSERT INTO user_tokens (user_id, type, token_hash, expires_at) VALUES (?, 'password_reset', ?, ?)");
        $stmt->execute([$userId, $token->getHashedToken(), $token->getExpiresAt()]);
    }

    /**
     * Retrieve User ID by Valid Reset Token
     *
     * Purpose:
     * - Looks up the user ID associated with a provided token hash, ensuring it's still valid.
     *
     * Logic behind the logic:
     * - Checking expiration inside the SQL query (`expires_at > NOW()`) mitigates race 
     *   conditions and prevents application code from mistakenly processing an expired token.
     *
     * @param string $tokenHash The hashed token submitted by the user.
     * @return array|null Returns associative array with user_id, or null if invalid/expired.
     */
    public function findValidPasswordResetToken(string $tokenHash): ?array
    {
        $stmt = $this->dbRead->prepare("
            SELECT user_id FROM user_tokens 
            WHERE token_hash = ? AND type = 'password_reset' AND expires_at > NOW()
        ");
        $stmt->execute([$tokenHash]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Purge all Password Reset Tokens for a given user
     *
     * Purpose:
     * - Removes all existing reset tokens for a user.
     * - This is critical before issuing a new token to prevent multiple valid tokens.
     *
     * @param int $userId The ID of the user.
     * @return void
     */
    public function deleteAllPasswordResetTokensForUser(int $userId): void
    {
        $stmt = $this->dbWrite->prepare("DELETE FROM user_tokens WHERE user_id = ? AND type = 'password_reset'");
        $stmt->execute([$userId]);
    }

    /**
     * Purge a Used or Invalid Password Reset Token
     *
     * Purpose:
     * - Removes a specific token from the database, effectively nullifying it.
     * - This is critical immediately after a successful password reset to prevent replay attacks.
     *
     * @param string $tokenHash The hashed token to delete.
     * @return void
     */
    public function deletePasswordResetToken(string $tokenHash): void
    {
        $stmt = $this->dbWrite->prepare("DELETE FROM user_tokens WHERE token_hash = ? AND type = 'password_reset'");
        $stmt->execute([$tokenHash]);
    }

    // ------------------------------------------------------------------
    // Maintenance
    // ------------------------------------------------------------------

    /**
     * Purge all expired tokens from the database
     *
     * Purpose:
     * - Prevent the `user_tokens` table from growing unboundedly with orphaned, 
     *   expired rows over time.
     *
     * Why / Why this design:
     * - By running this periodically via a cron job, we maintain table health 
     *   and query performance without impacting the synchronous request lifecycle.
     *
     * Teaching notes:
     * - This should be called by a scheduled CLI script (e.g. daily) to clean up 
     *   tokens that naturally expired without being explicitly consumed.
     *
     * @return int The number of rows deleted.
     */
    public function deleteExpiredTokens(): int
    {
        $stmt = $this->dbWrite->prepare("DELETE FROM user_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        
        return $stmt->rowCount();
    }
}

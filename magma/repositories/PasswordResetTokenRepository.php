<?php

namespace Magma\repositories;

use Magma\models\AbstractCommandRepository;
use Magma\interfaces\repositories\PasswordResetTokenRepositoryInterface;
use Magma\contracts\ClockInterface;
use Magma\database\DatabaseConnectionManager;
use Magma\security\TenantContext;

/**
 * Title: Password Reset Token Repository
 * Purpose:
 * - Manages the lifecycle of password reset tokens in the database.
 * - Handles creation, validation, and cleanup of sensitive reset hashes.
 * - Coordinates with the write database connection to ensure immediate consistency.
 * Why/Why this design:
 * - Centralizes token persistence logic, keeping the authentication services clean.
 * - Forces write-connection usage for lookups (`findValidPasswordResetToken`) to prevent replication lag from causing a user to be locked out right after requesting a reset.
 * Teaching notes:
 * - Security-critical tables like `user_tokens` often require synchronous write-read guarantees, justifying the bypass of typical read-replica routing.
 */
class PasswordResetTokenRepository extends AbstractCommandRepository implements PasswordResetTokenRepositoryInterface
{
    protected ClockInterface $clock;

    public function __construct(DatabaseConnectionManager $dbManager, ?TenantContext $tenantContext, ClockInterface $clock)
    {
        parent::__construct($dbManager, $tenantContext);
        $this->clock = $clock;
    }

    /**
     * Creates and persists a new password reset token for a user.
     *
     * Execution Flow:
     * 1. Prepares an INSERT statement targeting the user_tokens table.
     * 2. Binds the user ID, the securely hashed token, and its expiration timestamp.
     * 3. Executes the statement on the write connection.
     *
     * Logic behind the logic:
     * - Using the write connection ensures the token is immediately available for subsequent
     *   validation checks, avoiding issues with database replication lag.
     *
     * @param int $userId The ID of the user requesting a password reset.
     * @param \Magma\domain\PasswordResetToken $token The token domain object containing the hash and expiry.
     * @return void
     */
    public function createPasswordResetToken(int $userId, \Magma\domain\PasswordResetToken $token): void
    {
        $stmt = $this->getDb()->prepare("INSERT INTO user_tokens (user_id, type, token_hash, expires_at) VALUES (?, 'password_reset', ?, ?)");
        $stmt->execute([$userId, $token->getHashedToken(), $token->getExpiresAt()]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findValidPasswordResetToken(string $tokenHash): ?array
    {
        $stmt = $this->getDb()->prepare("
            SELECT user_id FROM user_tokens 
            WHERE token_hash = ? AND type = 'password_reset' AND expires_at > ?
        ");
        $stmt->execute([$tokenHash, $this->clock->now()->format('Y-m-d H:i:s')]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (is_array($result)) {
            $row = [];
            foreach ($result as $k => $v) {
                if (is_string($k)) {
                    $row[$k] = $v;
                }
            }
            return $row;
        }
        return null;
    }

    /**
     * Deletes all existing password reset tokens for a specific user.
     *
     * Execution Flow:
     * 1. Prepares a DELETE statement for the user_tokens table filtering by user ID and token type.
     * 2. Executes the deletion to invalidate any prior password reset requests.
     *
     * Logic behind the logic:
     * - This ensures that issuing a new password reset automatically invalidates any previously
     *   issued, unused tokens, reducing the window of opportunity for token interception.
     *
     * @param int $userId The ID of the user whose tokens should be deleted.
     * @return void
     */
    public function deleteAllPasswordResetTokensForUser(int $userId): void
    {
        $stmt = $this->getDb()->prepare("DELETE FROM user_tokens WHERE user_id = ? AND type = 'password_reset'");
        $stmt->execute([$userId]);
    }

    /**
     * Deletes a specific password reset token using its hash.
     *
     * Execution Flow:
     * 1. Prepares a DELETE statement targeting the specific token hash and type.
     * 2. Executes the statement to permanently remove the token.
     *
     * Logic behind the logic:
     * - Tokens are single-use. This method is called immediately after a successful password
     *   reset to prevent the token from being reused in a replay attack.
     *
     * @param string $tokenHash The hashed token to delete.
     * @return void
     */
    public function deletePasswordResetToken(string $tokenHash): void
    {
        $stmt = $this->getDb()->prepare("DELETE FROM user_tokens WHERE token_hash = ? AND type = 'password_reset'");
        $stmt->execute([$tokenHash]);
    }

    /**
     * Cleans up all expired password reset tokens from the database.
     *
     * Execution Flow:
     * 1. Prepares a DELETE statement for tokens whose expiration timestamp is in the past.
     * 2. Executes the statement and returns the number of affected rows.
     *
     * Logic behind the logic:
     * - This acts as a garbage collection routine to keep the user_tokens table lean and performant.
     *   It is typically executed via a scheduled cron job or maintenance worker.
     *
     * @return int The number of expired tokens deleted.
     */
    public function deleteExpiredTokens(): int
    {
        $stmt = $this->getDb()->prepare("DELETE FROM user_tokens WHERE expires_at < ? AND type = 'password_reset'");
        $stmt->execute([$this->clock->now()->format('Y-m-d H:i:s')]);
        return $stmt->rowCount();
    }
}

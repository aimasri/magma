<?php

namespace Magma\models;

use Magma\models\BaseRepository;

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
class PasswordResetTokenRepository extends BaseRepository
{
    public function execute(mixed $payload): mixed
    {
        return null;
    }

    public function createPasswordResetToken(int $userId, \Magma\domain\PasswordResetToken $token): void
    {
        $stmt = $this->dbWrite->prepare("INSERT INTO user_tokens (user_id, type, token_hash, expires_at) VALUES (?, 'password_reset', ?, ?)");
        $stmt->execute([$userId, $token->getHashedToken(), $token->getExpiresAt()]);
    }

    public function findValidPasswordResetToken(string $tokenHash): ?array
    {
        $stmt = $this->dbWrite->prepare("
            SELECT user_id FROM user_tokens 
            WHERE token_hash = ? AND type = 'password_reset' AND expires_at > NOW()
        ");
        $stmt->execute([$tokenHash]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function deleteAllPasswordResetTokensForUser(int $userId): void
    {
        $stmt = $this->dbWrite->prepare("DELETE FROM user_tokens WHERE user_id = ? AND type = 'password_reset'");
        $stmt->execute([$userId]);
    }

    public function deletePasswordResetToken(string $tokenHash): void
    {
        $stmt = $this->dbWrite->prepare("DELETE FROM user_tokens WHERE token_hash = ? AND type = 'password_reset'");
        $stmt->execute([$tokenHash]);
    }

    public function deleteExpiredTokens(): int
    {
        $stmt = $this->dbWrite->prepare("DELETE FROM user_tokens WHERE expires_at < NOW() AND type = 'password_reset'");
        $stmt->execute();
        return $stmt->rowCount();
    }
}

<?php

namespace Magma\models;

use Magma\models\BaseRepository;

/**
 * Title: Remember Token Repository
 * Purpose:
 * - Handles the persistence and retrieval of "remember me" session tokens.
 * - Coordinates token creation, validation lookups, and expired token cleanup.
 * - Extends BaseCommandRepository to ensure token operations are performed on the write database connection.
 * Why/Why this design:
 * - Uses the Repository pattern to encapsulate all database interactions related to user session tokens.
 * - Intentionally uses the write connection even for reads (`findValidRememberToken`) to prevent replication lag from causing failed logins immediately after token creation.
 * Teaching notes:
 * - The conscious breaking of strict CQRS (reading from a write connection) is a pragmatic industry standard for critical auth flows to avoid race conditions with replica databases.
 */
class RememberTokenRepository extends BaseRepository
{
    public function execute(mixed $payload): mixed
    {
        return null;
    }

    public function saveRememberToken(int $userId, string $selector, string $hashedValidator, string $expiresAt): void
    {
        $stmt = $this->dbWrite->prepare("INSERT INTO user_tokens (user_id, type, selector, token_hash, expires_at) VALUES (?, 'remember_me', ?, ?, ?)");
        $stmt->execute([$userId, $selector, $hashedValidator, $expiresAt]);
    }

    public function findValidRememberToken(string $selector): ?array
    {
        // Even though this is a find method, we keep it here for simplicity of SRP over CQRS strictness, 
        // or we use the read connection. Since we extend BaseCommandRepository, we only have write connection.
        // It's acceptable to use write connection for session lookup to prevent replica lag issues on login.
        $stmt = $this->dbWrite->prepare("
            SELECT user_id, token_hash as hashed_validator 
            FROM user_tokens 
            WHERE selector = ? AND type = 'remember_me' AND expires_at > NOW()
        ");
        $stmt->execute([$selector]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function deleteRememberToken(string $selector): void
    {
        $stmt = $this->dbWrite->prepare("DELETE FROM user_tokens WHERE selector = ? AND type = 'remember_me'");
        $stmt->execute([$selector]);
    }

    public function deleteExpiredTokens(): int
    {
        $stmt = $this->dbWrite->prepare("DELETE FROM user_tokens WHERE expires_at < NOW() AND type = 'remember_me'");
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function deleteAllRememberTokensForUser(int $userId): void
    {
        $stmt = $this->dbWrite->prepare("DELETE FROM user_tokens WHERE user_id = ? AND type = 'remember_me'");
        $stmt->execute([$userId]);
    }
}

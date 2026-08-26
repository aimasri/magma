<?php

namespace Magma\interfaces\repositories;

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

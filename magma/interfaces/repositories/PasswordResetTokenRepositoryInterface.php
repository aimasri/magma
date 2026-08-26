<?php

namespace Magma\interfaces\repositories;

use Magma\domain\PasswordResetToken;

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

<?php

declare(strict_types=1);

namespace Magma\services;

use Magma\interfaces\cqrs\UserCommandInterface;
use Magma\repositories\PasswordResetTokenRepository;
use Magma\enums\PasswordResetStatus;
use Magma\database\TransactionManagerInterface;
use Magma\repositories\RememberTokenRepository;

/**
 * Title: Password Reset Completion Service
 *
 * Purpose:
 * - Handle the validation and final update phase of password recovery.
 *
 * Why / Why this design:
 * - Single Responsibility Principle (SRP): Extracted from a monolithic `PasswordResetService`. 
 *   It strictly focuses on state mutations and validation after a user clicks an email link.
 *
 * Teaching notes:
 * - This service forces all previous sessions (remember tokens) to be invalidated upon reset, 
 *   which is a critical security pattern for compromised accounts.
 */
class PasswordResetCompletionService
{
    public function __construct(
        private UserCommandInterface $userCommandRepository,
        private PasswordResetTokenRepository $userTokenRepository,
        private RememberTokenRepository $rememberTokenRepository,
        private TransactionManagerInterface $transactionManager
    ) {}

    /**
     * Validates if a given plaintext token exists and has not expired.
     *
     * Execution Flow:
     * 1. Re-hashes the plaintext token using SHA-256 (via the domain entity).
     * 2. Checks the database for a matching hashed token that is still within the TTL window.
     *
     * @param string $token
     * @return bool
     */
    public function validateToken(string $token): bool
    {
        $resetToken = \Magma\domain\PasswordResetToken::fromPlainText($token);
        return (bool) $this->userTokenRepository->findValidPasswordResetToken($resetToken->getHashedToken());
    }

    /**
     * Executes the password reset mutation workflow.
     *
     * Execution Flow:
     * 1. Re-hashes the plaintext token.
     * 2. Hashes the new user-provided password using bcrypt/argon2.
     * 3. Opens a transaction.
     * 4. Validates the token exists in the DB and retrieves the associated user ID.
     * 5. Updates the user's password.
     * 6. Deletes the used reset token (preventing replay attacks).
     * 7. Deletes ALL remember-me tokens for the user (logging out all other devices).
     *
     * @param string $token
     * @param string $newPassword
     * @return PasswordResetStatus
     */
    public function completeReset(string $token, string $newPassword): PasswordResetStatus
    {
        $resetToken = \Magma\domain\PasswordResetToken::fromPlainText($token);
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        
        try {
            return $this->transactionManager->transactional(function () use ($hashed, $resetToken) {
                $record = $this->userTokenRepository->findValidPasswordResetToken($resetToken->getHashedToken());

                if (!$record) {
                    return PasswordResetStatus::INVALID_TOKEN;
                }

                $this->userCommandRepository->updatePassword($record['user_id'], $hashed);
                $this->userTokenRepository->deletePasswordResetToken($resetToken->getHashedToken());
                $this->rememberTokenRepository->deleteAllRememberTokensForUser($record['user_id']);
                return PasswordResetStatus::SUCCESS;
            });
        } catch (\Exception $e) {
            error_log("Failed to complete password reset: " . $e->getMessage());
            return PasswordResetStatus::SYSTEM_ERROR;
        }
    }
}

<?php

declare(strict_types=1);

namespace Magma\services;

use Magma\interfaces\cqrs\UserCommandInterface;
use Magma\interfaces\cqrs\UserQueryInterface;
use Magma\repositories\PasswordResetTokenRepository;
use Magma\queue\QueueInterface;
use Magma\routing\UrlGenerator;
use Magma\enums\PasswordResetStatus;
use Magma\database\TransactionManagerInterface;
use Magma\repositories\RememberTokenRepository;

/**
 * Password Reset Domain Service
 *
 * Purpose:
 * - Coordinate the entire password recovery lifecycle (token creation, validation, and final update).
 * - Enforce TTLs (Time To Live) and one-time use semantics on recovery tokens.
 * - Delegate email dispatching to the background worker via the `QueueInterface`.
 *
 * Why / Why this design:
 * - Extracting this complex flow from the Controller adheres to SRP and enables unit testing. 
 *   Security-sensitive flows like password resets belong in a centralized domain service 
 *   to ensure consistent audit and logging.
 * - Relying on `QueueInterface` rather than `MailerService` directly ensures the HTTP 
 *   request does not block while communicating with external SMTP servers.
 *
 * Teaching notes:
 * - Password reset tokens are generated using cryptographically secure random bytes, and then 
 *   hashed via SHA-256 before being persisted. This critical design pattern ensures that an 
 *   attacker with read-only database access cannot hijack active reset links.
 */
class PasswordResetService
{
    protected UserCommandInterface $userCommandRepository;
    protected UserQueryInterface $userQueryRepository;
    protected PasswordResetTokenRepository $userTokenRepository;
    protected RememberTokenRepository $rememberTokenRepository;
    protected QueueInterface $queue;
    protected UrlGenerator $urlGenerator;
    protected TransactionManagerInterface $transactionManager;

    public function __construct(
        UserCommandInterface $userCommandRepository,
        UserQueryInterface $userQueryRepository,
        PasswordResetTokenRepository $userTokenRepository,
        RememberTokenRepository $rememberTokenRepository,
        QueueInterface $queue,
        UrlGenerator $urlGenerator,
        TransactionManagerInterface $transactionManager
    ) {
        $this->userCommandRepository = $userCommandRepository;
        $this->userQueryRepository = $userQueryRepository;
        $this->userTokenRepository = $userTokenRepository;
        $this->rememberTokenRepository = $rememberTokenRepository;
        $this->queue = $queue;
        $this->urlGenerator = $urlGenerator;
        $this->transactionManager = $transactionManager;
    }

    /**
     * Initiates the password recovery lifecycle.
     * 
     * Execution Flow:
     * 1. Query the repository to verify the user exists. Return silently if they do not.
     * 2. Delegate secure token generation to the PasswordResetToken domain entity.
     * 3. Persist the newly generated token to the database.
     * 4. Construct an absolute URL containing the plain-text token.
     * 5. Construct an async job payload and push it to the Redis queue.
     * 
     * Logic behind the logic:
     * - We return `PasswordResetStatus::USER_NOT_FOUND` when a user isn't found instead of throwing an error. 
     *   This allows the Controller to safely swallow the failure and prevent User Enumeration attacks, 
     *   where an attacker could probe the API to determine which emails are registered on the platform.
     *
     * @param string $email The email address submitted by the user.
     * @return PasswordResetStatus
     */
    public function requestReset(string $email): PasswordResetStatus
    {
        $user = $this->userQueryRepository->findByEmail($email);
        if (!$user) {
            return PasswordResetStatus::USER_NOT_FOUND;
        }

        $token = \Magma\domain\PasswordResetToken::generate();

        try {
            $this->transactionManager->transactional(function () use ($user, $token) {
                $this->userTokenRepository->deleteAllPasswordResetTokensForUser($user->getId());
                $this->userTokenRepository->createPasswordResetToken($user->getId(), $token);
            });
        } catch (\Throwable $e) {
            error_log("Failed to create password reset token: " . $e->getMessage());
            return PasswordResetStatus::SYSTEM_ERROR;
        }

        $resetLink = $this->urlGenerator->generateAbsolute('/reset-password', ['token' => $token->getPlainTextToken()]);
        
        $payload = [
            'to_email' => $email,
            'to_name' => $user->getName(),
            'reset_link' => $resetLink
        ];

        try {
            $this->queue->push('emails', \Magma\jobs\SendPasswordResetEmailJob::class, $payload);
        } catch (\Throwable $e) {
            error_log("Failed to queue password reset email: " . $e->getMessage());
            return PasswordResetStatus::SYSTEM_ERROR;
        }
        
        return PasswordResetStatus::SUCCESS;
    }

    /**
     * Validates a recovery token.
     * 
     * Execution Flow:
     * 1. Hydrate the PasswordResetToken domain entity from the plain-text string.
     * 2. Query the repository for a valid, non-expired database record matching the computed hash.
     * 
     * Logic behind the logic:
     * - The service doesn't need to manually compute SHA-256 anymore. The entity guarantees 
     *   that the repository receives a consistently hashed value, preventing validation errors.
     */
    public function validateToken(string $token): bool
    {
        $resetToken = \Magma\domain\PasswordResetToken::fromPlainText($token);
        return (bool) $this->userTokenRepository->findValidPasswordResetToken($resetToken->getHashedToken());
    }

    /**
     * Finalizes the credential update.
     * 
     * Execution Flow:
     * 1. Hydrate the PasswordResetToken domain entity to compute the hash.
     * 2. Verify the token still exists and hasn't expired.
     * 3. Hash the new password securely.
     * 4. Wrap the user password update and token deletion in an atomic database transaction.
     * 
     * Logic behind the logic:
     * - Once validated, the new password is hashed using secure defaults, 
     *   the user record is updated, and the token is immediately purged 
     *   to prevent replay attacks.
     * 
     * @param string $token The plain-text token submitted by the user.
     * @param string $newPassword The new plaintext password to hash and store.
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
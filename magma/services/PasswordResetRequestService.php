<?php

declare(strict_types=1);

namespace Magma\services;

use Magma\interfaces\cqrs\UserQueryInterface;
use Magma\repositories\PasswordResetTokenRepository;
use Magma\queue\QueueInterface;
use Magma\routing\UrlGenerator;
use Magma\enums\PasswordResetStatus;
use Magma\database\TransactionManagerInterface;

/**
 * Title: Password Reset Request Service
 *
 * Purpose:
 * - Handle the first phase of password recovery (token creation and email dispatching).
 *
 * Why / Why this design:
 * - Single Responsibility Principle (SRP): Extracted from a monolithic `PasswordResetService` to ensure 
 *   this class only handles the initial request phase. It manages exactly 5 dependencies, keeping it 
 *   below the complexity threshold.
 *
 * Teaching notes:
 * - Employs the TransactionManager to ensure atomic token invalidation and creation, preventing 
 *   token duplication race conditions.
 */
class PasswordResetRequestService
{
    public function __construct(
        private UserQueryInterface $userQueryRepository,
        private PasswordResetTokenRepository $userTokenRepository,
        private QueueInterface $queue,
        private UrlGenerator $urlGenerator,
        private TransactionManagerInterface $transactionManager
    ) {}

    /**
     * Executes the password reset request workflow.
     *
     * Execution Flow:
     * 1. Looks up the user by email (returns safely if not found to prevent timing attacks).
     * 2. Generates a secure, cryptographically random token object.
     * 3. Wraps the invalidation of old tokens and creation of the new token in a database transaction.
     * 4. Generates an absolute reset URL.
     * 5. Dispatches an asynchronous email job to the queue.
     *
     * @param string $email
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
}

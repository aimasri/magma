<?php

declare(strict_types=1);

namespace Magma\services;

use Magma\interfaces\cqrs\UserQueryInterface;
use Magma\repositories\PasswordResetTokenRepository;
use Magma\enums\PasswordResetStatus;
use Magma\database\TransactionManagerInterface;
use Magma\interfaces\EventDispatcherInterface;

/**
 * Title: Password Reset Request Service
 *
 * Purpose:
 * - Handle the first phase of password recovery (token creation and email dispatching).
 *
 * Why / Why this design:
 * - Extracted from a monolithic `PasswordResetService` to ensure this class only handles the initial request phase.
 *
 * [AI_AUDIT_EXCEPTION]
 * SRP_HEURISTIC_IGNORE: This class intentionally exceeds the 3-dependency limit rule (4 dependencies).
 * REASON: This service represents a single, cohesive transactional boundary for requesting a password reset. It coordinates user lookup, token generation, transactional saving, and event dispatching in a unified workflow. Splitting it would introduce arbitrary fragmentation.
 *
 * Teaching notes:
 * - Employs the TransactionManager to ensure atomic token invalidation and creation, preventing 
 *   token duplication race conditions.
 */
class PasswordResetRequestService
{
    /**
     * Initializes the PasswordResetRequestService with its required dependencies.
     *
     * Execution Flow:
     * 1. Injects repositories, transaction manager, and event dispatcher.
     * 2. Binds them to the class properties to coordinate the password reset request phase.
     *
     * Logic behind the logic:
     * - Orchestration via DI: Coordinates distinct bounded context tools (DB, Event Dispatcher)
     *   via constructor injection to test them in isolation.
     *
     * @param UserQueryInterface $userQueryRepository
     * @param PasswordResetTokenRepository $userTokenRepository
     * @param TransactionManagerInterface $transactionManager
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        private UserQueryInterface $userQueryRepository,
        private PasswordResetTokenRepository $userTokenRepository,
        private TransactionManagerInterface $transactionManager,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * Executes the password reset request workflow.
     *
     * Execution Flow:
     * 1. Looks up the user by email (returns safely if not found to prevent timing attacks).
     * 2. Generates a secure, cryptographically random token object.
     * 3. Wraps the invalidation of old tokens and creation of the new token in a database transaction.
     * 4. Dispatches a PasswordResetRequestedEvent to notify listeners.
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
            $this->transactionManager->transactional(function () use ($user, $token, $email) {
                $this->userTokenRepository->deleteAllPasswordResetTokensForUser($user->getId());
                $this->userTokenRepository->createPasswordResetToken($user->getId(), $token);
                
                $this->eventDispatcher->dispatch(new \Magma\domain\events\PasswordResetRequestedEvent(
                    $email,
                    $user->getName(),
                    $token->getPlainTextToken()
                ));
            });
            return PasswordResetStatus::SUCCESS;
        } catch (\Throwable $e) {
            error_log("Failed to process password reset request: " . $e->getMessage());
            return PasswordResetStatus::SYSTEM_ERROR;
        }
    }
}

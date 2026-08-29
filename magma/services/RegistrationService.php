<?php

declare(strict_types=1);

namespace Magma\services;

use Magma\interfaces\cqrs\UserCommandInterface;
use Magma\validation\ValidationException;
use Magma\interfaces\EventDispatcherInterface;
use Magma\domain\events\UserRegisteredEvent;
use Magma\database\TransactionManagerInterface;

/**
 * Title: User Registration Service
 *
 * Purpose:
 * - Centralize the business logic for creating new user accounts.
 * - Perform business rule validation (e.g., email uniqueness checks).
 * - Securely hash passwords before database persistence.
 *
 * Why / Why this design:
 * - Extracting this domain logic from the `AuthController` strictly adheres to the Single 
 *   Responsibility Principle (SRP). It decouples the act of "registering a user" from the 
 *   HTTP request lifecycle.
 *
 * Teaching notes:
 * - If user registration needs to dispatch welcome emails or create default tenant data, those operations should be handled via Domain Events rather than bloating this class further.
 */
class RegistrationService
{
    protected UserCommandInterface $userCommandRepository;
    protected EventDispatcherInterface $dispatcher;
    protected TransactionManagerInterface $transactionManager;

    /**
     * Constructs the RegistrationService.
     *
     * Execution Flow:
     * 1. Injects the user command repository, event dispatcher, and transaction manager.
     * 2. Stores them in protected properties to handle user registration workflows.
     *
     * Logic behind the logic:
     * - Dependency Injection: Ensures that side effects (like database changes or events)
     *   can be safely mocked in tests by abstracting concrete implementations.
     *
     * @param UserCommandInterface $userCommandRepository
     * @param EventDispatcherInterface $dispatcher
     * @param TransactionManagerInterface $transactionManager
     */
    public function __construct(
        UserCommandInterface $userCommandRepository,
        EventDispatcherInterface $dispatcher,
        TransactionManagerInterface $transactionManager
    ) {
        $this->userCommandRepository = $userCommandRepository;
        $this->dispatcher = $dispatcher;
        $this->transactionManager = $transactionManager;
    }

    /**
     * Registers a new user.
     * 
     * @param \Magma\dto\RegistrationDTO $dto The strictly typed registration DTO
     * @return int The ID of the newly created user.
     * @throws ValidationException If the email is already registered.
     */
    public function registerUser(\Magma\dto\RegistrationDTO $dto): int
    {
        $registration = new \Magma\domain\UserRegistration($dto->name, $dto->email, $dto->password);
        
        $result = $this->transactionManager->transactional(function () use ($registration) {
            try {
                $userId = $this->userCommandRepository->create($registration);
                
                if ($userId) {
                    $this->dispatcher->dispatch(new UserRegisteredEvent($registration, $userId));
                }
                
                return $userId;
            } catch (\Magma\domain\exceptions\DuplicateResourceException $e) {
                throw new ValidationException(['email' => 'This email is already registered.']);
            }
        });
        
        return is_scalar($result) ? (int) $result : 0;
    }
}

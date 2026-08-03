<?php

namespace Magma\services;

use Magma\models\UserRepositoryInterface;
use Magma\validation\ValidationException;
use Magma\interfaces\EventDispatcherInterface;
use Magma\domain\events\UserRegisteredEvent;
use Magma\database\TransactionManagerInterface;

/**
 * User Registration Service
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
 * - This service returns the newly created user record, allowing the Controller to 
 *   decide whether to automatically establish an authenticated session or not.
 */
class RegistrationService
{
    protected UserRepositoryInterface $userRepository;
    protected EventDispatcherInterface $dispatcher;
    protected TransactionManagerInterface $transactionManager;

    public function __construct(
        UserRepositoryInterface $userRepository,
        EventDispatcherInterface $dispatcher,
        TransactionManagerInterface $transactionManager
    ) {
        $this->userRepository = $userRepository;
        $this->dispatcher = $dispatcher;
        $this->transactionManager = $transactionManager;
    }

    /**
     * Registers a new user and automatically logs them in.
     * 
     * Execution Flow:
     * 1. Instantiate a UserRegistration domain entity to handle data extraction and hashing.
     * 2. Query the repository to ensure the email is not already registered.
     * 3. Persist the new user to the database and retrieve their generated ID.
     * 4. Fetch the complete user record and dispatch a UserRegisteredEvent.
     * 5. Return the newly created user record.
     * 
     * Logic behind the logic:
     * - Throwing a `ValidationException` when an email exists ensures that domain rules 
     *   are strictly enforced at the service level, while still allowing the controller to 
     *   catch the exception and gracefully redirect the user back to the form.
     * 
     * @param array $data The registration payload (must contain name, email, password)
     * @return \Magma\domain\AuthUser The newly created user entity.
     * @throws ValidationException If the email is already registered.
     */
    public function registerUser(array $data): \Magma\domain\AuthUser
    {
        $registration = new \Magma\domain\UserRegistration($data);
        
        if ($this->userRepository->findByEmail($registration->getEmail())) {
            throw new ValidationException(['email' => 'This email is already registered.']);
        }
        
        return $this->transactionManager->transactional(function () use ($registration) {
            $userId = $this->userRepository->create($registration);

            // Fetch the newly created user
            $user = $this->userRepository->findById($userId);
            if ($user) {
                // Dispatch domain event carrying the rich entity and record
                $this->dispatcher->dispatch(new UserRegisteredEvent($registration, $user));
            }
            
            return $user;
        });
    }
}

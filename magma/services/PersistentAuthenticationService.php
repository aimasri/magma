<?php

namespace Magma\services;

use Magma\interfaces\cqrs\UserQueryInterface;
use Magma\database\TransactionManagerInterface;
use Magma\services\RememberMeService;
use Magma\services\SessionAuthenticationService;
use Magma\services\AuthenticationResult;

/**
 * Title: Persistent Authentication Service
 *
 * Purpose:
 * - Manage "Remember Me" automated login logic.
 *
 * Why / Why this design:
 * - Solves a critical TOCTOU race condition by wrapping token validation, 
 *   consumption, and rotation into a single PostgreSQL SERIALIZABLE transaction.
 * - This service explicitly adheres to the SRP by extracting persistent cookie 
 *   orchestration away from standard credential authentication.
 */
class PersistentAuthenticationService
{
    protected RememberMeService $rememberMeService;
    protected SessionAuthenticationService $sessionAuth;
    protected UserQueryInterface $userRepository;
    protected TransactionManagerInterface $transactionManager;

    public function __construct(
        RememberMeService $rememberMeService,
        SessionAuthenticationService $sessionAuth,
        UserQueryInterface $userRepository,
        TransactionManagerInterface $transactionManager
    ) {
        $this->rememberMeService = $rememberMeService;
        $this->sessionAuth = $sessionAuth;
        $this->userRepository = $userRepository;
        $this->transactionManager = $transactionManager;
    }

    /**
     * Attempts to automatically log the user in using a persistent token.
     * 
     * Execution Flow:
     * 1. Opens a SERIALIZABLE database transaction to prevent concurrent auto-login race conditions.
     * 2. Validates the token and fetches the corresponding user.
     * 3. Logs the user in to the active session.
     * 4. Securely rotates the token to prevent replay attacks.
     * 5. Returns an AuthenticationResult with the new cookie attached.
     * 
     * @param string $token
     * @return AuthenticationResult
     */
    public function attemptAutoLogin(string $token): AuthenticationResult
    {
        /** @var AuthenticationResult $result */
        $result = $this->transactionManager->transactional(function () use ($token) {
            $userId = $this->rememberMeService->validateToken($token);
            if (!$userId) {
                return AuthenticationResult::failure()->clearCookie('remember_user');
            }

            $user = $this->userRepository->findById($userId);
            if (!$user) {
                return AuthenticationResult::failure()->clearCookie('remember_user');
            }

            $this->sessionAuth->login($user);

            $tokenData = $this->rememberMeService->rotateToken($token, $userId);
            
            return AuthenticationResult::success($user)
                ->withCookie('remember_user', $tokenData['token'], $tokenData['expiry']);
        });

        return $result;
    }

    /**
     * Issues a new persistent login token for the user.
     * 
     * @param int $userId
     * @return array{token: string, expiry: int}
     */
    public function issueToken(int $userId): array
    {
        return $this->rememberMeService->generateToken($userId);
    }
    
    /**
     * Destroys the persistent token and clears the cookie.
     * 
     * @param string $token
     * @return AuthenticationResult
     */
    public function logout(string $token): AuthenticationResult
    {
        $this->rememberMeService->invalidateToken($token);
        $this->sessionAuth->logout();

        return AuthenticationResult::failure()->clearCookie('remember_user');
    }
}

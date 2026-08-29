<?php

namespace Magma\services;

use Magma\domain\AuthUser;

/**
 * Title: Authentication Facade Service
 *
 * Purpose:
 * - Provides a unified orchestration layer for authentication controllers.
 * - Delegates actual business logic to strictly-focused, domain-specific services.
 *
 * Why / Why this design:
 * - Resolves the "God Object" anti-pattern. Previously, this class directly managed 
 *   credential verification, session states, and persistent cookies, violating SRP 
 *   and exceeding the 3-dependency limit. By acting as a Facade, it maintains 
 *   exactly 3 dependencies while cleanly separating the domain logic.
 * 
 * Teaching notes:
 * - Facade services like this should ONLY delegate and orchestrate. If you find yourself writing complex `if/else` business rules here, you are likely violating the boundaries. Move that logic into one of the specialized sub-services.
 */
class AuthenticationService
{
    protected CredentialAuthenticationService $credentialAuth;
    protected PersistentAuthenticationService $persistentAuth;
    protected SessionAuthenticationService $sessionAuth;

    /**
     * Initializes the service with its constituent authentication handlers.
     *
     * @param CredentialAuthenticationService $credentialAuth
     * @param PersistentAuthenticationService $persistentAuth
     * @param SessionAuthenticationService $sessionAuth
     */
    public function __construct(
        CredentialAuthenticationService $credentialAuth,
        PersistentAuthenticationService $persistentAuth,
        SessionAuthenticationService $sessionAuth
    ) {
        $this->credentialAuth = $credentialAuth;
        $this->persistentAuth = $persistentAuth;
        $this->sessionAuth = $sessionAuth;
    }

    /**
     * Delegates credential validation.
     */
    public function attempt(string $email, string $password, bool $remember = false): AuthenticationResult
    {
        $result = $this->credentialAuth->attempt($email, $password);

        if ($result->isSuccessful() && $remember) {
            $user = $result->getUser();
            if ($user !== null) {
                $tokenData = $this->persistentAuth->issueToken($user->getId());
                $result->withCookie('remember_user', $tokenData['token'], $tokenData['expiry']);
            }
        }

        return $result;
    }

    /**
     * Delegates persistent token auto-login.
     */
    public function attemptAutoLogin(string $token): AuthenticationResult
    {
        return $this->persistentAuth->attemptAutoLogin($token);
    }

    /**
     * Delegates session logout and token destruction.
     */
    public function logout(?string $token = null): AuthenticationResult
    {
        if ($token) {
            return $this->persistentAuth->logout($token);
        }
        
        $this->sessionAuth->logout();
        return AuthenticationResult::failure()->clearCookie('remember_user');
    }

    /**
     * Delegates issuing a short-lived SSO token.
     * 
     * Execution Flow:
     * 1. Forwards the request to the PersistentAuthenticationService.
     * 2. Returns the generated short-lived token and its expiry.
     * 
     * @return array{token: string, expiry: int}
     */
    public function issueSsoToken(int $userId): array
    {
        return $this->persistentAuth->issueSsoToken($userId);
    }

    /**
     * Delegates session login for an authenticated user.
     */
    public function login(AuthUser $authUser): void
    {
        $this->sessionAuth->login($authUser);
    }

    /**
     * Delegates fetching the currently authenticated user from the session.
     * 
     * @return AuthUser|null
     */
    public function getAuthenticatedUser(): ?AuthUser
    {
        return $this->sessionAuth->getAuthenticatedUser();
    }
}
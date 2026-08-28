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
 */
class AuthenticationService
{
    protected CredentialAuthenticationService $credentialAuth;
    protected PersistentAuthenticationService $persistentAuth;
    protected SessionAuthenticationService $sessionAuth;

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
}
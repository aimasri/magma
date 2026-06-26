<?php

namespace Magma\security;

use Magma\http\Session;

/**
 * Cross-Site Request Forgery Token Manager
 * 
 * Purpose:
 * - Centralizes CSRF token generation, validation, and HTML field creation.
 * - Decouples the TemplateEngine and Request from security logic.
 * 
 * Why / Why this design:
 * - By encapsulating CSRF logic here, we prevent the TemplateEngine from 
 *   acting as a pseudo-security service, enforcing the Single Responsibility 
 *   Principle (SRP).
 * 
 * Teaching notes:
 * - It implements a Token Grace Period to allow for smooth navigation when users 
 *   use the browser's "Back" button or open multiple tabs.
 */
class CsrfManager
{
    private Session $session;
    private const GRACE_PERIOD_COUNT = 2;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Get the active CSRF token, generating one if it doesn't exist.
     */
    public function getToken(): string
    {
        $csrfTokens = $this->session->get('_csrf_tokens', []);
        if (empty($csrfTokens)) {
            $csrfTokens[] = bin2hex(random_bytes(32));
            $this->session->set('_csrf_tokens', $csrfTokens);
        }
        return end($csrfTokens);
    }

    /**
     * Validate a submitted token against the valid tokens in the session grace period.
     */
    public function validateToken(string $submittedToken): bool
    {
        $csrfTokens = $this->session->get('_csrf_tokens', []);
        foreach ($csrfTokens as $validToken) {
            if (is_string($validToken) && !empty($submittedToken) && hash_equals($validToken, $submittedToken)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Rotate the token array, preserving only up to GRACE_PERIOD_COUNT tokens.
     */
    public function rotateToken(): void
    {
        $csrfTokens = $this->session->get('_csrf_tokens', []);
        $csrfTokens[] = bin2hex(random_bytes(32));
        if (count($csrfTokens) > self::GRACE_PERIOD_COUNT) {
            $csrfTokens = array_slice($csrfTokens, -self::GRACE_PERIOD_COUNT);
        }
        $this->session->set('_csrf_tokens', $csrfTokens);
    }

    /**
     * Generate the hidden HTML input field containing the CSRF token.
     */
    public function csrfField(): string
    {
        $token = $this->getToken();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

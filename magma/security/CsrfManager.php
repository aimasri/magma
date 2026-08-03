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
    private const GRACE_PERIOD_COUNT = 5;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Get the active CSRF token, generating one if it doesn't exist.
     *
     * Execution Flow:
     * 1. Retrieve the existing array of tokens from the session.
     * 2. If the array is empty, generate a new cryptographically secure 32-byte token.
     * 3. Store the new token array back into the session.
     * 4. Return the most recent token (the last element).
     *
     * Logic behind the logic:
     * - `random_bytes` ensures cryptographically strong pseudo-randomness, making brute-forcing impossible.
     */
    public function getToken(): string
    {
        $tokens = $this->session->get('_csrf_token', []);
        
        if (empty($tokens) || !is_array($tokens)) {
            $token = bin2hex(random_bytes(32));
            $this->session->set('_csrf_token', [$token]);
            return $token;
        }
        
        return end($tokens);
    }

    /**
     * Validate a submitted token against the valid tokens in the session grace period.
     *
     * Execution Flow:
     * 1. Retrieve the list of active tokens from the session.
     * 2. Iterate through the tokens to find a match with the submitted token.
     * 3. Use `hash_equals` to perform the comparison.
     * 4. Return true if a match is found, otherwise false.
     *
     * Logic behind the logic:
     * - `hash_equals` mitigates timing attacks by ensuring that the comparison time is constant,
     *   regardless of whether the tokens match completely or fail early.
     */
    public function validateToken(string $submittedToken): bool
    {
        $validTokens = $this->session->get('_csrf_token', []);
        
        if (!is_array($validTokens) || empty($submittedToken)) {
            return false;
        }

        foreach ($validTokens as $validToken) {
            if (is_string($validToken) && hash_equals($validToken, $submittedToken)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Rotate the token array, preserving only up to GRACE_PERIOD_COUNT tokens.
     *
     * Execution Flow:
     * 1. Retrieve the existing tokens from the session.
     * 2. Generate and append a new token.
     * 3. If the array size exceeds the grace period, slice off the oldest tokens.
     * 4. Save the truncated array back to the session.
     *
     * Logic behind the logic:
     * - Limiting the array size prevents session bloat, while still providing UX grace for 
     *   multi-tab forms.
     */
    public function regenerateToken(): void
    {
        $tokens = $this->session->get('_csrf_token', []);
        
        if (!is_array($tokens)) {
            $tokens = [];
        }
        
        $tokens[] = bin2hex(random_bytes(32));
        
        if (count($tokens) > self::GRACE_PERIOD_COUNT) {
            $tokens = array_slice($tokens, -self::GRACE_PERIOD_COUNT);
        }
        
        $this->session->set('_csrf_token', $tokens);
    }

    /**
     * Generate the hidden HTML input field containing the CSRF token.
     *
     * Execution Flow:
     * 1. Retrieves the active token.
     * 2. Returns an HTML hidden input string securely escaping the token value.
     *
     * Logic behind the logic:
     * - Pre-rendering the field allows templates to blindly drop in the token without 
     *   worrying about HTML escaping intricacies.
     */
    public function csrfField(): string
    {
        $token = $this->getToken();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

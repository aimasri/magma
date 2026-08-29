<?php

declare(strict_types=1);

namespace Magma\security;

use Magma\http\SessionInterface;

/**
 * Title: Cross-Site Request Forgery (CSRF) Token Manager
 * 
 * Purpose:
 * - Centralizes CSRF token generation, validation, rotation, and HTML field rendering.
 * - Enforces the Synchronizer Token Pattern across both state-mutating HTML forms and AJAX endpoints.
 * 
 * Why / Why this design:
 * - Decoupling & Interface Inversion: Injects `SessionInterface` rather than a concrete session class, allowing CSRF testing without touching superglobals.
 * - Token Grace Period: Retains a sliding window of recent tokens (up to 5) to prevent race conditions when users operate across multiple browser tabs.
 * 
 * Teaching notes:
 * - Uses `hash_equals()` for constant-time string comparisons to prevent timing side-channel attacks.
 */
class CsrfManager
{
    private SessionInterface $session;
    private const GRACE_PERIOD_COUNT = 5;

    /**
     * Initializes the CSRF manager with a session interface.
     *
     * @param SessionInterface $session
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * Retrieves the active CSRF token, generating one if none exists.
     *
     * Execution Flow:
     * 1. Retrieve the existing token array from session storage.
     * 2. If missing or empty, generate a cryptographically secure 32-byte hexadecimal token.
     * 3. Save the new token list to the session.
     * 4. Return the latest active token.
     *
     * Logic behind the logic:
     * - `random_bytes(32)` provides 256 bits of cryptographic entropy, preventing brute-force token prediction.
     *
     * @return string
     */
    public function getToken(): string
    {
        $tokens = $this->session->get('_csrf_token', []);

        if (empty($tokens) || !is_array($tokens)) {
            $token = bin2hex(random_bytes(32));
            $this->session->set('_csrf_token', [$token]);
            return $token;
        }

        $last = end($tokens);
        return is_scalar($last) ? (string)$last : '';
    }

    /**
     * Validates a submitted token against active tokens in the session grace period.
     *
     * Execution Flow:
     * 1. Retrieve the token array from session storage.
     * 2. Perform constant-time string comparisons (`hash_equals`) against each active token.
     * 3. Return true if a valid match is found, false otherwise.
     *
     * Logic behind the logic:
     * - Constant-time comparison eliminates timing side-channel information leakage.
     *
     * @param string $submittedToken
     * @return bool
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
     * Consumes a specific token from the session grace list.
     *
     * @param string $token
     * @return void
     */
    public function consumeToken(string $token): void
    {
        $tokens = $this->session->get('_csrf_token', []);
        if (is_array($tokens)) {
            $tokens = array_filter($tokens, fn($t) => is_string($t) && !hash_equals($t, $token));
            $this->session->set('_csrf_token', array_values($tokens));
        }
    }

    /**
     * Rotates the token array, appending a new token and trimming older entries past the grace window.
     *
     * @return void
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
     * Generates a hidden HTML input field containing the active CSRF token.
     *
     * @return string
     */
    public function csrfField(): string
    {
        $token = $this->getToken();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

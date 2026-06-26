<?php

namespace Magma\domain;

/**
 * Password Reset Token Domain Entity
 *
 * Purpose:
 * - Encapsulate the creation, hashing, and expiration logic for password reset tokens.
 *
 * Why / Why this design:
 * - Removing cryptography and time manipulation from the service keeps it focused 
 *   solely on coordinating the database and the email queue.
 *
 * Teaching notes:
 * - Security is inherently bound to this token, so it must own its own generation 
 *   using cryptographically secure random bytes.
 */
class PasswordResetToken
{
    private string $plainTextToken;
    private string $hashedToken;
    private string $expiresAt;

    /**
     * Constructs a new PasswordResetToken instance internally.
     *
     * @param string $plainTextToken The raw token string.
     * @param string $hashedToken The SHA-256 hashed token.
     * @param string $expiresAt The expiration timestamp (Y-m-d H:i:s).
     */
    private function __construct(string $plainTextToken, string $hashedToken, string $expiresAt)
    {
        $this->plainTextToken = $plainTextToken;
        $this->hashedToken = $hashedToken;
        $this->expiresAt = $expiresAt;
    }

    /**
     * Generates a brand new, cryptographically secure token.
     *
     * Execution Flow:
     * 1. Uses `random_bytes(32)` to generate a high-entropy string.
     * 2. Hashes the string using SHA-256 for safe database storage.
     * 3. Calculates the expiration time by adding 3600 seconds (1 hour) to the current time.
     * 4. Returns a new instance encapsulating these values.
     *
     * Logic behind the logic:
     * - We hash the token before storing it to prevent an attacker with read-only 
     *   database access from hijacking active password reset sessions.
     *
     * @return self
     */
    public static function generate(): self
    {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 Hour TTL

        return new self($token, $hashedToken, $expiresAt);
    }

    /**
     * Hydrates a token object from user input to calculate its hash for verification.
     *
     * Execution Flow:
     * 1. Accepts the plain-text token (usually from a query string).
     * 2. Computes the SHA-256 hash to allow for safe database lookups.
     *
     * Logic behind the logic:
     * - Re-computing the hash on the fly allows the repository to query by the 
     *   `token_hash` column without ever exposing the raw plain-text token.
     *
     * @param string $plainTextToken The token from the HTTP request.
     * @return self
     */
    public static function fromPlainText(string $plainTextToken): self
    {
        $hashedToken = hash('sha256', $plainTextToken);
        return new self($plainTextToken, $hashedToken, '');
    }

    /**
     * Retrieves the plain-text token (used for generating email links).
     *
     * @return string
     */
    public function getPlainTextToken(): string
    {
        return $this->plainTextToken;
    }

    /**
     * Retrieves the hashed token (used for database queries).
     *
     * @return string
     */
    public function getHashedToken(): string
    {
        return $this->hashedToken;
    }

    /**
     * Retrieves the expiration timestamp (used for database insertion).
     *
     * @return string
     */
    public function getExpiresAt(): string
    {
        return $this->expiresAt;
    }
}

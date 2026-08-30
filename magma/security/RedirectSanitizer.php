<?php

declare(strict_types=1);

namespace Magma\security;

/**
 * Title: RFC 3986 Open-Redirect Security Sanitizer
 *
 * Purpose:
 * - Validates and sanitizes redirection target URIs to protect against Open Redirect vulnerabilities (CWE-601).
 * - Enforces RFC 3986 path-absolute URI compliance, ensuring only safe, local relative paths are permitted.
 * - Explicitly rejects protocol-relative exploits (`//evil.com`), backslash normalization tricks (`/\evil.com`, `\\evil.com`), external schemes (`http:`, `https:`, `javascript:`, `data:`), CRLF header injection, and recursive authentication loops (`/login`, `/logout`).
 *
 * Why / Why this design:
 * - Single Responsibility Principle (SRP): Isolates URL validation and sanitization mechanics from HTTP controllers and middleware layers into a dedicated security utility.
 * - Pure Stateless Functions: Operates deterministically without shared state or external I/O, maximizing execution speed and enabling comprehensive unit test coverage.
 *
 * Teaching notes:
 * - Modern web browsers normalize protocol-relative URLs (`//example.com`) and backslash prefixes (`/\example.com`) to external domain authorities. Validating path-absolute constraints (`/` prefix without subsequent slashes or backslashes) mathematically closes this attack vector.
 */
class RedirectSanitizer
{
    /** @var array<int, string> Authentication paths excluded to prevent redirect loops and UX traps */
    private const EXCLUDED_AUTH_PATHS = [
        '/login',
        '/logout',
        '/register',
        '/forgot-password',
        '/reset-password',
    ];

    /**
     * Maximum allowed length for a redirection target URI.
     */
    private const MAX_URI_LENGTH = 2048;

    /**
     * Validates whether a candidate URI is a safe, local relative destination.
     *
     * Execution Flow:
     * 1. Validates string length and rejects empty, non-string, or oversized inputs.
     * 2. Rejects control characters, newlines, carriage returns, and null bytes (preventing CRLF injection).
     * 3. Enforces that the URI begins with a single forward slash `/`.
     * 4. Rejects protocol-relative prefixes (`//`, `///`) and backslash prefixes (`/\`, `\\`, `\/`).
     * 5. Verifies absence of backslashes before query or fragment delimiters.
     * 6. Validates URI syntax via `parse_url()`: asserts scheme, host, port, user, and pass components are completely empty.
     * 7. Extracts the path component and ensures it does not match excluded recursive authentication routes.
     * 8. Returns true if all security constraints pass, false otherwise.
     *
     * Logic behind the logic:
     * - Checking both raw string prefixes and `parse_url()` components provides defense-in-depth against parser differential vulnerabilities where `parse_url` might handle malformed schemes differently than browser URL parsers.
     *
     * @param string $url The candidate redirection URI.
     * @return bool True if the URI is a valid and safe local relative destination; false otherwise.
     */
    public static function isValid(string $url): bool
    {
        $trimmed = trim($url);

        if ($trimmed === '' || strlen($trimmed) > self::MAX_URI_LENGTH) {
            return false;
        }

        // 1. Guard against control characters, CRLF injection, and null bytes
        if (preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1) {
            return false;
        }

        // 2. Must begin with a single forward slash (RFC 3986 path-absolute reference)
        if (!str_starts_with($trimmed, '/')) {
            return false;
        }

        // 3. Reject protocol-relative URLs (//evil.com) and backslash normalizations (/\evil.com, \evil.com)
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/\\') || str_starts_with($trimmed, '\\')) {
            return false;
        }

        // 4. Reject backslashes anywhere in the path component before query string or fragment
        $pathOnly = (string) strtok($trimmed, '?#');
        if (str_contains($pathOnly, '\\')) {
            return false;
        }

        // 5. Reject explicit URI schemes (e.g., javascript:, data:, http:, https:)
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $trimmed) === 1) {
            return false;
        }

        // 6. Strict RFC 3986 parsing validation
        $parsed = parse_url($trimmed);
        if ($parsed === false) {
            return false;
        }

        // Must not declare an external scheme, authority, host, port, or userinfo
        if (isset($parsed['scheme']) || isset($parsed['host']) || isset($parsed['port']) || isset($parsed['user']) || isset($parsed['pass'])) {
            return false;
        }

        // Path must be present and start with a single slash
        $path = $parsed['path'] ?? '';
        if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return false;
        }

        // 7. Guard against recursive authentication redirect loops
        $normalizedPath = rtrim(strtolower($path), '/');
        if ($normalizedPath === '') {
            $normalizedPath = '/';
        }

        if (in_array($normalizedPath, self::EXCLUDED_AUTH_PATHS, true)) {
            return false;
        }

        return true;
    }

    /**
     * Sanitizes a candidate URL, returning the validated local relative URI or a fallback.
     *
     * Execution Flow:
     * 1. Verifies if candidate URL is a non-empty string and passes `isValid()`.
     * 2. If valid, returns the trimmed URI.
     * 3. If invalid, returns the provided fallback value.
     *
     * Logic behind the logic:
     * - Provides a convenient one-step sanitation gate for controllers and middleware handling redirect targets.
     *
     * @param string|null $url The candidate redirect URL to sanitize.
     * @param string|null $fallback The fallback URL if validation fails (defaults to null).
     * @return string|null The sanitized URL or the fallback.
     */
    public static function sanitize(?string $url, ?string $fallback = null): ?string
    {
        if ($url !== null && self::isValid($url)) {
            return trim($url);
        }

        return $fallback;
    }

    /**
     * Sanitizes a candidate URL, guaranteeing a non-null string return using the provided fallback.
     *
     * @param string|null $url The candidate redirect URL to sanitize.
     * @param string $fallback The guaranteed non-empty fallback URL.
     * @return string The sanitized URL or the non-null fallback.
     */
    public static function sanitizeOrFallback(?string $url, string $fallback): string
    {
        $sanitized = self::sanitize($url, $fallback);
        return $sanitized !== null && $sanitized !== '' ? $sanitized : $fallback;
    }
}

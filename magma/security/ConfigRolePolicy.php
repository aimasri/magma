<?php

declare(strict_types=1);

namespace Magma\security;

use Magma\config\ConfigInterface;
use Magma\contracts\RolePolicyInterface;

/**
 * Title: Configuration-Driven Role Policy
 *
 * Purpose:
 * - Implements `RolePolicyInterface` using application configuration settings (`ROLE_DASHBOARD_MAP`, `ROLE_TIMEOUT_MAP`, `AUTH_DEFAULT_REDIRECT`, `SESSION_LIFETIME`).
 * - Provides sensible defaults while delegating role definitions and landing destinations entirely to application configuration.
 *
 * Why / Why this design:
 * - Single Responsibility Principle (SRP): Solely responsible for mapping role strings to destinations and timeouts using configuration state.
 * - Zero Domain Coupling: Contains zero hardcoded business role names in source code. Any role string defined by downstream applications can be mapped.
 * - Dependency Inversion Principle (DIP): Injects `ConfigInterface` via constructor injection (exactly 1 dependency).
 *
 * Teaching notes:
 * - Downstream applications can configure mappings in `.env` or application config via arrays or comma-delimited key:value pairs (e.g. `ROLE_DASHBOARD_MAP="admin:/admin,staff:/admin"`), allowing clean environment-specific overrides without code changes.
 */
class ConfigRolePolicy implements RolePolicyInterface
{
    /**
     * Initializes the configuration-driven role policy with the application configuration service.
     *
     * @param ConfigInterface $config The application configuration repository.
     */
    public function __construct(
        private readonly ConfigInterface $config
    ) {}

    /**
     * Resolves the default post-authentication landing path for a given user role.
     *
     * Execution Flow:
     * 1. Retrieves optional `ROLE_DASHBOARD_MAP` from configuration (supporting array or comma-delimited string).
     * 2. If a role is provided and mapped, sanitizes the mapped URI via `RedirectSanitizer::sanitize()`.
     * 3. If valid and non-null, returns the sanitized path.
     * 4. Otherwise retrieves `AUTH_DEFAULT_REDIRECT` (defaulting to '/'), sanitizes via `RedirectSanitizer`, and returns it.
     *
     * Logic behind the logic:
     * - Sanitizing configured landing paths through `RedirectSanitizer` enforces RFC 3986 path-absolute constraints and prevents configuration errors from introducing open-redirect vulnerabilities or redirect loops.
     *
     * @param string|null $role The user's role identifier.
     * @return string The sanitized path-absolute landing URI.
     */
    public function resolveDashboardPath(?string $role): string
    {
        if ($role !== null && $role !== '') {
            $map = $this->parseMap($this->config->get('ROLE_DASHBOARD_MAP'));
            if (isset($map[$role])) {
                $sanitized = RedirectSanitizer::sanitize($map[$role]);
                if ($sanitized !== null) {
                    return $sanitized;
                }
            }
        }

        $default = $this->config->get('AUTH_DEFAULT_REDIRECT', '/');
        $defaultStr = is_string($default) ? $default : '/';

        return RedirectSanitizer::sanitize($defaultStr) ?? '/';
    }

    /**
     * Resolves the maximum allowed session inactivity duration in seconds for a given user role.
     *
     * Execution Flow:
     * 1. Retrieves optional `ROLE_TIMEOUT_MAP` from configuration (supporting array or comma-delimited string).
     * 2. If a role is provided and mapped to a positive integer (`> 0`), returns the mapped timeout.
     * 3. Otherwise reads `SESSION_LIFETIME` (defaulting to 1800 seconds).
     * 4. Returns the validated timeout, enforcing a minimum lower bound of 60 seconds.
     *
     * Logic behind the logic:
     * - Enforcing a 60-second floor prevents accidental zero or negative timeouts from locking all users out of the system.
     *
     * @param string|null $role The user's role identifier.
     * @return int The maximum inactivity window in seconds.
     */
    public function resolveSessionTimeout(?string $role): int
    {
        if ($role !== null && $role !== '') {
            $map = $this->parseMap($this->config->get('ROLE_TIMEOUT_MAP'));
            if (isset($map[$role])) {
                $val = $map[$role];
                if (is_numeric($val) && (int) $val > 0) {
                    return (int) $val;
                }
            }
        }

        $lifetime = $this->config->get('SESSION_LIFETIME', 1800);
        $lifetimeInt = is_numeric($lifetime) ? (int) $lifetime : 1800;

        return max(60, $lifetimeInt);
    }

    /**
     * Parses configuration map from either an associative array or a comma-delimited key:value string.
     *
     * Execution Flow:
     * 1. If input is an array, extracts string key-value pairs.
     * 2. If input is a string, splits on commas and parses colon-delimited key-value tokens.
     * 3. Trims whitespace around keys and values.
     * 4. Returns normalized associative array.
     *
     * @param mixed $raw The raw configuration value.
     * @return array<string, string> Normalized key-value map.
     */
    private function parseMap(mixed $raw): array
    {
        if (is_array($raw)) {
            $map = [];
            foreach ($raw as $k => $v) {
                if (is_scalar($v)) {
                    $map[(string) $k] = (string) $v;
                }
            }
            return $map;
        }

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $map = [];
        $pairs = explode(',', $raw);
        foreach ($pairs as $pair) {
            $trimmed = trim($pair);
            if ($trimmed === '' || !str_contains($trimmed, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $trimmed, 2);
            $cleanKey = trim($key);
            $cleanVal = trim($value);
            if ($cleanKey !== '') {
                $map[$cleanKey] = $cleanVal;
            }
        }

        return $map;
    }
}

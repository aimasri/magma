<?php

declare(strict_types=1);

namespace Magma\logging;

/**
 * Title: Default Correlation ID Provider
 *
 * Purpose:
 * - Generates and caches a unique correlation ID for the current execution context.
 *
 * Why / Why this design:
 * - Singleton State: It caches the ID so that all log entries within a single HTTP request or queue job share the exact same trace ID.
 * - Simplicity: Uses `random_bytes` for fast, cryptographically secure ID generation without relying on external UUID libraries.
 *
 * Teaching notes:
 * - When running in a long-lived process (like RoadRunner or Swoole), this state must be reset at the beginning of every new request boundary, or else multiple requests will incorrectly share the same Correlation ID.
 */
class CorrelationIdProvider implements CorrelationIdProviderInterface
{
    private ?string $correlationId = null;

    /**
     * Retrieves the current correlation ID, generating one if it doesn't exist yet.
     *
     * Execution Flow:
     * 1. Check if the internal state property `$correlationId` is null.
     * 2. If null, generate a 32-character hexadecimal string using cryptographically secure `random_bytes`.
     * 3. Cache the generated string and return it.
     *
     * Logic behind the logic:
     * - Lazy Initialization: The ID is only generated the first time a log entry or trace is requested, preventing unnecessary entropy consumption or CPU cycles on requests that never log anything.
     *
     * @return string The unique trace identifier.
     */
    public function getCorrelationId(): string
    {
        if ($this->correlationId === null) {
            $this->correlationId = bin2hex(random_bytes(16));
        }
        return $this->correlationId;
    }
}

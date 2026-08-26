<?php

declare(strict_types=1);

namespace Magma\logging;

/**
 * Title: Native Error Log Implementation
 *
 * Purpose:
 * - A zero-dependency implementation of LoggerInterface that routes structured 
 *   logs to PHP's native `error_log()`.
 *
 * Why / Why this design:
 * - Maintains the framework's strict zero-dependency rule while providing a 
 *   robust observability adapter. Context arrays are JSON encoded for easy 
 *   parsing by external log aggregators (like Datadog or ELK).
 *
 * Teaching notes:
 * - When instantiating the Container, bind `LoggerInterface::class` to this 
 *   implementation so it is auto-wired globally.
 */
class NativeLogger implements LoggerInterface
{
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Internal formatting and dispatch method.
     * 
     * Execution Steps:
     * 1. Check if context is empty to avoid noisy empty JSON objects.
     * 2. Format string as `[LEVEL] Message {"context": "json"}`.
     * 3. Dispatch to `error_log()`.
     */
    private function log(string $level, string $message, array $context): void
    {
        $logEntry = sprintf(
            '[%s] %s %s',
            $level,
            $message,
            empty($context) ? '' : json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        error_log(trim($logEntry));
    }
}

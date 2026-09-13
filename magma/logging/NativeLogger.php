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
    private ?\Magma\security\TenantContext $tenantContext;
    private ?CorrelationIdProviderInterface $correlationIdProvider;

    public function __construct(
        ?\Magma\security\TenantContext $tenantContext = null,
        ?CorrelationIdProviderInterface $correlationIdProvider = null
    ) {
        $this->tenantContext = $tenantContext;
        $this->correlationIdProvider = $correlationIdProvider;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    /**
     * Internal formatting and dispatch method.
     * 
     * Execution Steps:
     * 1. Check if context is empty to avoid noisy empty JSON objects.
     * 2. Format string as `[LEVEL] Message {"context": "json"}`.
     * 3. Dispatch to `error_log()`.
     *
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context): void
    {
        $tenantId = ($this->tenantContext !== null && $this->tenantContext->hasTenantId()) ? $this->tenantContext->getTenantId() : 'System';
        $traceId = $this->correlationIdProvider !== null ? $this->correlationIdProvider->getCorrelationId() : 'N/A';
        
        // Sanitize CRLF to prevent log injection
        $safeMessage = str_replace(["\r", "\n"], ' ', $message);

        $logEntry = sprintf(
            '[%s] [Trace: %s] [Tenant: %s] %s %s',
            $level,
            $traceId,
            $tenantId,
            $safeMessage,
            empty($context) ? '' : json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        error_log(trim($logEntry));
    }
}

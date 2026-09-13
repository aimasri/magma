<?php

declare(strict_types=1);

namespace Magma\logging;

/**
 * Title: Correlation ID Provider Interface
 *
 * Purpose:
 * - Provides a consistent Request ID or Trace ID across the lifecycle of a request or job.
 *
 * Why / Why this design:
 * - Dependency Inversion: Allows the framework to swap out ID generation strategies (e.g. UUIDv4 vs fast random bytes) without changing the consumers (like NativeLogger).
 *
 * Teaching notes:
 * - This interface is critical for distributed tracing in microservices. In an enterprise system, this ID would be parsed from incoming HTTP headers (e.g. X-Correlation-ID) or generated if missing.
 */
interface CorrelationIdProviderInterface
{
    public function getCorrelationId(): string;
}

<?php

declare(strict_types=1);

namespace Magma\logging;

/**
 * Title: Correlation ID Provider Interface
 *
 * Purpose:
 * - Provides a consistent Request ID or Trace ID across the lifecycle of a request or job.
 */
interface CorrelationIdProviderInterface
{
    public function getCorrelationId(): string;
}

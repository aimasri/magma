<?php

declare(strict_types=1);

namespace Magma\logging;

/**
 * Title: Default Correlation ID Provider
 *
 * Purpose:
 * - Generates and caches a unique correlation ID for the current execution context.
 */
class CorrelationIdProvider implements CorrelationIdProviderInterface
{
    private ?string $correlationId = null;

    public function getCorrelationId(): string
    {
        if ($this->correlationId === null) {
            $this->correlationId = bin2hex(random_bytes(16));
        }
        return $this->correlationId;
    }
}

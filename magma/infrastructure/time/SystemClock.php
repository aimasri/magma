<?php

declare(strict_types=1);

namespace Magma\infrastructure\time;

use DateTimeImmutable;
use Magma\contracts\ClockInterface;

/**
 * Title: System Clock
 *
 * Purpose:
 * - Provides the actual, real-time system clock implementation for production environments.
 *
 * Why this design:
 * - Wraps PHP's native `DateTimeImmutable` behind an abstraction boundary, fulfilling the `ClockInterface` contract for dependency injection.
 *
 * Teaching notes:
 * - This class should remain completely stateless and rely entirely on the host OS for time resolution.
 */
class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}

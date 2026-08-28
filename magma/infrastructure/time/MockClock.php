<?php

declare(strict_types=1);

namespace Magma\infrastructure\time;

use DateTimeImmutable;
use Magma\contracts\ClockInterface;

/**
 * Title: Mock Clock
 *
 * Purpose:
 * - Provides a mutable, controllable time source for automated tests.
 *
 * Why this design:
 * - Implements the state pattern for time. By allowing test suites to arbitrarily "freeze", "advance", or "sleep" time without blocking the CPU, we can instantly test time-sensitive logic (like token expiration).
 *
 * Teaching notes:
 * - Never bind this implementation in the production dependency injection container. It is strictly for testing infrastructure.
 */
class MockClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable();
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function setTime(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    public function sleep(int $seconds): void
    {
        $this->now = $this->now->modify("+$seconds seconds");
    }

    public function advance(string $interval): void
    {
        $this->now = $this->now->modify($interval);
    }
}

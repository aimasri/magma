<?php

declare(strict_types=1);

namespace Magma\infrastructure\time;

use DateTimeImmutable;
use Magma\contracts\ClockInterface;

/**
 * Title: Mock Clock
 *
 * Purpose:
 * - Provides a mutable, deterministic time source implementing `ClockInterface` for automated tests and simulations.
 *
 * Why / Why this design:
 * - State Pattern for Time: Allows test suites to freeze, set, or advance wall-clock time instantaneously in memory without `sleep()` delays or external testing libraries, keeping `main` 100% dependency-free while giving `ClockInterface` a concrete test double.
 *
 * Teaching notes:
 * - Never bind `MockClock` in production service providers; bind `SystemClock` in production and override `ClockInterface` with `MockClock` strictly inside test harnesses.
 */
class MockClock implements ClockInterface
{
    private DateTimeImmutable $now;

    /**
     * Initializes the mock clock at a fixed point in time (or current wall-clock time if omitted).
     */
    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable();
    }

    /**
     * Returns the currently frozen point in time.
     */
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    /**
     * Replaces the frozen timestamp explicitly.
     */
    public function setTime(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    /**
     * Advances the frozen clock forward by a number of seconds without blocking execution.
     */
    public function sleep(int $seconds): void
    {
        $this->now = $this->now->modify("+{$seconds} seconds");
    }

    /**
     * Modifies the frozen clock using any relative `DateTimeImmutable::modify()` interval expression.
     */
    public function advance(string $interval): void
    {
        $this->now = $this->now->modify($interval);
    }
}

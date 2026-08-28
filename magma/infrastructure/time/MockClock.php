<?php

declare(strict_types=1);

namespace Magma\infrastructure\time;

use DateTimeImmutable;
use Magma\contracts\ClockInterface;

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

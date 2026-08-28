<?php

declare(strict_types=1);

namespace Magma\contracts;

use DateTimeImmutable;

/**
 * Title: Clock Interface
 *
 * Purpose:
 * - Provides an abstraction over system time retrieval.
 *
 * Why this design:
 * - Eliminates direct calls to `now()`, `time()`, or `new DateTime()`, which represent hidden global state. Mocking this interface allows for precise, deterministic time-travel testing in unit tests.
 *
 * Teaching notes:
 * - Inject this interface wherever time-dependent logic (like token expiry, scheduling, or auditing) is executed.
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}

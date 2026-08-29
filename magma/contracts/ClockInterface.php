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
    /**
     * Retrieves the current system time as an immutable DateTime object.
     *
     * Logic behind the logic:
     * - Enforces immutability by returning a DateTimeImmutable instance. This prevents accidental
     *   modification of the time object across different layers of the application, eliminating a
     *   common source of subtle bugs.
     *
     * @return DateTimeImmutable
     */
    public function now(): DateTimeImmutable;
}

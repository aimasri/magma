<?php

declare(strict_types=1);

namespace Magma\infrastructure\exceptions;

/**
 * Title: Boot Exception
 *
 * Purpose:
 * - Represents a fatal error that occurs during the kernel or CLI bootstrapping phase.
 *
 * Why this design:
 * - Differentiating boot errors from standard runtime exceptions allows the framework to fail fast and loudly before dispatching requests to controllers that would inevitably crash.
 *
 * Teaching notes:
 * - Only throw this during application initialization (e.g., missing essential environment variables or missing configuration files).
 */
class BootException extends InfrastructureException
{
}

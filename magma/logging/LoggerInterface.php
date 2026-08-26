<?php

declare(strict_types=1);

namespace Magma\logging;

/**
 * Title: System Logger Interface
 *
 * Purpose:
 * - Provides a standard contract for system-wide logging and observability.
 *
 * Why / Why this design:
 * - Based loosely on PSR-3, this interface allows the framework to swap logging 
 *   implementations (e.g., Native, Datadog, Sentry, Monolog) without altering 
 *   the consuming services.
 * - Enforces Dependency Inversion Principle (DIP).
 *
 * Teaching notes:
 * - Inject this interface via constructor injection into any service that needs 
 *   to record observable events, errors, or telemetry.
 */
interface LoggerInterface
{
    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     */
    public function error(string $message, array $context = []): void;

    /**
     * Exceptional occurrences that are not errors (e.g., use of deprecated APIs,
     * poor use of an API, undesirable things that are not necessarily wrong).
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Interesting events.
     */
    public function info(string $message, array $context = []): void;

    /**
     * Detailed debug information.
     */
    public function debug(string $message, array $context = []): void;
}

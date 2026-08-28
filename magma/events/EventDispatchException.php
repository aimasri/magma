<?php

declare(strict_types=1);

namespace Magma\events;

use RuntimeException;
use Throwable;

/**
 * Title: Event Dispatch Exception
 *
 * Purpose:
 * - Aggregates multiple exceptions that occur during synchronous event dispatching.
 *
 * Why this design:
 * - Because an event might trigger multiple listeners, if one fails, we want the rest to execute. This exception collects all failures into a single throwable, preventing early termination of the listener queue while still reporting all errors.
 *
 * Teaching notes:
 * - Extract the nested exceptions via `getExceptions()` when catching this in the application kernel for detailed logging.
 */
class EventDispatchException extends RuntimeException
{
    /** @var Throwable[] */
    private array $exceptions;

    /**
     * @param Throwable[] $exceptions
     */
    public function __construct(string $message, array $exceptions = [])
    {
        parent::__construct($message);
        $this->exceptions = $exceptions;
    }

    /**
     * @return Throwable[]
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }
}

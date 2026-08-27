<?php

declare(strict_types=1);

namespace Magma\events;

use RuntimeException;
use Throwable;

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

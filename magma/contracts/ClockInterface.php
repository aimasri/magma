<?php

declare(strict_types=1);

namespace Magma\contracts;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}

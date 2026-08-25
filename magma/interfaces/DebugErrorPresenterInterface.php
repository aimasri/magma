<?php

declare(strict_types=1);

namespace Magma\interfaces;

use Throwable;
use Magma\http\RequestInterface;
use Magma\http\Response;

interface DebugErrorPresenterInterface
{
    public function present(Throwable $e, ?RequestInterface $request = null, int $statusCode = 500): Response;
}

<?php

declare(strict_types=1);

namespace Magma\interfaces;

use Magma\http\Response;

interface ResponseFactoryInterface
{
    public function create(string $content = '', int $status = 200, array $headers = []): Response;
}

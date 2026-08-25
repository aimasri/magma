<?php

declare(strict_types=1);

namespace Magma\interfaces;

use Magma\http\Response;

interface JsonErrorPresenterInterface
{
    public function present(
        int $code,
        string $message,
        ?\Throwable $throwable = null,
        bool $debug = false,
        ?array $errors = null
    ): Response;

    public function presentNotFound(string $message = 'Resource not found'): Response;

    public function presentUnauthorized(string $message = 'Unauthorized access'): Response;

    public function presentForbidden(string $message = 'Access forbidden'): Response;

    public function presentValidation(array $errors, string $message = 'Validation failed'): Response;

    public function presentServerError(
        string $message = 'Internal server error',
        ?\Throwable $throwable = null,
        bool $debug = false
    ): Response;
}

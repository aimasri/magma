<?php

declare(strict_types=1);

namespace Magma\validation;

use Magma\http\RequestInterface;

/**
 * Title: Validatable Request Interface
 * Purpose:
 * - Decouple routing parameter resolver from concrete FormRequest.
 * Why/Why this design:
 * - DIP (Dependency Inversion Principle): Depend on abstractions, not concretions.
 */
interface ValidatableRequestInterface
{
    public function validate(): bool;
    public function getErrors(): array;
    public function getRequest(): RequestInterface;
}

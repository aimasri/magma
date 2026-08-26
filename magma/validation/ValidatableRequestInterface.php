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
 * Teaching notes:
 * - Ensure any custom request classes implement this to be resolvable by the framework's dependency injection container.
 */
interface ValidatableRequestInterface
{
    public function validate(): bool;
    
    /**
     * @return array<string, string>
     */
    public function getErrors(): array;
    
    public function getRequest(): RequestInterface;
}

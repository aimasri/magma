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
    /**
     * Validates the incoming request data against defined business rules.
     *
     * Execution Flow:
     * 1. Evaluates all configured rules for the given DTO or FormRequest.
     * 2. Populates an internal error array if validation fails.
     * 3. Returns a boolean indicating the final validation status.
     *
     * Logic behind the logic:
     * - Fail-Fast Principle: Provides a clear, unified boundary for resolving validation 
     *   before the controller action is even invoked, protecting domain layers from malformed data.
     *
     * @return bool True if validation passes, false otherwise.
     */
    public function validate(): bool;
    
    /**
     * Retrieves the list of validation errors, if any.
     *
     * Execution Flow:
     * 1. Returns a key-value array where keys are field names and values are error messages.
     *
     * Logic behind the logic:
     * - Standardized Error Reporting: Ensures that clients/controllers can uniformly 
     *   consume error states regardless of the underlying request object.
     *
     * @return array<string, string>
     */
    public function getErrors(): array;
    
    /**
     * Retrieves the underlying HTTP request object.
     *
     * Execution Flow:
     * 1. Returns the injected RequestInterface that this validatable object wraps or depends on.
     *
     * Logic behind the logic:
     * - Delegation: Allows consumers of the validatable request to still access 
     *   core HTTP request properties (like headers or raw bodies) without breaking abstraction.
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface;
}

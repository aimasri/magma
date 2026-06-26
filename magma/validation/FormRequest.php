<?php

namespace Magma\validation;

use Magma\http\Request;

/**
 * Dedicated Request Validation Contract
 *
 * Purpose:
 * - Encapsulate validation rules for a specific HTTP endpoint (e.g., `LoginRequest`).
 * - Ensure controllers remain focused on flow control and delegation, not data sanitization.
 *
 * Why / Why this design:
 * - By enforcing validation through a dedicated Request object, we guarantee that the 
 *   controller never processes invalid or malicious input. It acts as an unbreakable 
 *   gateway before business logic executes.
 *
 * Teaching notes:
 * - Throwing a `ValidationException` is a critical pattern. It interrupts the standard execution 
 *   flow, allowing a centralized error handler (like the `BaseController`) to catch it, flash 
 *   the errors to the session, and automatically redirect the user back to the form.
 */
abstract class FormRequest
{
    protected Request $request;
    protected Validator $validator;

    public function __construct(Request $request, Validator $validator)
    {
        $this->request = $request;
        $this->validator = $validator;
    }

    /**
     * Defines the validation rules for this specific request.
     * Must return an array mapping field names to rule strings.
     */
    abstract public function rules(): array;

    /**
     * Executes the validation process.
     * 
     * It coordinates with the internal Validator engine. If constraints are 
     * violated, it throws a ValidationException containing the granular 
     * error details.
     */
    public function validate(): bool
    {
        if (!$this->validator->validate($this->request->request(), $this->rules())) {
            throw new ValidationException($this->validator->getErrors());
        }

        return true;
    }

    public function getErrors(): array
    {
        return $this->validator->getErrors();
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
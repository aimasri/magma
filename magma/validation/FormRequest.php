<?php

namespace Magma\validation;

use Magma\http\RequestInterface;

/**
 * Title: Dedicated Request Validation Contract
 *
 * Purpose:
 * - Encapsulate validation rules for a specific HTTP endpoint (e.g., `LoginRequest`).
 * - Ensure controllers remain focused on flow control and delegation, not data sanitization.
 *
 * Why/Why this design:
 * - By enforcing validation through a dedicated Request object, we guarantee that the 
 *   controller never processes invalid or malicious input. It acts as an unbreakable 
 *   gateway before business logic executes.
 *
 * Teaching notes:
 * - Throwing a `ValidationException` is a critical pattern. It interrupts the standard execution 
 *   flow, allowing a centralized error handler (like the `BaseController`) to catch it, flash 
 *   the errors to the session, and automatically redirect the user back to the form.
 */
abstract class FormRequest implements ValidatableRequestInterface
{
    protected RequestInterface $request;
    protected Validator $validator;

    /**
     * Initializes the FormRequest with the current HTTP request and Validator.
     *
     * @param RequestInterface $request The inbound HTTP request.
     * @param Validator $validator The validation engine.
     */
    public function __construct(RequestInterface $request, Validator $validator)
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
        $this->validator->validateOrFail($this->request->request(), $this->rules());
        return true;
    }

    /**
     * Retrieves the validation errors if validation has previously failed.
     *
     * @return array An array of error messages.
     */
    public function getErrors(): array
    {
        return $this->validator->getErrors();
    }

    /**
     * Retrieves the underlying HTTP Request instance.
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
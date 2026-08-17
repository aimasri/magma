<?php

namespace Magma\validation;

/**
 * Title: ValidationException — thrown when form input fails validation.
 *
 * Purpose:
 * - Carry a structured map of field => error messages so callers can render
 *   friendly feedback or convert errors into JSON for APIs.
 *
 * Why/Why this design:
 * - Employs Exception-Driven Control Flow. Instead of returning `false` from 
 *   a validator and manually checking it, throwing an exception automatically 
 *   halts execution, keeping the "happy path" clean in controllers.
 *
 * Teaching notes:
 * - A global exception handler can catch this specific exception type to 
 *   automatically redirect users back with flashed errors, eliminating boilerplate.
 */
class ValidationException extends \Exception
{
    private array $errors;

    /**
     * Initializes the exception with an array of structured error messages.
     *
     * @param array $errors Map of field names to their respective validation error messages.
     */
    public function __construct(array $errors)
    {
        parent::__construct("The given data failed validation.");
        $this->errors = $errors;
    }

    /**
     * Retrieves the associative array of validation errors.
     *
     * @return array Map of errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
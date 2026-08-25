<?php

declare(strict_types=1);

namespace Magma\interfaces;

use Magma\http\Response;

/**
 * Title: JSON Error Presenter Interface
 *
 * Purpose:
 * - Defines the contract for returning standardized JSON error responses
 * - Responsible for structuring API error messages consistently across the application
 *
 * Why / Why this design:
 * - Interface Segregation
 * - Ensures APIs maintain a uniform error schema (e.g., code, message, errors array) regardless of the error source
 *
 * Teaching notes:
 * - Adheres strictly to API design best practices, preventing random response structures upon failures
 */
interface JsonErrorPresenterInterface
{
    /**
     * Presents a generic JSON error response.
     *
     * 1. Constructs a standard error payload containing code and message.
     * 2. Optionally attaches debug information if debug mode is active.
     * 3. Appends specific validation or domain errors if provided.
     *
     * @param int $code HTTP status code
     * @param string $message Error message
     * @param \Throwable|null $throwable Optional exception
     * @param bool $debug Whether to include debug data
     * @param array|null $errors Additional error details
     * @return Response
     */
    public function present(
        int $code,
        string $message,
        ?\Throwable $throwable = null,
        bool $debug = false,
        ?array $errors = null
    ): Response;

    /**
     * Presents a 404 Not Found JSON error response.
     *
     * 1. Wraps the generic present method with a 404 status.
     *
     * @param string $message
     * @return Response
     */
    public function presentNotFound(string $message = 'Resource not found'): Response;

    /**
     * Presents a 401 Unauthorized JSON error response.
     *
     * 1. Wraps the generic present method with a 401 status.
     *
     * @param string $message
     * @return Response
     */
    public function presentUnauthorized(string $message = 'Unauthorized access'): Response;

    /**
     * Presents a 403 Forbidden JSON error response.
     *
     * 1. Wraps the generic present method with a 403 status.
     *
     * @param string $message
     * @return Response
     */
    public function presentForbidden(string $message = 'Access forbidden'): Response;

    /**
     * Presents a 422 Validation Failed JSON error response.
     *
     * 1. Wraps the generic present method with a 422 status and attaches validation errors.
     *
     * @param array $errors
     * @param string $message
     * @return Response
     */
    public function presentValidation(array $errors, string $message = 'Validation failed'): Response;

    /**
     * Presents a 500 Internal Server Error JSON response.
     *
     * 1. Wraps the generic present method with a 500 status.
     * 2. Handles internal server exceptions securely based on debug mode.
     *
     * @param string $message
     * @param \Throwable|null $throwable
     * @param bool $debug
     * @return Response
     */
    public function presentServerError(
        string $message = 'Internal server error',
        ?\Throwable $throwable = null,
        bool $debug = false
    ): Response;
}

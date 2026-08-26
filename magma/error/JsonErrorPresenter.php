<?php

declare(strict_types=1);

namespace Magma\error;

use Magma\http\Response;

/**
 * Title: JSON Error Presenter & API Exception Formatter
 *
 * Purpose:
 * - Formats application exceptions and HTTP error status codes (400, 401, 403, 404, 422, 500) into standardized JSON error envelopes.
 * - Enforces consistent JSON error schemas for RESTful APIs and asynchronous AJAX consumers.
 *
 * Why / Why this design:
 * - Single Responsibility Principle (SRP): Isolates JSON error serialization mechanics from HTML template rendering logic in `ErrorHandler`.
 * - Security & Information Disclosure Prevention: Automatically strips detailed stack traces, system paths, and raw database exception messages in production environments while presenting full diagnostics during local development.
 *
 * Teaching notes:
 * - Client-side SPA frameworks and mobile apps depend on strict, predictable error contracts (e.g. `{success: false, error: string, code: int}`).
 */
class JsonErrorPresenter implements \Magma\interfaces\JsonErrorPresenterInterface
{
    /**
     * Formats an error into a standardized JSON Response.
     *
     * Execution Flow:
     * 1. Constructs standard JSON payload with `success => false`, `error => message`, and `code => status`.
     * 2. If validation errors array is provided, attaches `errors => [...]`.
     * 3. If debug mode is active and a Throwable is present, attaches file, line, and stack trace metadata.
     * 4. Returns an HTTP Response with application/json content type.
     *
     * @param int $code HTTP Status Code (400-599)
     * @param string $message User-safe error description
     * @param \Throwable|null $throwable Underlying exception for debugging
     * @param bool $debug Whether to expose debugging stack traces
     * @param array<string, mixed>|null $errors Granular validation or field errors
     * @return Response
     */
    public function present(
        int $code,
        string $message,
        ?\Throwable $throwable = null,
        bool $debug = false,
        ?array $errors = null
    ): Response {
        $payload = [
            'success' => false,
            'error'   => $message,
            'code'    => $code,
        ];

        if ($errors !== null && !empty($errors)) {
            $payload['errors'] = $errors;
        }

        if ($debug && $throwable !== null) {
            $payload['debug'] = [
                'exception' => get_class($throwable),
                'file'      => $throwable->getFile(),
                'line'      => $throwable->getLine(),
                'trace'     => explode("\n", $throwable->getTraceAsString()),
            ];
        }

        $jsonString = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return new Response(
            $jsonString,
            $code,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    /**
     * Formats a 404 Not Found JSON error response.
     *
     * @param string $message
     * @return Response
     */
    public function presentNotFound(string $message = 'Resource not found'): Response
    {
        return $this->present(404, $message);
    }

    /**
     * Formats a 401 Unauthorized JSON error response.
     *
     * @param string $message
     * @return Response
     */
    public function presentUnauthorized(string $message = 'Unauthorized access'): Response
    {
        return $this->present(401, $message);
    }

    /**
     * Formats a 403 Forbidden JSON error response.
     *
     * @param string $message
     * @return Response
     */
    public function presentForbidden(string $message = 'Access forbidden'): Response
    {
        return $this->present(403, $message);
    }

    /**
     * Formats a 422 Unprocessable Entity (Validation Error) JSON response.
     *
     * @param array<string, mixed> $errors
     * @param string $message
     * @return Response
     */
    public function presentValidation(array $errors, string $message = 'Validation failed'): Response
    {
        return $this->present(422, $message, null, false, $errors);
    }

    /**
     * Formats a 500 Internal Server Error JSON response.
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
    ): Response {
        return $this->present(500, $message, $throwable, $debug);
    }
}

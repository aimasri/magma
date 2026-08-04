<?php

namespace Magma\controllers;

use Magma\http\Response;

/**
 * Title: API Base Controller
 *
 * Purpose:
 * - Base class for JSON APIs providing standard response formatting and error handling.
 *
 * Why this design:
 * - Centralizes try/catch blocks for API endpoints.
 * - Catching \Throwable instead of \Exception ensures that PHP 7/8 fatal errors 
 *   (like TypeError or DivisionByZeroError) don't bypass the JSON formatter 
 *   and return HTML stack traces to API clients.
 *
 * Teaching notes:
 * - Common pattern in RESTful APIs to ensure consistent JSON structures (envelope pattern) for clients.
 * - In production, consider logging the full exception stack trace internally while only returning generic error messages to the client for security.
 */
abstract class ApiBaseController
{
    /**
     * Helper to return a JSON response with strict \Throwable catching.
     *
     * Execution Flow:
     * 1. Execute the provided callback to retrieve the data payload.
     * 2. Serialize the successful response into a JSON envelope format.
     * 3. If any throwable occurs, catch it and serialize a standard error JSON envelope.
     * 4. Return an HTTP Response object with the appropriate status code and headers.
     *
     * Logic behind the logic:
     * - Centralized Error Handling: Guarantees that even catastrophic errors are formatted correctly for API consumers, avoiding broken JSON or raw HTML output.
     *
     * @param callable $callback The business logic returning an array or object to be serialized.
     * @return Response
     */
    protected function executeJson(callable $callback): Response
    {
        try {
            $data = $callback();
            $payload = json_encode(['success' => true, 'data' => $data], JSON_THROW_ON_ERROR);
            
            return new Response($payload, 200, ['Content-Type' => 'application/json']);
        } catch (\Throwable $e) {
            $statusCode = 500;
            // You can inspect $e to return different HTTP status codes here if desired.
            
            // Log the actual error internally to prevent information disclosure
            error_log($e->getMessage());
            
            $errorPayload = json_encode([
                'success' => false,
                'error' => 'An unexpected internal error occurred.'
            ]);
            
            return new Response($errorPayload, $statusCode, ['Content-Type' => 'application/json']);
        }
    }
}

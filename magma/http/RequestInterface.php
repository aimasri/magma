<?php

namespace Magma\http;

/**
 * Title: Request Interface
 *
 * Purpose:
 * - Defines a contract for HTTP requests, allowing for decoupled implementations.
 *
 * Why this design:
 * - Adheres to the Dependency Inversion Principle, ensuring that core framework components depend on abstractions rather than concrete request classes.
 *
 * Teaching notes:
 * - Interfaces provide clear boundaries and improve testability by enabling mocking.
 */
interface RequestInterface
{
    /**
     * Retrieves the HTTP method (verb) used for the request.
     * @return string e.g., 'GET', 'POST'
     */
    public function getMethod(): string;

    /**
     * Retrieves the full request URI, including query strings.
     */
    public function getUri(): string;

    /**
     * Retrieves the URL path without query string parameters.
     */
    public function getPath(): string;

    /**
     * Breaks the URL path into an array of individual segments.
     */
    public function pathSegments(): array;

    /**
     * Retrieves a value from the query string (GET parameters).
     */
    public function query(?string $key = null, mixed $default = null): mixed;

    /**
     * Retrieves a value from the request body (POST parameters).
     */
    public function request(?string $key = null, mixed $default = null): mixed;

    /**
     * Retrieves a value from the server environment array ($_SERVER).
     */
    public function server(?string $key = null, mixed $default = null): mixed;

    /**
     * Retrieves a value from the user's active session.
     */
    public function session(?string $key = null, mixed $default = null): mixed;

    /**
     * Retrieves a one-time flash message from the session.
     */
    public function flash(string $key, mixed $default = null): mixed;

    /**
     * Sets a key-value pair in the user's active session.
     */
    public function setSession(string $key, mixed $value): void;

    /**
     * Attaches a custom attribute to the request for use by later middleware or controllers.
     */
    public function setAttribute(string $key, mixed $value): void;

    /**
     * Retrieves a previously attached custom attribute.
     */
    public function getAttribute(string $key, mixed $default = null): mixed;

    /**
     * Retrieves a specific HTTP header from the incoming request.
     */
    public function header(string $key, mixed $default = null): ?string;

    /**
     * Retrieves the raw, unparsed request body string.
     */
    public function getRawBody(): string;

    /**
     * Determines if the request is expecting a JSON response based on headers.
     */
    public function isJsonExpected(): bool;

    /**
     * Retrieves an uploaded file array from the request ($_FILES).
     */
    public function file(?string $key = null, mixed $default = null): mixed;

    /**
     * Retrieves a cookie value from the request.
     */
    public function cookie(?string $key = null, mixed $default = null): mixed;
}

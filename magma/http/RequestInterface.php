<?php

declare(strict_types=1);

namespace Magma\http;

/**
 * Title: HTTP Request Contract
 *
 * Purpose:
 * - Defines the standard interface for HTTP request representations across the framework.
 * - Decouples application consumers (controllers, middlewares, validators) from PHP superglobals.
 *
 * Why / Why this design:
 * - Dependency Inversion Principle (DIP): Components depend on this abstraction rather than concrete HTTP implementations, facilitating diskless unit testing, API simulation, and asynchronous execution.
 *
 * Teaching notes:
 * - Adheres to clean architecture boundaries by shielding controllers from mutable global state.
 */
interface RequestInterface
{
    /**
     * Retrieves the HTTP method verb (e.g., 'GET', 'POST', 'PUT', 'DELETE').
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * Retrieves the full request URI including path and query string.
     *
     * @return string
     */
    public function getUri(): string;

    /**
     * Retrieves the normalized URL path without query string parameters.
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Breaks the URL path into an array of individual trimmed segments.
     *
     * @return string[]
     */
    public function pathSegments(): array;

    /**
     * Retrieves a value from the query string ($_GET).
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function query(?string $key = null, mixed $default = null): mixed;

    /**
     * Retrieves a value from the request body (POST / parsed JSON parameters).
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function request(?string $key = null, mixed $default = null): mixed;

    /**
     * Retrieves a value from the server environment array ($_SERVER).
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function server(?string $key = null, mixed $default = null): mixed;

    /**
     * Returns a new request instance with the attached custom attribute.
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function withAttribute(string $key, mixed $value): self;

    /**
     * Retrieves a previously attached custom attribute.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getAttribute(string $key, mixed $default = null): mixed;

    /**
     * Retrieves a specific HTTP header from the request.
     *
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public function header(string $key, ?string $default = null): ?string;

    /**
     * Retrieves the raw, unparsed request payload body.
     *
     * @return string
     */
    public function getRawBody(): string;

    /**
     * Determines if the request expects a JSON response based on headers or route path.
     *
     * @return bool
     */
    public function isJsonExpected(): bool;

    /**
     * Alias for isJsonExpected().
     *
     * @return bool
     */
    public function expectsJson(): bool;

    /**
     * Retrieves uploaded file metadata ($_FILES).
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function file(?string $key = null, mixed $default = null): mixed;

    /**
     * Retrieves cookie parameters ($_COOKIE).
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function cookie(?string $key = null, mixed $default = null): mixed;

    /**
     * Determines if the request was made over HTTPS.
     *
     * @return bool
     */
    public function isSecure(): bool;
}

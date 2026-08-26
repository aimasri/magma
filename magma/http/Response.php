<?php

namespace Magma\http;

/**
 * Title: HTTP Response Abstraction
 *
 * Purpose:
 * - Encapsulates HTTP status codes, response headers, and the HTML/JSON payload.
 * - Provides a fluent API for modifying responses before transmission.
 *
 * Why this design:
 * - Returning a `Response` object instead of using `echo` or `header()` immediately allows middleware layers to intercept and modify the output (e.g., adding CORS headers or GZIP compression) before anything is sent over the wire.
 *
 * Teaching notes:
 * - Modern frameworks separate the "creation" of a response from the "emission" of it. This is why the `send()` method is only called once, at the very end of the Application lifecycle, preventing "headers already sent" errors.
 */
class Response
{
    private string $content;
    private int $statusCode;
    /** @var array<string, string> */
    private array $headers;
    /** @var array<int, array{name: string, value: string, expires: int, path: string, domain: string, secure: bool, httponly: bool}> */
    private array $cookies = [];

    /**
     * @param string $content
     * @param int $statusCode
     * @param array<string, string> $headers
     */
    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Attaches a cookie to be sent with the response.
     * 
     * Execution Flow:
     * 1. Accept cookie parameters (matching PHP's native setcookie signature).
     * 2. Store the cookie definition in the internal `$cookies` array.
     * 3. Return the Response instance to allow method chaining.
     * 
     * Logic behind the logic:
     * - By buffering cookies internally rather than emitting them immediately, we 
     *   prevent "headers already sent" errors and maintain strict MVC encapsulation.
     */
    public function withCookie(
        string $name, 
        string $value = "", 
        int $expires = 0, 
        string $path = "/", 
        string $domain = "", 
        bool $secure = true, 
        bool $httponly = true
    ): self {
        $this->cookies[] = compact('name', 'value', 'expires', 'path', 'domain', 'secure', 'httponly');
        return $this;
    }

    /**
     * Transmits the response to the client.
     * 
     * Execution Flow:
     * 1. Emit the HTTP status code.
     * 2. Iterate through and emit all buffered HTTP headers.
     * 3. Iterate through and emit all buffered cookies via `setcookie()`.
     * 4. Output the raw string content body.
     * 
     * Logic behind the logic:
     * - This is the absolute final step of the request lifecycle. It centralizes 
     *   the use of native PHP output functions, ensuring they are only called once.
     */
    public function send(): void
    {
        http_response_code($this->statusCode);
        
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", false);
        }

        foreach ($this->cookies as $cookie) {
            setcookie(
                $cookie['name'], 
                $cookie['value'], 
                $cookie['expires'], 
                $cookie['path'], 
                $cookie['domain'], 
                $cookie['secure'], 
                $cookie['httponly']
            );
        }

        echo $this->content;
    }
}

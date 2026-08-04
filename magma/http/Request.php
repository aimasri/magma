<?php

namespace Magma\http;

/**
 * HTTP Request Abstraction
 *
 * Purpose:
 * - Provide a single, testable API over PHP superglobals (GET/POST/FILES/COOKIE/SERVER).
 * - Normalize incoming data, including automatic JSON payload parsing.
 * - Handle HTTP method spoofing for RESTful routing.
 *
 * Why / Why this design:
 * - Encapsulating global state makes request handling deterministic and easier to unit test. 
 *   By removing direct references to `$_POST` or `$_GET` in controllers, we guarantee that 
 *   all input goes through this single abstraction layer where it can be sanitized or logged.
 *
 * Teaching notes:
 * - This class manually parses `php://input` for JSON payloads. In standard PHP, `$_POST` 
 *   is only populated if the `Content-Type` is `application/x-www-form-urlencoded` or 
 *   `multipart/form-data`. Modern APIs rely heavily on JSON, so this abstraction bridges that gap.
 */
class Request implements RequestInterface
{
    /**
     * @var array Allowed HTTP verbs.
     * 
     * Purpose:
     * - Restricts processing to safe/expected HTTP methods.
     * 
     * Teaching notes:
     * - This is static to comply with the Open/Closed Principle. Service providers can 
     *   append new methods (e.g., custom verbs) without modifying this core class.
     */
    private static array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
    private static ?string $cachedRawBody = null;

    /**
     * Registers a custom HTTP method to bypass OCP violations.
     */
    public static function addAllowedMethod(string $method): void
    {
        $method = strtoupper($method);
        if (!in_array($method, self::$allowedMethods, true)) {
            self::$allowedMethods[] = $method;
        }
    }

    // Storage for various input sources to avoid direct global access.
    private array $get;
    private array $post;
    private array $server;
    private array $files;
    private array $cookies;
    
    // Derived properties for routing and logic.
    private string $method;
    private string $uri;
    private string $path;

    /** @var array|null Data extracted from application/json payloads */
    private ?array $parsedJson = null; 
    
    /** @var array Internal storage for data passed between middlewares (e.g., 'user_id') */
    private array $attributes = []; 
    
    /** @var array|null Merged set of POST and JSON data for unified access */
    private ?array $requestData = null; 
    
    /** @var array|null Cached path segments to avoid redundant string parsing */
    private ?array $segments = null;

    private ?string $rawBody = null;

    public function __construct(
        string $method,
        string $uri,
        array $get = [],
        array $post = [],
        array $server = [],
        array $files = [], 
        array $cookies = [],
        ?array $parsedJson = null,
        ?string $rawBody = null
    ) {
        $this->method = $method;
        $this->uri = $uri;
        $this->path = parse_url($this->uri, PHP_URL_PATH) ?? '/';
        $this->get = $get;
        $this->post = $post;
        $this->server = $server;
        $this->files = $files;
        $this->cookies = $cookies;
        $this->parsedJson = $parsedJson;
        $this->rawBody = $rawBody;
        $this->requestData = $this->parsedJson !== null ? $this->parsedJson : $this->post;
    }

    /**
     * Factory method to build a Request instance.
     * Offloads JSON decoding and HTTP verb spoofing to a dedicated builder method.
     */
    public static function build(
        array $get = [],
        array $post = [],
        array $server = [],
        array $files = [], 
        array $cookies = [],
        ?string $rawBody = null
    ): self {
        $rawMethod = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        
        if (!in_array($rawMethod, self::$allowedMethods, true)) {
            throw new \RuntimeException("Method Not Allowed: {$rawMethod}", 405);
        }
        
        $method = $rawMethod;

        if ($method === 'POST' && isset($post['_method'])) {
            $spoofedMethod = strtoupper($post['_method']);
            if (in_array($spoofedMethod, self::$allowedMethods, true)) {
                $method = $spoofedMethod;
            } else {
                throw new \RuntimeException("Method Not Allowed: {$spoofedMethod}", 405);
            }
        }

        $uri = $server['REQUEST_URI'] ?? '/';
        
        $contentType = strtolower($server['CONTENT_TYPE'] ?? $server['HTTP_CONTENT_TYPE'] ?? '');
        $parsedJson = \Magma\http\PayloadParser::parseJsonPayload($contentType, $rawBody);

        return new self($method, $uri, $get, $post, $server, $files, $cookies, $parsedJson, $rawBody);
    }

    /**
     * Factory method to create a Request instance using PHP globals.
     */
    public static function createFromGlobals(): self
    {
        if (self::$cachedRawBody === null) {
            self::$cachedRawBody = file_get_contents('php://input');
        }
        return self::build($_GET, $_POST, $_SERVER, $_FILES, $_COOKIE, self::$cachedRawBody);
    }

    /**
     * Get the HTTP request method (e.g., GET, POST, PUT, DELETE).
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the full request URI.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get the URL path without query parameters.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the URL path parsed into segments.
     * 
     * Execution Flow:
     * 1. Trim the internal path of trailing or leading slashes.
     * 2. If the path is entirely empty (the root `/`), return an empty array.
     * 3. Split the path by the `/` delimiter.
     * 4. Filter out any empty segments caused by consecutive slashes (e.g., `//`).
     * 5. Re-index the array values starting from 0 and return.
     * 
     * Logic behind the logic:
     * - Abstracting path parsing avoids scattering `$_SERVER` access or string 
     *   manipulation throughout the codebase and makes behavior easier to test.
     *
     * @return array
     */
    public function pathSegments(): array
    {
        if ($this->segments !== null) {
            return $this->segments;
        }

        $cleanPath = trim($this->path, " /");
        if ($cleanPath === '') {
            return $this->segments = [];
        }
        return $this->segments = array_values(array_filter(explode('/', $cleanPath), 'strlen'));
    }

    /**
     * Retrieve data from the query string ($_GET).
     * 
     * @param string|null $key Key to retrieve, or null for all query data.
     * @param mixed $default Default value if the key does not exist.
     * @return mixed
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->get;
        }
        return $this->get[$key] ?? $default;
    }

    /**
     * Retrieve data from the merged request data (POST + JSON).
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function request(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->requestData;
        }
        return $this->requestData[$key] ?? $default;
    }
    
    /**
     * Retrieve server environment data ($_SERVER).
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }
        return $this->server[$key] ?? $default;
    }
    

    

    /**
     * Return an instance with the specified derived request attribute.
     * 
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    /**
     * Retrieve a custom attribute.
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Retrieve an HTTP header. Automatically normalizes the header key.
     * 
     * PHP stores headers in $_SERVER with an 'HTTP_' prefix and underscores.
     * This method allows you to fetch headers using standard naming conventions 
     * (e.g., 'X-Requested-With') while handling the internal mapping automatically.
     * 
     * @param string $key Header name (e.g., 'Content-Type' or 'User-Agent').
     * @param mixed $default
     * @return string|null
     */
    public function header(string $key, mixed $default = null): ?string
    {
        $key = str_replace('-', '_', strtoupper($key));
        $headerKey = (str_starts_with($key, 'HTTP_') || $key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') 
            ? $key 
            : 'HTTP_' . $key;

        return $this->server[$headerKey] ?? $default;
    }

    /**
     * Get the raw request body as a string.
     */
    public function getRawBody(): string
    {
        return $this->rawBody ?? '';
    }

    /**
     * Content Negotiation Helper.
     * Determines if the client prefers a JSON response over HTML.
     */
    public function isJsonExpected(): bool
    {
        $accept = $this->header('Accept', '');
        $requestedWith = $this->header('X-Requested-With', '');
        
        return str_contains($accept, 'application/json') || strtolower($requestedWith) === 'xmlhttprequest';
    }

    /**
     * Retrieve data from the files array ($_FILES).
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function file(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->files;
        }
        return $this->files[$key] ?? $default;
    }

    /**
     * Retrieve data from cookies ($_COOKIE).
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->cookies;
        }
        return $this->cookies[$key] ?? $default;
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';
        if ((!empty($https) && strtolower($https) !== 'off') || ($this->server['SERVER_PORT'] ?? '') == 443) {
            return true;
        }

        // Only trust X-Forwarded-Proto if coming from a trusted proxy (e.g., localhost proxy)
        // For demonstration, we assume proxies from 127.0.0.1 or 10.x.x.x are trusted.
        $remoteAddr = $this->server['REMOTE_ADDR'] ?? '';
        if (str_starts_with($remoteAddr, '127.') || str_starts_with($remoteAddr, '10.')) {
            $forwardedProto = $this->header('X-Forwarded-Proto', '');
            return strtolower($forwardedProto) === 'https';
        }
        
        return false;
    }
}
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
    public static array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];

    // Storage for various input sources to avoid direct global access.
    private array $get;
    private array $post;
    private array $server;
    private array $files;
    private array $cookies;
    private Session $session;
    
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
        array $get = [],
        array $post = [],
        array $server = [],
        array $files = [], 
        array $cookies = [],
        ?Session $session = null,
        ?string $rawBody = null
    )
    {
        $this->get = $get;
        $this->post = $post;
        $this->server = $server;
        $this->files = $files;
        $this->cookies = $cookies;
        $this->rawBody = $rawBody;

        $this->session = $session ?? new Session();

        // Detect the HTTP method, defaulting to GET if not set.
        $rawMethod = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        
        if (!in_array($rawMethod, self::$allowedMethods, true)) {
            throw new \RuntimeException("Method Not Allowed: {$rawMethod}", 405);
        }
        
        $this->method = $rawMethod;

        /**
         * HTTP Method Spoofing:
         * Standard HTML forms only support GET and POST. To use RESTful methods like 
         * PUT or DELETE, we look for a hidden '_method' field in the POST data.
         * 
         * Execution Flow:
         * 1. Detect the raw HTTP method and validate it against a strict allowlist.
         * 2. If it is invalid, immediately throw a 405 Method Not Allowed exception.
         * 3. If the request is a POST, check for a '_method' spoofing parameter.
         * 4. Validate the spoofed method against the same allowlist to prevent bypass.
         * 
         * Logic behind the logic:
         * - Checking the raw method first prevents malicious actors from sending arbitrary 
         *   HTTP verbs. Re-validating the spoofed method ensures they cannot use a valid 
         *   POST request to spoof an invalid verb like TRACK or TRACE.
         */
        if ($this->method === 'POST' && isset($this->post['_method'])) {
            $spoofedMethod = strtoupper($this->post['_method']);
            if (in_array($spoofedMethod, self::$allowedMethods, true)) {
                $this->method = $spoofedMethod;
            } else {
                throw new \RuntimeException("Method Not Allowed: {$spoofedMethod}", 405);
            }
        }

        $this->uri = $this->server['REQUEST_URI'] ?? '/';
        $this->path = parse_url($this->uri, PHP_URL_PATH) ?? '/';


    }

    /**
     * Factory method to create a Request instance using PHP globals.
     * 
     * @return self
     */
    public static function createFromGlobals(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_FILES, $_COOKIE, new Session());
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
     * Parses JSON lazily on first access.
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function request(?string $key = null, mixed $default = null): mixed
    {
        if ($this->requestData === null) {
            $this->parseJsonPayload();
            $this->requestData = !empty($this->parsedJson) ? $this->parsedJson : $this->post;
        }

        if ($key === null) {
            return $this->requestData;
        }
        return $this->requestData[$key] ?? $default;
    }

    /**
     * Lazily parses the JSON payload if the Content-Type header indicates application/json.
     */
    private function parseJsonPayload(): void
    {
        if ($this->parsedJson !== null) {
            return;
        }

        $this->parsedJson = [];
        $contentType = strtolower($this->server('CONTENT_TYPE', ''));
        
        if (str_contains($contentType, 'json')) {
            if ($this->rawBody === null) {
                $this->rawBody = (string) file_get_contents('php://input');
            }

            if ($this->rawBody !== '') {
                try {
                    $decoded = json_decode($this->rawBody, true, 512, JSON_THROW_ON_ERROR);
                    $this->parsedJson = is_array($decoded) ? $decoded : [];
                } catch (\JsonException $e) {
                    throw new \RuntimeException("Invalid JSON payload: " . $e->getMessage(), 400, $e);
                }
            }
        }
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
     * Retrieve session data.
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function session(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->session->all();
        }
        return $this->session->get($key, $default);
    }
    
    /**
     * Atomically read and clear a session value (Flash data).
     * 
     * Purpose:
     * - Implements the Post/Redirect/Get (PRG) flash pattern safely.
     * 
     * Execution Flow:
     * 1. Retrieve the value from the session.
     * 2. Delete the key from the session.
     * 3. Return the value.
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function flash(string $key, mixed $default = null): mixed
    {
        $value = $this->session->get($key, $default);
        $this->session->set($key, null);
        return $value;
    }
    
    /**
     * Set a value in the session.
     * 
     * @param string $key
     * @param mixed $value
     */
    public function setSession(string $key, mixed $value): void
    {
        $this->session->set($key, $value);
    }
    

    /**
     * Set a custom attribute on the request object (useful for middleware).
     * 
     * @param string $key
     * @param mixed $value
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
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

    /**
     * Determine if the request is over HTTPS.
     */
    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';
        return (!empty($https) && strtolower($https) !== 'off') || 
               ($this->server['SERVER_PORT'] ?? '') == 443;
    }
}
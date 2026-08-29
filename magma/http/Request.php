<?php

declare(strict_types=1);

namespace Magma\http;

/**
 * Title: HTTP Request Abstraction
 *
 * Purpose:
 * - Provides a unified, testable object-oriented representation of HTTP requests.
 * - Encapsulates superglobals (GET, POST, COOKIES, FILES, SERVER, php://input) and request state.
 * - Manages HTTP verb spoofing, content negotiation, JSON payload parsing, and middleware attributes.
 *
 * Why / Why this design:
 * - Encapsulating global state makes request handling deterministic and trivial to unit test.
 * - Static Builder Pattern (`Request::build()`): Encapsulates payload decoding and method spoofing in a dedicated factory, ensuring consistent initialization across web and CLI test suites.
 *
 * Teaching notes:
 * - Content-negotiation helpers (`expectsJson()`) allow middlewares and controllers to dynamically switch between JSON and HTML error responses.
 */
class Request implements RequestInterface
{
    /** @var array<string> Allowed standard HTTP verbs */
    private static array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
    private static ?string $cachedRawBody = null;

    /**
     * Registers a custom HTTP method verb.
     *
     * @param string $method
     * @return void
     */
    public static function addAllowedMethod(string $method): void
    {
        $method = strtoupper(trim($method));
        if (!in_array($method, self::$allowedMethods, true)) {
            self::$allowedMethods[] = $method;
        }
    }

    /** @var array<string, mixed> */
    private array $get;
    /** @var array<string, mixed> */
    private array $post;
    /** @var array<string, mixed> */
    private array $cookies;
    /** @var array<string, mixed> */
    private array $files;
    /** @var array<string, mixed> */
    private array $server;

    private string $method;
    private string $uri;
    private string $path;

    /** @var array<int|string, mixed>|null */
    private ?array $parsedJson = null;
    /** @var array<string, mixed> */
    private array $attributes = [];
    /** @var array<int|string, mixed>|null */
    private ?array $requestData = null;
    /** @var array<int, string> */
    private readonly array $segments;
    private ?string $rawBody = null;

    /**
     * Constructs a normalized HTTP Request instance.
     *
     * @param string $method
     * @param string $uri
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     * @param array<string, mixed> $cookies
     * @param array<int|string, mixed>|null $parsedJson
     * @param string|null $rawBody
     */
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
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $parsedPath = parse_url($this->uri, PHP_URL_PATH);
        $this->path = is_string($parsedPath) ? $parsedPath : '/';
        $this->get = $get;
        $this->post = $post;
        $this->server = $server;
        $this->files = $files;
        $this->cookies = $cookies;
        $this->parsedJson = $parsedJson;
        $this->rawBody = $rawBody;
        $this->requestData = $this->parsedJson !== null ? $this->parsedJson : $this->post;

        $cleanPath = trim($this->path, " /");
        if ($cleanPath === '') {
            $this->segments = [];
        } else {
            $this->segments = array_values(array_filter(explode('/', $cleanPath), static fn(string $segment): bool => strlen($segment) > 0));
        }
    }

    /**
     * Static factory to build a Request instance with automatic JSON decoding and verb spoofing.
     *
     * Execution Flow:
     * 1. Extracts raw HTTP verb from `$server['REQUEST_METHOD']` or defaults to 'GET'.
     * 2. Checks for verb spoofing via POST `_method` or `X-HTTP-Method-Override` header.
     * 3. Resolves request URI from `$server['REQUEST_URI']`.
     * 4. Parses raw JSON input if the Content-Type is `application/json`.
     * 5. Instantiates and returns the configured Request object.
     *
     * Logic behind the logic:
     * - Encapsulating method spoofing and payload parsing in this builder guarantees that unit tests passing custom arrays receive identical normalization behavior as live HTTP requests.
     *
     * @param array<string, mixed>|null $get
     * @param array<string, mixed>|null $post
     * @param array<string, mixed>|null $cookies
     * @param array<string, mixed>|null $files
     * @param array<string, mixed>|null $server
     * @param string|null $rawBody
     * @return self
     */
    public static function build(
        ?array $get = null,
        ?array $post = null,
        ?array $cookies = null,
        ?array $files = null,
        ?array $server = null,
        ?string $rawBody = null
    ): self {
        $serverData = $server ?? [];
        $postData = $post ?? [];
        $getData = $get ?? [];
        $cookieData = $cookies ?? [];
        $filesData = $files ?? [];

        $rawMethodVal = $serverData['REQUEST_METHOD'] ?? 'GET';
        $rawMethod = is_scalar($rawMethodVal) ? strtoupper((string)$rawMethodVal) : 'GET';

        if (!in_array($rawMethod, self::$allowedMethods, true)) {
            throw new \RuntimeException("Method Not Allowed: {$rawMethod}", 405);
        }

        $method = $rawMethod;

        // HTTP Method Spoofing via POST parameter or header
        if ($method === 'POST') {
            if (isset($postData['_method']) && is_scalar($postData['_method'])) {
                $spoofedMethod = strtoupper((string)$postData['_method']);
                if (in_array($spoofedMethod, self::$allowedMethods, true)) {
                    $method = $spoofedMethod;
                } else {
                    throw new \RuntimeException("Method Not Allowed: {$spoofedMethod}", 405);
                }
            } elseif (isset($serverData['HTTP_X_HTTP_METHOD_OVERRIDE']) && is_scalar($serverData['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $spoofedMethod = strtoupper((string)$serverData['HTTP_X_HTTP_METHOD_OVERRIDE']);
                if (in_array($spoofedMethod, self::$allowedMethods, true)) {
                    $method = $spoofedMethod;
                }
            }
        }

        $uriVal = $serverData['REQUEST_URI'] ?? '/';
        $uri = is_scalar($uriVal) ? (string)$uriVal : '/';

        $ctVal = $serverData['CONTENT_TYPE'] ?? $serverData['HTTP_CONTENT_TYPE'] ?? '';
        $contentType = is_scalar($ctVal) ? strtolower((string)$ctVal) : '';
        $parsedJson = null;

        if (str_contains($contentType, 'application/json') && $rawBody !== null && trim($rawBody) !== '') {
            $parsedJson = PayloadParser::parseJsonPayload($contentType, $rawBody);
        }

        return new self(
            $method,
            $uri,
            $getData,
            $postData,
            $serverData,
            $filesData,
            $cookieData,
            $parsedJson,
            $rawBody
        );
    }

    /**
     * Factory method to create a Request instance from current PHP globals.
     *
     * @return self
     */
    public static function createFromGlobals(): self
    {
        if (self::$cachedRawBody === null) {
            $input = file_get_contents('php://input');
            self::$cachedRawBody = $input !== false ? $input : null;
        }
        // @phpstan-ignore-next-line
        return self::build($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER, self::$cachedRawBody);
    }

    /**
     * Retrieves the HTTP verb for the request.
     *
     * Logic behind the logic:
     * - Centralizes access to the resolved HTTP method, abstracting away any verb spoofing that occurred during initialization.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Retrieves the full request URI.
     *
     * Logic behind the logic:
     * - Exposes the raw URI to allow precise routing constraints and middleware pattern matching.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Retrieves the parsed URI path without query parameters.
     *
     * Logic behind the logic:
     * - Isolates the strict URL path for clean matching against the application's defined route tree.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Retrieves the request path as an array of segments.
     *
     * Logic behind the logic:
     * - Provides a pre-computed array of path elements, optimizing performance for RESTful parameter extraction.
     */
    public function pathSegments(): array
    {
        return $this->segments;
    }

    /**
     * Retrieves a specific query parameter from $_GET, or the entire array if no key is provided.
     *
     * Logic behind the logic:
     * - Abstracting $_GET access ensures that controllers do not couple directly to superglobals.
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->get;
        }
        return $this->get[$key] ?? $default;
    }

    /**
     * Retrieves a specific parsed payload parameter (JSON or POST), or the entire array if no key is provided.
     *
     * Logic behind the logic:
     * - Unifies JSON body payloads and standard POST arrays, providing a seamless DX regardless of client content type.
     */
    public function request(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->requestData;
        }
        return $this->requestData[$key] ?? $default;
    }

    /**
     * Retrieves a specific server parameter from $_SERVER, or the entire array if no key is provided.
     *
     * Logic behind the logic:
     * - Wraps server variable access to facilitate mocking during unit and integration tests.
     */
    public function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }
        return $this->server[$key] ?? $default;
    }

    /**
     * Returns a new Request instance with the specified custom attribute added.
     *
     * Execution Flow:
     * 1. Clones the current request instance.
     * 2. Sets the attribute on the cloned copy.
     * 3. Returns the cloned copy.
     *
     * Logic behind the logic:
     * - Employs PSR-7 style immutability. Mutating state across middleware layers is dangerous; cloning guarantees deterministic request states.
     */
    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    /**
     * Retrieves a custom attribute assigned during middleware processing.
     *
     * Logic behind the logic:
     * - Allows upstream middleware (e.g., authentication) to safely attach resolved context (e.g., User object) for downstream controllers.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Retrieves a specific HTTP header value.
     *
     * Execution Flow:
     * 1. Normalizes the requested header key to match PHP's standard $_SERVER array formatting.
     * 2. Extracts and returns the scalar value or the default.
     *
     * Logic behind the logic:
     * - Masks the quirky behavior of PHP's HTTP_ prefixing, providing a predictable API for accessing raw headers.
     */
    public function header(string $key, ?string $default = null): ?string
    {
        $key = str_replace('-', '_', strtoupper($key));
        $headerKey = (str_starts_with($key, 'HTTP_') || $key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH')
            ? $key
            : 'HTTP_' . $key;

        return isset($this->server[$headerKey]) && is_scalar($this->server[$headerKey]) ? (string)$this->server[$headerKey] : $default;
    }

    /**
     * Retrieves the raw HTTP request body string.
     *
     * Logic behind the logic:
     * - Critical for cryptographic webhook signature verification where exact byte-for-byte matching is required before parsing.
     */
    public function getRawBody(): string
    {
        return $this->rawBody ?? '';
    }

    /**
     * Determines if the request expects a JSON response based on HTTP Accept, X-Requested-With, or /api/* prefix.
     *
     * @return bool
     */
    public function isJsonExpected(): bool
    {
        $accept = (string)$this->header('Accept', '');
        $requestedWith = (string)$this->header('X-Requested-With', '');

        return str_contains($accept, 'application/json')
            || strtolower($requestedWith) === 'xmlhttprequest'
            || str_starts_with($this->path, '/api/');
    }

    /**
     * Alias for isJsonExpected().
     *
     * @return bool
     */
    public function expectsJson(): bool
    {
        return $this->isJsonExpected();
    }

    /**
     * Retrieves a specific uploaded file from $_FILES, or the entire array if no key is provided.
     *
     * Logic behind the logic:
     * - Encapsulates file uploads to maintain the Request object's boundaries over PHP's global state.
     */
    public function file(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->files;
        }
        return $this->files[$key] ?? $default;
    }

    /**
     * Retrieves a specific cookie value from $_COOKIE, or the entire array if no key is provided.
     *
     * Logic behind the logic:
     * - Encapsulates cookie data access to prevent direct superglobal dependencies.
     */
    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->cookies;
        }
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Determines if the request was transmitted securely over HTTPS.
     *
     * Execution Flow:
     * 1. Checks standard $_SERVER variables for HTTPS or port 443.
     * 2. Validates the incoming request IP against a list of trusted proxies.
     * 3. If trusted, respects the 'X-Forwarded-Proto' header set by load balancers.
     *
     * Logic behind the logic:
     * - Safely handling 'X-Forwarded-Proto' ensures accurate security context even when deployed behind terminating reverse proxies or CDNs, while rejecting spoofed headers from direct malicious clients.
     */
    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';
        $isHttpsOn = !empty($https) && is_scalar($https) && strtolower((string)$https) !== 'off';
        $isPort443 = isset($this->server['SERVER_PORT']) && is_scalar($this->server['SERVER_PORT']) && ((int)$this->server['SERVER_PORT']) === 443;
        
        if ($isHttpsOn || $isPort443) {
            return true;
        }

        $remoteAddrVal = $this->server['REMOTE_ADDR'] ?? '';
        $remoteAddr = is_scalar($remoteAddrVal) ? (string)$remoteAddrVal : '';
        $trustedProxies = \Magma\config\Config::get('TRUSTED_PROXIES', ['127.0.0.1']);
        if (is_string($trustedProxies)) {
            $trustedProxies = explode(',', $trustedProxies);
        }

        $isTrustedProxy = false;
        if (is_array($trustedProxies)) {
            foreach ($trustedProxies as $proxy) {
                if (str_starts_with($remoteAddr, trim((string)$proxy))) {
                    $isTrustedProxy = true;
                    break;
                }
            }
        }

        if ($isTrustedProxy) {
            $forwardedProto = (string)$this->header('X-Forwarded-Proto', '');
            return strtolower($forwardedProto) === 'https';
        }

        return false;
    }
}
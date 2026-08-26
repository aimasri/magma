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

        return self::build($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER, self::$cachedRawBody);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function pathSegments(): array
    {
        return $this->segments;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->get;
        }
        return $this->get[$key] ?? $default;
    }

    public function request(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->requestData;
        }
        return $this->requestData[$key] ?? $default;
    }

    public function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }
        return $this->server[$key] ?? $default;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $key = str_replace('-', '_', strtoupper($key));
        $headerKey = (str_starts_with($key, 'HTTP_') || $key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH')
            ? $key
            : 'HTTP_' . $key;

        return isset($this->server[$headerKey]) && is_scalar($this->server[$headerKey]) ? (string)$this->server[$headerKey] : $default;
    }

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

    public function file(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->files;
        }
        return $this->files[$key] ?? $default;
    }

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
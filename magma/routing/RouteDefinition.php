<?php

declare(strict_types=1);

namespace Magma\routing;

/**
 * Title: Route Definition Fluent Builder
 *
 * Purpose:
 * - Represents a declared route builder instance prior to compilation and registration into the RouteCollection.
 * - Provides an expressive, fluent API for declaring route URIs, HTTP verbs, handlers, middleware stacks, parameter regex constraints, and route aliases.
 *
 * Why / Why this design:
 * - Fluent Interface Pattern: Simplifies route configuration by chaining modifiers (e.g., `->middleware()->name()->where()`) instead of passing unreadable 7-element array tuples.
 * - Immutability & Separation of Concerns: Separates the declaration phase (fluid configuration) from the runtime representation (`Route` value object), ensuring the routing table is deterministic and type-safe.
 *
 * Teaching notes:
 * - In enterprise frameworks (like Laravel or Symfony), routing definitions are parsed into immutable compiled representations. This builder decouples route registration syntax from internal router indexing.
 */
class RouteDefinition
{
    private string $method;
    private string $uri;
    /** @var array<int, string>|callable|string */
    private mixed $handler;
    private ?string $action = null;
    /** @var array<int, string> */
    private array $middleware = [];
    private ?string $name = null;
    /** @var array<string, string> */
    private array $parameters = [];
    private ?string $redirectOnFail = null;

    /**
     * @param string $method HTTP method (GET, POST, PUT, DELETE, PATCH, etc.)
     * @param string $uri The URL path pattern (e.g., '/users/{id}')
     * @param array<int, string>|callable|string $handler Controller action tuple, closure, or invocable class
     * @param ?string $action Specific controller method name if separated
     * @param array<int, string> $middleware Array of middleware class-strings or instances
     * @param ?string $name Unique route identifier for reverse URL generation
     * @param array<string, string> $parameters Associative array of parameter constraints (e.g. ['id' => '\d+'])
     * @param ?string $redirectOnFail Fallback redirection URI on constraint failure
     */
    public function __construct(
        string $method,
        string $uri,
        mixed $handler,
        ?string $action = null,
        array $middleware = [],
        ?string $name = null,
        array $parameters = [],
        ?string $redirectOnFail = null
    ) {
        $this->method = strtoupper(trim($method));
        $this->uri = '/' . ltrim(trim($uri), '/');
        $this->handler = $handler;
        $this->action = $action;
        $this->middleware = $middleware;
        $this->name = $name;
        $this->parameters = $parameters;
        $this->redirectOnFail = $redirectOnFail;

        // Auto-extract action if handler is [ControllerClass, 'actionName']
        if (is_array($handler) && count($handler) === 2 && is_string($handler[1])) {
            $this->action = $handler[1];
        }
    }

    /**
     * Static factory to create a GET route definition.
     *
     * @param string $uri
     * @param array<int, string>|callable|string $handler
     * @return self
     */
    public static function get(string $uri, mixed $handler): self
    {
        return new self('GET', $uri, $handler);
    }

    /**
     * Static factory to create a POST route definition.
     *
     * @param string $uri
     * @param array<int, string>|callable|string $handler
     * @return self
     */
    public static function post(string $uri, mixed $handler): self
    {
        return new self('POST', $uri, $handler);
    }

    /**
     * Static factory to create a PUT route definition.
     *
     * @param string $uri
     * @param array<int, string>|callable|string $handler
     * @return self
     */
    public static function put(string $uri, mixed $handler): self
    {
        return new self('PUT', $uri, $handler);
    }

    /**
     * Static factory to create a DELETE route definition.
     *
     * @param string $uri
     * @param array<int, string>|callable|string $handler
     * @return self
     */
    public static function delete(string $uri, mixed $handler): self
    {
        return new self('DELETE', $uri, $handler);
    }

    /**
     * Static factory to create a PATCH route definition.
     *
     * @param string $uri
     * @param array<int, string>|callable|string $handler
     * @return self
     */
    public static function patch(string $uri, mixed $handler): self
    {
        return new self('PATCH', $uri, $handler);
    }

    /**
     * Assigns a unique alias name to the route.
     *
     * @param string $name
     * @return $this
     */
    public function name(string $name): self
    {
        $this->name = trim($name);
        return $this;
    }

    /**
     * Appends one or multiple middleware classes to the route pipeline.
     *
     * @param array<int, string>|string $middleware
     * @return $this
     */
    public function middleware(array|string $middleware): self
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } else {
            $this->middleware[] = $middleware;
        }
        return $this;
    }

    /**
     * Defines regex constraints for dynamic URI parameters.
     *
     * @param array<string, string>|string $param Parameter name or associative array of param => regex
     * @param ?string $regex Regex pattern if first argument is a string
     * @return $this
     */
    public function where(array|string $param, ?string $regex = null): self
    {
        if (is_array($param)) {
            $this->parameters = array_merge($this->parameters, $param);
        } elseif ($regex !== null) {
            $this->parameters[$param] = $regex;
        }
        return $this;
    }

    /**
     * Configures a fallback redirection path if parameter constraints fail.
     *
     * @param string $path
     * @return $this
     */
    public function redirectOnFail(string $path): self
    {
        $this->redirectOnFail = $path;
        return $this;
    }

    /**
     * Converts the fluent definition into an immutable Route value object.
     *
     * @return Route
     */
    public function toRoute(): Route
    {
        return new Route(
            method: $this->method,
            uri: $this->uri,
            handler: $this->handler,
            action: $this->action,
            middleware: $this->middleware,
            name: $this->name,
            parameters: $this->parameters,
            redirectOnFail: $this->redirectOnFail
        );
    }

    /**
     * Gets the declared HTTP method.
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Gets the declared URI pattern.
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /** @return array<int, string>|callable|string */
    public function getHandler(): mixed
    {
        return $this->handler;
    }

    /**
     * Gets the action method explicitly extracted from the handler.
     *
     * @return ?string
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /** @return array<int, string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Gets the assigned alias name.
     *
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /** @return array<string, string> */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Gets the configured fallback redirection path.
     *
     * @return ?string
     */
    public function getRedirectOnFail(): ?string
    {
        return $this->redirectOnFail;
    }
}

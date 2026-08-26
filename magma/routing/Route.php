<?php

declare(strict_types=1);

namespace Magma\routing;

/**
 * Title: Immutable Route Value Object
 *
 * Purpose:
 * - Encapsulates an immutable, strongly-typed representation of an application route.
 * - Replaces ambiguous numeric tuple arrays ($route[0..6]) with named, type-safe properties.
 * - Supports native PHP serialization and caching manifests via `var_export` and `__set_state`.
 *
 * Why / Why this design:
 * - Value Object Pattern: By making the route definition immutable with typed properties, we eliminate primitive obsession and runtime indexing bugs (`Undefined array key 3`).
 * - High-Performance Cache Compatibility: Providing a deterministic `__set_state()` implementation allows OPcache to load compiled route tables instantly into worker memory without reflection or parsing overhead.
 * - ArrayAccess Bridge: Implements ArrayAccess strictly as a backward-compatibility facade for legacy tests/adapters while encouraging property access in all new code.
 *
 * Teaching notes:
 * - In enterprise architectures, value objects represent domain concepts defined by their attributes rather than a persistent identity.
 * - Notice the use of `readonly` properties: once constructed by the RouteCompiler or RouteCollection, a Route's state cannot be corrupted.
 */
class Route implements \JsonSerializable
{
    public readonly string $method;
    public readonly string $uri;
    /** @var array<int, string>|callable|string */
    public readonly mixed $handler;
    public readonly ?string $action;
    /** @var array<int, string> */
    public readonly array $middleware;
    public readonly ?string $name;
    /** @var array<string, string> */
    public readonly array $parameters;
    public readonly ?string $redirectOnFail;
    public readonly ?string $compiledRegex;

    /**
     * Constructs a strongly-typed Route Value Object.
     *
     * @param string $method HTTP Method (GET, POST, etc.)
     * @param string $uri The URI pattern (e.g., '/items/{id}')
     * @param array<int, string>|callable|string $handler Controller callback or handler
     * @param ?string $action Controller method name if applicable
     * @param array<int, string> $middleware Stack of middleware class names
     * @param ?string $name Unique route identifier name
     * @param array<string, string> $parameters Associative array of regex parameter constraints
     * @param ?string $redirectOnFail Redirection path on constraint mismatch
     * @param ?string $compiledRegex Pre-compiled PCRE regular expression pattern
     */
    public function __construct(
        string $method,
        string $uri,
        mixed $handler,
        ?string $action = null,
        array $middleware = [],
        ?string $name = null,
        array $parameters = [],
        ?string $redirectOnFail = null,
        ?string $compiledRegex = null
    ) {
        $this->method = strtoupper(trim($method));
        $this->uri = '/' . ltrim(trim($uri), '/');
        $this->handler = $handler;
        $this->middleware = array_values($middleware);
        $this->name = $name !== null && trim($name) !== '' ? trim($name) : null;
        $this->parameters = $parameters;
        $this->redirectOnFail = $redirectOnFail;
        $this->compiledRegex = $compiledRegex;

        if ($action !== null) {
            $this->action = $action;
        } elseif (is_array($handler) && count($handler) === 2 && is_string($handler[1])) {
            $this->action = $handler[1];
        } else {
            $this->action = null;
        }
    }

    /**
     * Restores a Route instance from an exported array state during cache loading.
     *
     * Execution Flow:
     * 1. Inspects the provided state array for required named keys.
     * 2. Re-instantiates an immutable Route instance with all cached metadata.
     *
     * Logic behind the logic:
     * - `var_export` calls `__set_state` when reading compiled PHP files, enabling O(1) OPcache execution.
     *
     * @param array<string, mixed> $state
     * @return self
     */
    public static function __set_state(array $state): self
    {
        $middleware = is_array($state['middleware'] ?? null) ? $state['middleware'] : [];
        $parameters = is_array($state['parameters'] ?? null) ? $state['parameters'] : (is_array($state['constraints'] ?? null) ? $state['constraints'] : []);

        $handler = $state['handler'] ?? [];
        if (!is_array($handler) && !is_callable($handler) && !is_string($handler)) {
            $handler = [];
        }

        return new self(
            method: is_string($state['method'] ?? null) ? $state['method'] : 'GET',
            uri: is_string($state['uri'] ?? null) ? $state['uri'] : '/',
            handler: $handler,
            action: is_string($state['action'] ?? null) ? $state['action'] : null,
            middleware: array_filter($middleware, 'is_string'),
            name: is_string($state['name'] ?? null) ? $state['name'] : null,
            parameters: array_filter($parameters, 'is_string'),
            redirectOnFail: is_string($state['redirectOnFail'] ?? null) ? $state['redirectOnFail'] : null,
            compiledRegex: is_string($state['compiledRegex'] ?? null) ? $state['compiledRegex'] : null
        );
    }

    /**
     * Determines whether the route is static (contains no dynamic parameter tokens).
     *
     * @return bool
     */
    public function isStatic(): bool
    {
        return !str_contains($this->uri, '{');
    }

    /**
     * Determines whether the route has dynamic parameter placeholders.
     *
     * @return bool
     */
    public function hasParameters(): bool
    {
        return str_contains($this->uri, '{');
    }

    /**
     * Returns a clone of this route with the compiled regular expression attached.
     *
     * @param string $regex
     * @return self
     */
    public function withCompiledRegex(string $regex): self
    {
        return new self(
            method: $this->method,
            uri: $this->uri,
            handler: $this->handler,
            action: $this->action,
            middleware: $this->middleware,
            name: $this->name,
            parameters: $this->parameters,
            redirectOnFail: $this->redirectOnFail,
            compiledRegex: $regex
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    /** @return array<int, string>|callable|string */
    public function getHandler(): mixed
    {
        return $this->handler;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    /** @return array<int, string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /** @return array<string, string> */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /** @return array<string, string> */
    public function getConstraints(): array
    {
        return $this->parameters;
    }

    public function getRedirectOnFail(): ?string
    {
        return $this->redirectOnFail;
    }

    public function getCompiledRegex(): ?string
    {
        return $this->compiledRegex;
    }

    /**
     * JSON serialization support.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'method' => $this->method,
            'uri' => $this->uri,
            'handler' => is_array($this->handler) ? $this->handler : (is_string($this->handler) ? $this->handler : 'Closure'),
            'action' => $this->action,
            'middleware' => $this->middleware,
            'name' => $this->name,
            'parameters' => $this->parameters,
            'redirectOnFail' => $this->redirectOnFail,
            'compiledRegex' => $this->compiledRegex,
        ];
    }

    // --------------------------------------------------------------------------
    // ArrayAccess Implementation (Backward Compatibility Bridge)
    // --------------------------------------------------------------------------

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, [0, 1, 2, 3, 4, 5, 6, 'method', 'uri', 'handler', 'action', 'middleware', 'name', 'parameters', 'constraints', 'redirectOnFail', 'compiledRegex'], true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            0, 'method' => $this->method,
            1, 'uri' => $this->uri,
            2, 'handler' => $this->handler,
            3, 'parameters', 'constraints' => $this->parameters,
            4, 'redirectOnFail' => $this->redirectOnFail,
            5, 'middleware' => $this->middleware,
            6 => $this->compiledRegex ?? $this->name,
            'action' => $this->action,
            'name' => $this->name,
            'compiledRegex' => $this->compiledRegex,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Route value object is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Route value object is immutable.');
    }
}

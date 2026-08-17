<?php

declare(strict_types=1);

namespace Magma\http;

/**
 * Title: Session Management Contract
 *
 * Purpose:
 * - Defines an object-oriented contract for interacting with user session state.
 * - Decouples controllers, authentication services, and middleware from PHP superglobals (`$_SESSION`) and native session lifecycle hooks.
 *
 * Why / Why this design:
 * - Dependency Inversion Principle (DIP): Allows the application kernel to swap seamlessly between native PHP sessions, Redis/Memcached distributed session storage, and in-memory Array drivers for fast, isolated unit testing.
 * - Security Abstraction: Encapsulates session regeneration (mitigating session fixation attacks) and flash messaging behind a unified interface.
 *
 * Teaching notes:
 * - When writing controllers and services, always type-hint `SessionInterface` rather than concrete storage implementations.
 */
interface SessionInterface
{
    /**
     * Retrieves an item from the session by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Stores a key/value pair in the session.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Checks if a key exists in the session.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Removes an item from the session.
     *
     * @param string $key
     * @return void
     */
    public function remove(string $key): void;

    /**
     * Returns all items currently stored in the session.
     *
     * @return array
     */
    public function all(): array;

    /**
     * Retrieves a flash value and immediately deletes it from storage.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function flash(string $key, mixed $default = null): mixed;

    /**
     * Regenerates the session identifier to prevent session fixation attacks.
     *
     * @param bool $deleteOldSession Whether to delete the old session data associated with the previous ID
     * @return bool
     */
    public function regenerate(bool $deleteOldSession = true): bool;

    /**
     * Commits session mutations and closes the write lock.
     *
     * @return void
     */
    public function save(): void;

    /**
     * Completely invalidates and clears the session state.
     *
     * @return void
     */
    public function destroy(): void;
}

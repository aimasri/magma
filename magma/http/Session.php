<?php

namespace Magma\http;

/**
 * Native Session Wrapper
 *
 * Purpose:
 * - Provide an object-oriented API for interacting with session data (get/set/remove).
 * - Centralize security policies for the session cookie (HttpOnly, SameSite, Secure).
 * - Provide safe mechanisms for session regeneration and destruction.
 *
 * Why / Why this design:
 * - By hiding the `$_SESSION` superglobal, the rest of the application is decoupled from 
 *   how sessions are stored. This makes it trivial to replace native file-based sessions 
 *   with Redis or Memcached later without changing controller logic.
 *
 * Teaching notes:
 * - `session_regenerate_id(true)` is critical to prevent Session Fixation attacks. It 
 *   should be called immediately after any privilege escalation (like a successful login).
 */
class Session
{
    private array $storage;

    public function __construct(?\SessionHandlerInterface $handler = null, array &$storage = null)
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE && !headers_sent()) {
            if ($handler !== null) {
                session_set_save_handler($handler, true);
            }

            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => isset($_SERVER['HTTPS']),
                'path'     => '/',
            ]);
            session_start();
        }

        if ($storage !== null) {
            $this->storage = &$storage;
        } else {
            if (!isset($_SESSION)) {
                $_SESSION = [];
            }
            $this->storage = &$_SESSION;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->storage[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->storage[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }

    public function remove(string $key): void
    {
        if ($this->has($key)) {
            unset($this->storage[$key]);
        }
    }

    public function all(): array
    {
        return $this->storage;
    }

    public function regenerate(bool $deleteOldSession = true): bool
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            return session_regenerate_id($deleteOldSession);
        }
        return false;
    }

    /**
     * Commits session data and releases the active session lock.
     * 
     * Execution Flow:
     * 1. Check if a session is currently active using `session_status()`.
     * 2. Force PHP to write session data and immediately release the storage lock via `session_write_close()`.
     * 
     * Logic behind the logic:
     * - Native PHP sessions lock the session file to prevent race conditions. If a user 
     *   dispatches concurrent AJAX requests, they will queue sequentially on the server 
     *   unless the lock is explicitly released early. This method provides the mechanism 
     *   to achieve lock-free asynchronous concurrency once session mutations are complete.
     */
    public function save(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Completely terminates the session.
     * 
     * This clears the $_SESSION array, destroys the server-side storage, 
     * and instructs the client to expire the session cookie.
     */
    public function destroy(): void
    {
        $this->storage = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
    }
}

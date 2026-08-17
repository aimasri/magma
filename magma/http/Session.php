<?php

declare(strict_types=1);

namespace Magma\http;

/**
 * Title: Native PHP Session Adapter
 *
 * Purpose:
 * - Provides an object-oriented adapter for native PHP sessions implementing `SessionInterface`.
 * - Centralizes security policies for the session cookie (HttpOnly, SameSite, Secure flags).
 * - Manages session ID regeneration to eliminate session fixation vulnerabilities.
 *
 * Why / Why this design:
 * - Encapsulation & Decoupling: Encapsulating `$_SESSION` and `session_*` functions behind `SessionInterface` ensures application controllers and services remain completely decoupled from the storage medium.
 * - Concurrency Management: Exposes an explicit `save()` method (`session_write_close()`) to release PHP session locks early during long-running or concurrent AJAX workflows.
 *
 * Teaching notes:
 * - `session_regenerate_id(true)` is called upon privilege escalation (e.g. after login) to invalidate old session identifiers and prevent fixation attacks.
 */
class Session implements SessionInterface
{
    private array $storage;

    public function __construct(?\SessionHandlerInterface $handler = null, ?array &$storage = null)
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE && !headers_sent()) {
            if ($handler !== null) {
                session_set_save_handler($handler, true);
            }

            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
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

    public function flash(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
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
     * Commits session data and releases the active session write lock.
     *
     * Execution Flow:
     * 1. Checks if a session is currently active via `session_status()`.
     * 2. Writes session mutations to storage and releases the lock via `session_write_close()`.
     *
     * Logic behind the logic:
     * - Native PHP session file locks prevent concurrent requests from the same user from executing in parallel. Releasing the lock unlocks asynchronous parallelism.
     *
     * @return void
     */
    public function save(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Completely destroys the active session and expires the client cookie.
     *
     * @return void
     */
    public function destroy(): void
    {
        $this->storage = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            session_destroy();
        }
    }
}

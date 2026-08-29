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
    /** @var array<string, mixed> */
    private array $storage;

    /**
     * Initializes the native PHP session adapter.
     *
     * Execution Flow:
     * 1. Checks if a session has already started or headers are sent.
     * 2. Sets secure session cookie parameters if the session is unstarted.
     * 3. Configures a custom save handler if provided.
     * 4. Binds the internal storage reference to `$_SESSION` or the provided array.
     *
     * Logic behind the logic:
     * - The constructor defers session creation to ensure headers aren't prematurely sent, while explicitly configuring security attributes like HttpOnly and SameSite.
     *
     * @param \SessionHandlerInterface|null $handler
     * @param array<string, mixed>|null $storage
     */
    public function __construct(?\SessionHandlerInterface $handler = null, ?array &$storage = null)
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE && !headers_sent()) {
            if ($handler !== null) {
                session_set_save_handler($handler, true);
            }

            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                // @phpstan-ignore-next-line
                'secure'   => isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
                'path'     => '/',
            ]);
            session_start();
        }

        if ($storage !== null) {
            $this->storage = &$storage;
        } else {
            // @phpstan-ignore-next-line
            if (!isset($_SESSION)) {
                // @phpstan-ignore-next-line
                $_SESSION = [];
            }
            // @phpstan-ignore-next-line
            $this->storage = &$_SESSION;
        }
    }

    /**
     * Retrieves a value from the session storage.
     *
     * Logic behind the logic:
     * - Safely falls back to a default value if the key does not exist, avoiding undefined index warnings.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->storage[$key] ?? $default;
    }

    /**
     * Retrieves a value from the session and removes it.
     *
     * Execution Flow:
     * 1. Fetches the value associated with the key.
     * 2. Deletes the key from the session.
     * 3. Returns the fetched value.
     *
     * Logic behind the logic:
     * - Commonly used for "flash messages" (e.g. success/error notifications) that should only be displayed once per user interaction.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function flash(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    /**
     * Stores a value in the session.
     *
     * Logic behind the logic:
     * - Modifies the underlying referenced storage array directly, which in turn modifies `$_SESSION`.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->storage[$key] = $value;
    }

    /**
     * Checks if a key exists in the session.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }

    /**
     * Removes a key from the session.
     *
     * Logic behind the logic:
     * - Safely unsets the value only if it exists to avoid mutating the array unnecessarily.
     *
     * @param string $key
     * @return void
     */
    public function remove(string $key): void
    {
        if ($this->has($key)) {
            unset($this->storage[$key]);
        }
    }

    /**
     * Returns the entire session storage array.
     *
     * Logic behind the logic:
     * - Provides read-only access to all stored session variables for debugging or batch operations.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->storage;
    }

    /**
     * Regenerates the session ID.
     *
     * Execution Flow:
     * 1. Verifies that the current environment is not CLI and a session is active.
     * 2. Calls `session_regenerate_id` to cycle the underlying PHP session ID.
     *
     * Logic behind the logic:
     * - Primarily used upon user privilege escalation (like login) to protect against session fixation attacks. Deleting the old session ensures stolen previous session identifiers cannot be reused.
     *
     * @param bool $deleteOldSession
     * @return bool
     */
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
                $sessionName = session_name();
                if (is_string($sessionName)) {
                    setcookie(
                        $sessionName,
                        '',
                        time() - 42000,
                        $params["path"],
                        $params["domain"],
                        $params["secure"],
                        $params["httponly"]
                    );
                }
            }
            session_destroy();
        }
    }
}

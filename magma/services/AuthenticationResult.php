<?php

declare(strict_types=1);

namespace Magma\services;

use Magma\domain\AuthUser;

/**
 * Title: Authentication Result Value Object
 *
 * Purpose:
 * - Encapsulates the immutable outcome of an authentication or logout operation.
 * - Transports cookie persistence instructions (`cookiesToSet`, `cookiesToClear`) from the domain
 *   service layer back to the HTTP controller layer without coupling services to `Response`.
 *
 * Why / Why this design:
 * - Immutability (`readonly class`): Prevents mid-flight mutation bugs; `withCookie()` and `clearCookie()`
 *   return new `AuthenticationResult` instances with the appended instruction.
 * - Transport Agnosticism: Keeps `AuthenticationService` reusable across HTTP, CLI, and test contexts.
 *
 * Teaching notes:
 * - Because `AuthenticationResult` is a `readonly` Value Object, always capture the returned instance
 *   when chaining `$result = $result->withCookie(...)` or `$result->clearCookie(...)`.
 */
readonly class AuthenticationResult
{
    /**
     * Initializes an immutable authentication result.
     *
     * @param AuthUser|null $user Authenticated domain user when successful, or null on failure/logout.
     * @param array<int, array{name: string, value: string, expiry: int}> $cookiesToSet Cookies to attach to the HTTP response.
     * @param array<int, string> $cookiesToClear Cookie names to expire on the HTTP response.
     */
    public function __construct(
        private ?AuthUser $user = null,
        private array $cookiesToSet = [],
        private array $cookiesToClear = []
    ) {}

    /**
     * Creates a successful authentication result for the given user.
     *
     * @param AuthUser $user
     * @return self
     */
    public static function success(AuthUser $user): self
    {
        return new self($user);
    }

    /**
     * Creates a failed (or logged-out) authentication result.
     *
     * @return self
     */
    public static function failure(): self
    {
        return new self(null);
    }

    /**
     * Indicates whether the authentication attempt succeeded.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->user !== null;
    }

    /**
     * Returns the authenticated user entity, if any.
     *
     * @return AuthUser|null
     */
    public function getUser(): ?AuthUser
    {
        return $this->user;
    }

    /**
     * Returns a new instance with an additional cookie queued to be set on the HTTP response.
     *
     * @param string $name
     * @param string $value
     * @param int $expiry Unix expiration timestamp.
     * @return self
     */
    public function withCookie(string $name, string $value, int $expiry): self
    {
        $cookies = $this->cookiesToSet;
        $cookies[] = [
            'name'   => $name,
            'value'  => $value,
            'expiry' => $expiry,
        ];

        return new self($this->user, $cookies, $this->cookiesToClear);
    }

    /**
     * Returns a new instance with an additional cookie name queued for expiration.
     *
     * @param string $name
     * @return self
     */
    public function clearCookie(string $name): self
    {
        $cleared = $this->cookiesToClear;
        $cleared[] = $name;

        return new self($this->user, $this->cookiesToSet, $cleared);
    }

    /**
     * @return array<int, array{name: string, value: string, expiry: int}>
     */
    public function getCookiesToSet(): array
    {
        return $this->cookiesToSet;
    }

    /**
     * @return array<int, string>
     */
    public function getCookiesToClear(): array
    {
        return $this->cookiesToClear;
    }
}

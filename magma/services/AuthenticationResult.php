<?php

namespace Magma\services;

/**
 * Title: Authentication Result DTO
 *
 * Purpose:
 * - Encapsulate the result of an authentication attempt (success/failure).
 * - Transport persistence instructions (cookies to set or clear) from the domain 
 *   service layer back to the HTTP Controller layer without coupling the domain 
 *   to the HTTP Response object.
 * 
 * Why / Why this design:
 * - Using a dedicated Result object prevents the Controller from having to micromanage 
 *   token rotation and cookie clearing logic. The Service dictates *what* persistence 
 *   needs to happen, and the Controller simply applies it to the HTTP Response.
 * 
 * Teaching notes:
 * - By decoupling this DTO from `core\http\Response`, the domain logic remains 
 *   entirely transport-agnostic. It could theoretically be used in a CLI or WebSocket 
 *   context where cookies are translated into different persistence mechanisms.
 */
class AuthenticationResult
{
    private ?\Magma\domain\AuthUser $user;
    /** @var array<int, array{name: string, value: string, expiry: int}> */
    private array $cookiesToSet = [];
    
    /** @var array<int, string> */
    private array $cookiesToClear = [];

    /**
     * Initializes a new authentication result encapsulating the authenticated user.
     *
     * @param \Magma\domain\AuthUser|null $user
     */
    public function __construct(?\Magma\domain\AuthUser $user = null)
    {
        $this->user = $user;
    }

    /**
     * Factory method to create a successful authentication result.
     *
     * @param \Magma\domain\AuthUser $user
     * @return self
     */
    public static function success(\Magma\domain\AuthUser $user): self
    {
        return new self($user);
    }

    /**
     * Factory method to create a failed authentication result.
     *
     * @return self
     */
    public static function failure(): self
    {
        return new self(null);
    }

    /**
     * Determines whether the authentication attempt was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->user !== null;
    }

    /**
     * Retrieves the authenticated user, if available.
     *
     * @return \Magma\domain\AuthUser|null
     */
    public function getUser(): ?\Magma\domain\AuthUser
    {
        return $this->user;
    }

    /**
     * Queues a cookie to be set in the HTTP response.
     *
     * @param string $name
     * @param string $value
     * @param int $expiry
     * @return self
     */
    public function withCookie(string $name, string $value, int $expiry): self
    {
        $this->cookiesToSet[] = compact('name', 'value', 'expiry');
        return $this;
    }

    /**
     * Queues a cookie to be cleared in the HTTP response.
     *
     * @param string $name
     * @return self
     */
    public function clearCookie(string $name): self
    {
        $this->cookiesToClear[] = $name;
        return $this;
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

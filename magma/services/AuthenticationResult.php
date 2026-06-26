<?php

namespace Magma\services;

/**
 * Authentication Result DTO
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
    private ?array $user;
    private array $cookiesToSet = [];
    private array $cookiesToClear = [];

    public function __construct(?array $user = null)
    {
        $this->user = $user;
    }

    public static function success(array $user): self
    {
        return new self($user);
    }

    public static function failure(): self
    {
        return new self(null);
    }

    public function isSuccessful(): bool
    {
        return $this->user !== null;
    }

    public function getUser(): ?array
    {
        return $this->user;
    }

    public function withCookie(string $name, string $value, int $expiry): self
    {
        $this->cookiesToSet[] = compact('name', 'value', 'expiry');
        return $this;
    }

    public function clearCookie(string $name): self
    {
        $this->cookiesToClear[] = $name;
        return $this;
    }

    public function getCookiesToSet(): array
    {
        return $this->cookiesToSet;
    }

    public function getCookiesToClear(): array
    {
        return $this->cookiesToClear;
    }
}

<?php

namespace Magma\dto;

/**
 * Title: Tenant Data Transfer Object
 * Purpose:
 * - Encapsulates tenant data into a strictly typed, immutable structure.
 * - Transports tenant information across application boundaries (e.g., Repository to Controller).
 * Why/Why this design:
 * - Utilizing readonly properties ensures the data cannot be tampered with after instantiation, promoting safe, predictable state management.
 * - Decouples the presentation and domain layers from the specific database schema or ORM implementation.
 * Teaching notes:
 * - Immutable DTOs represent an industry best practice for preventing side effects in complex applications where data is passed through multiple services.
 */
class TenantDTO
{
    /**
     * Constructs an immutable Tenant Data Transfer Object.
     *
     * Logic behind the logic:
     * - Binds all scalar properties and JSON-like arrays via readonly promotion to guarantee thread-safe state during cross-layer transport.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $tagline,
        public readonly string $email,
        public readonly int $plan_id,
        public readonly string $subscription_status,
        public readonly ?string $billing_cycle_anchor,
        public readonly ?string $payment_gateway_customer_id,
        /** @var array<string, mixed> */
        public readonly array $theme_settings = []
    ) {}
}

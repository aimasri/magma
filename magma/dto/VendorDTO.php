<?php

namespace Magma\dto;

/**
 * Title: Vendor Data Transfer Object
 * Purpose:
 * - Encapsulates vendor data into a strictly typed, immutable structure.
 * - Transports vendor information across application boundaries (e.g., Repository to Controller).
 * Why/Why this design:
 * - Utilizing readonly properties ensures the data cannot be tampered with after instantiation, promoting safe, predictable state management.
 * - Decouples the presentation and domain layers from the specific database schema or ORM implementation.
 * Teaching notes:
 * - Immutable DTOs represent an industry best practice for preventing side effects in complex applications where data is passed through multiple services.
 */
class VendorDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $tagline,
        public readonly string $email,
        public readonly int $plan_id,
        public readonly string $subscription_status,
        public readonly ?string $billing_cycle_anchor,
        public readonly ?string $payment_gateway_customer_id,
        public readonly array $theme_settings = []
    ) {}
}

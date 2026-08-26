<?php

declare(strict_types=1);

namespace Magma\interfaces\cqrs;

/**
 * Title: Vendor Command Interface
 * Purpose:
 * - Defines the contract for all write operations (commands) related to Vendor entities.
 * - Handles creating, updating, and deleting vendors.
 * Why/Why this design:
 * - Enforces the CQRS (Command Query Responsibility Segregation) pattern by strictly separating write interfaces from read interfaces.
 * - Allows different implementations (e.g., event-sourced vs CRUD) without affecting the domain logic.
 * Teaching notes:
 * - Interface Segregation Principle (ISP) at work. Clients that only need to read vendors shouldn't depend on an interface that includes write methods.
 */
interface VendorCommandInterface extends \Magma\interfaces\cqrs\CommandInterface
{
    /**
     * @param array<string, mixed> $data
     * @return bool
     */
    public function create(array $data): bool;

    /**
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}

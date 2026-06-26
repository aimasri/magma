<?php

namespace Magma\dto;

/**
 * Pagination DTO
 * 
 * Purpose:
 * - Securely transfer calculated pagination values between the service layer, 
 *   controllers, and repositories.
 * 
 * Why / Why this design:
 * - Using a Data Transfer Object ensures that limit and offset values are strictly typed
 *   and immutable once calculated, preventing accidental manipulation during request flow.
 * 
 * Teaching notes:
 * - Readonly properties (PHP 8.1+) ensure immutability without the boilerplate of getters.
 */
class PaginationDTO
{
    public readonly int $limit;
    public readonly ?int $lastId;

    public function __construct(int $limit, ?int $lastId = null)
    {
        $this->limit = $limit;
        $this->lastId = $lastId;
    }
}

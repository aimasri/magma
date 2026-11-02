<?php

declare(strict_types=1);

namespace Magma\dto;

/**
 * Title: Pagination DTO
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
    /**
     * Title: Initialize Pagination DTO
     *
     * Purpose:
     * - Captures the pagination constraints (limit and keyset boundary parameters) for a query.
     * 
     * Logic behind the logic:
     * - By enforcing strictly typed properties via constructor injection, we prevent malformed 
     *   pagination data (like negative limits) from propagating down to the repository layer.
     * 
     * @param int $limit Number of records to fetch.
     * @param int|null $lastId Primary ID cursor boundary.
     * @param string|int|null $lastCursor Optional non-id column cursor boundary (e.g. created_at).
     * @param int|null $tieBreakerId Optional secondary id tie-breaker for duplicate cursor column values.
     */
    public function __construct(
        public readonly int $limit,
        public readonly ?int $lastId = null,
        public readonly string|int|null $lastCursor = null,
        public readonly ?int $tieBreakerId = null
    ) {}
}

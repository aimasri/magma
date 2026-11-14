<?php

declare(strict_types=1);

namespace Magma\dto;

/**
 * Title: Pagination DTO
 *
 * Purpose:
 * - Transfers validated keyset cursor pagination parameters (`limit`, `lastId`, `lastCursor`, `tieBreakerId`)
 *   between services, controllers, and CQRS query repositories.
 *
 * Why / Why this design:
 * - `readonly class` guarantees immutability across query builders without getter boilerplate while
 *   decoupling repositories from raw HTTP query strings.
 *
 * Teaching notes:
 * - Cursor (`id < :last_id` or composite `(created_at, id)`) pagination delivers `O(1)` index scans
 *   regardless of page depth, avoiding PostgreSQL `OFFSET` linear scan degradation.
 */
readonly class PaginationDTO
{
    /**
     * @param int $limit Maximum number of rows to fetch.
     * @param int|null $lastId Primary integer ID cursor boundary.
     * @param string|int|null $lastCursor Optional secondary sort column cursor boundary.
     * @param int|null $tieBreakerId Optional tie-breaker ID when sorting by non-unique columns.
     */
    public function __construct(
        public int $limit,
        public ?int $lastId = null,
        public string|int|null $lastCursor = null,
        public ?int $tieBreakerId = null
    ) {}
}

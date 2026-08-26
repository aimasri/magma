<?php

namespace Magma\services;

use Magma\dto\PaginationDTO;

/**
 * Title: Pagination Service
 *
 * Purpose:
 * - Centralizes pagination math.
 * 
 * Why / Why this design:
 * - Prevents DRY violations across multiple controllers. By injecting this service,
 *   we ensure that limit/offset logic is calculated identically everywhere.
 * 
 * Teaching notes:
 * - The `$allowUserOverride` toggle gives developers granular control. On public pages, 
 *   we might lock the limit to prevent scraping or excessive memory consumption. On 
 *   admin tables, we might let the user choose to see 100 items per page via the query string.
 */
class PaginationService
{
    /**
     * Calculates pagination bounds.
     *
     * @param int|null $lastId
     * @param int|null $reqLimit
     * @param int $defaultLimit
     * @param bool $allowUserOverride
     * @param int $maxLimit
     * @return PaginationDTO
     */
    public function getPagination(
        ?int $lastId, 
        ?int $reqLimit,
        int $defaultLimit = 20, 
        bool $allowUserOverride = false, 
        int $maxLimit = 100
    ): PaginationDTO {
        // Either strictly use the default, or let the user choose (up to a safe max)
        $limit = $allowUserOverride && $reqLimit !== null
            ? min($reqLimit, $maxLimit) 
            : $defaultLimit;
        
        // Ensure minimum boundary
        $limit = max(1, $limit);

        return new PaginationDTO($limit, $lastId);
    }
}

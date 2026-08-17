<?php

namespace Magma\services;

use Magma\http\Request;
use Magma\dto\PaginationDTO;

/**
 * Title: Pagination Service
 *
 * Purpose:
 * - Centralizes pagination math and request parsing.
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
     * Parses the request and calculates pagination bounds.
     *
     * @param Request $request
     * @param int $defaultLimit
     * @param bool $allowUserOverride
     * @param int $maxLimit
     * @return PaginationDTO
     */
    public function getPagination(
        Request $request, 
        int $defaultLimit = 20, 
        bool $allowUserOverride = false, 
        int $maxLimit = 100
    ): PaginationDTO {
        $lastId = $request->query('last_id');
        if ($lastId !== null) {
            $lastId = (int)$lastId;
        }
        
        // Either strictly use the default, or let the user choose (up to a safe max)
        $limit = $allowUserOverride 
            ? min((int)$request->query('limit', $defaultLimit), $maxLimit) 
            : $defaultLimit;
        
        // Ensure minimum boundary
        $limit = max(1, $limit);

        return new PaginationDTO($limit, $lastId);
    }
}

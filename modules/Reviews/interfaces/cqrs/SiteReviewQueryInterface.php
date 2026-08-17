<?php

declare(strict_types=1);

namespace Modules\Reviews\interfaces\cqrs;

/**
 * Title: Site Review Query Interface
 *
 * Purpose:
 * - Defines the contract for read operations (queries) related to site reviews.
 *
 * Why / Why this design:
 * - Implements CQRS by isolating read pathways from write commands.
 * - Allows for highly optimized read operations (e.g., caching, read replicas) without affecting business logic for writes.
 *
 * Teaching notes:
 * - Implementations should only return data and must not cause any side effects or mutate state.
 */
interface SiteReviewQueryInterface
{
    /**
     * Retrieves a paginated list of approved reviews.
     *
     * @param int $limit The maximum number of reviews to retrieve.
     * @param int|null $lastId The ID of the last review seen, used for cursor-based pagination.
     * @return iterable A collection of approved ReviewDTOs.
     */
    public function getApprovedReviews(int $limit = 20, ?int $lastId = null): iterable;
}

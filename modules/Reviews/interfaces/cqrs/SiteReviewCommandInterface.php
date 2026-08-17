<?php

declare(strict_types=1);

namespace Modules\Reviews\interfaces\cqrs;

use Modules\Reviews\domain\Review;

/**
 * Title: Site Review Command Interface
 *
 * Purpose:
 * - Defines the contract for write operations (commands) related to site reviews.
 *
 * Why / Why this design:
 * - Implements CQRS by separating the write (command) operations from read (query) operations.
 * - Enforces loose coupling and allows for specific optimization of write pathways.
 *
 * Teaching notes:
 * - All implementations must handle data persistence. Do not include query/retrieval methods here.
 */
interface SiteReviewCommandInterface
{
    /**
     * Persists a new site review to the underlying storage.
     *
     * @param Review $review The domain entity representing the review.
     * @return bool True if successfully persisted, false otherwise.
     */
    public function addReview(Review $review): bool;
}

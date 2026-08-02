<?php

namespace Magma\models;

/**
 * Site Review Repository Interface
 *
 * Purpose:
 * - Defines the contract for fetching and submitting site reviews.
 *
 * Why / Why this design:
 * - Dependency Inversion Principle: Ensures controllers and services depend 
 *   on an abstraction rather than the concrete SQL implementation, enabling easy mocking 
 *   during unit testing.
 *
 * Teaching notes:
 * - Notice how the method signatures use type hints and return types. This is critical 
 *   in PHP to guarantee that any concrete implementation respects the established contract.
 */
interface SiteReviewRepositoryInterface
{
    /**
     * Retrieves approved testimonials.
     *
     * Purpose:
     * - Fetches a paginated set of approved site reviews for public display.
     *
     * Logic behind the logic:
     * - Requires callers to supply pagination limits to prevent memory exhaustion when querying large tables.
     *
     * @param int $limit
     * @param int|null $lastId
     * @return iterable<\Magma\dto\ReviewDTO>
     */
    public function getApprovedReviews(int $limit = 20, ?int $lastId = null): iterable;

    /**
     * Adds a new review with a 'pending' status.
     *
     * Purpose:
     * - Accepts a new user-submitted review and stores it awaiting moderation.
     *
     * Logic behind the logic:
     * - The interface enforces passing a domain entity (`Review`) rather than an array, ensuring
     *   that all data is validated and typed before reaching the repository layer.
     *
     * @param \Magma\domain\Review $review
     * @return bool
     */
    public function addReview(\Magma\domain\Review $review): bool;
}

<?php

namespace Modules\Reviews\repositories;

use Modules\Reviews\interfaces\cqrs\SiteReviewCommandInterface;
use Magma\models\AbstractCommandRepository;
use Modules\Reviews\domain\Review;

/**
 * Title: Site Review Command Repository
 *
 * Purpose:
 * - Handles persistence (write operations) for site reviews.
 *
 * Why / Why this design:
 * - Implements the Command part of CQRS to segregate write models from read models.
 *
 * Teaching notes:
 * - The repository relies on the Domain entity `Review` to supply structured data
 *   and constants, preventing magic strings in SQL queries.
 */
class SiteReviewCommandRepository extends AbstractCommandRepository implements SiteReviewCommandInterface
{
    /**
     * Inserts a new review record into the site_reviews table.
     *
     * Execution Flow:
     * 1. Prepares an INSERT statement for the site_reviews table.
     * 2. Binds values from the provided Review domain entity.
     * 3. Executes the query and returns the success status.
     *
     * Logic behind the logic:
     * - Using PDO prepared statements with named parameters prevents SQL injection attacks.
     * - Relying on the Review entity ensures only valid, pre-formatted data reaches the database.
     *
     * @param Review $review The review domain entity to persist.
     * @return bool True on successful insertion.
     */
    public function addReview(Review $review): bool
    {
        $sql = "INSERT INTO site_reviews (author, comment, rating, status) 
                VALUES (:author, :comment, :rating, :status)";
        $stmt = $this->getDb()->prepare($sql);
        return $stmt->execute([
            'author'  => $review->getAuthor(),
            'comment' => $review->getComment(),
            'rating'  => $review->getRating(),
            'status'  => $review->getStatus()
        ]);
    }
}

<?php

namespace Magma\models;

use Magma\dto\ReviewDTO;

/**
 * Site Review Data Access
 *
 * Purpose:
 * - Provide methods to fetch approved reviews and insert new pending reviews.
 * - Manage SQL operations against the `site_reviews` table.
 *
 * Why / Why this design:
 * - Centralizing these queries prevents SQL from leaking into the `ReviewAggregatorService` 
 *   or the controllers, ensuring that data validation and moderation statuses are consistently 
 *   applied application-wide.
 *
 * Teaching notes:
 * - The `getApprovedReviews()` method hardcodes the `status = 'approved'` filter to ensure 
 *   unmoderated content is never accidentally queried and displayed on the frontend.
 */
class SiteReviewRepository extends BaseRepository implements SiteReviewRepositoryInterface
{


    /**
     * Retrieves approved testimonials from the database.
     * 
     * Execution Flow:
     * 1. Execute a query filtering by the `approved` status.
     * 2. Order the results by creation date in descending order (newest first).
     * 3. Fetch the results iteratively and `yield` each mapped ReviewDTO.
     * 
     * Logic behind the logic:
     * - Hardcoding the status check here guarantees that developers cannot forget to 
     *   apply the moderation filter when fetching reviews for the public UI.
     * - Utilizing a generator (`yield`) instead of `fetchAll()` maintains O(1) memory 
     *   complexity, preventing server crashes when the review table grows exponentially.
     *
     * @param int $limit Maximum number of reviews to return.
     * @param int $offset Number of reviews to skip.
     * @return iterable<\Magma\dto\ReviewDTO> An iterable of ReviewDTO objects.
     */
    public function getApprovedReviews(int $limit = 20, ?int $lastId = null): iterable
    {
        $sql = "SELECT id, comment, author, rating FROM site_reviews WHERE status = 'approved'";
        if ($lastId !== null) {
            $sql .= " AND id < :last_id";
        }
        $sql .= " ORDER BY id DESC LIMIT :limit";
        
        $stmt = $this->dbRead->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        if ($lastId !== null) {
            $stmt->bindValue(':last_id', $lastId, \PDO::PARAM_INT);
        }
        $stmt->execute();
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield new ReviewDTO(
                author: $row['author'],
                comment: $row['comment'],
                rating: (int)$row['rating'],
                id: (int)$row['id']
            );
        }
    }

    /**
     * Adds a new review to the database with a 'pending' status.
     *
     * Purpose:
     * - Allows public users to submit reviews that require administrative approval 
     *   before appearing on the live site.
     *
     * Execution Flow:
     * 1. Prepares the INSERT statement with a hardcoded 'pending' status.
     * 2. Executes the query using the Write connection to ensure the master database 
     *    receives the new record.
     *
     * Logic behind the logic:
     * - Hardcoding 'pending' within the SQL query prevents malicious users from 
     *   bypassing moderation by injecting a manipulated 'status' field in their POST request.
     *
     * @param \Magma\domain\Review $review The encapsulated review entity.
     * @return bool True if the insert was successful.
     */
    public function addReview(\Magma\domain\Review $review): bool
    {
        $sql = "INSERT INTO site_reviews (author, comment, rating, status) 
                VALUES (:author, :comment, :rating, 'pending')";
        $stmt = $this->dbWrite->prepare($sql);
        return $stmt->execute([
            'author'  => $review->getAuthor(),
            'comment' => $review->getComment(),
            'rating'  => $review->getRating()
        ]);
    }
}
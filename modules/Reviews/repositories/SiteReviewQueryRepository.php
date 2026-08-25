<?php

namespace Modules\Reviews\repositories;

use Modules\Reviews\interfaces\cqrs\SiteReviewQueryInterface;
use Magma\models\AbstractQueryRepository;
use Modules\Reviews\dto\ReviewDTO;
use Modules\Reviews\domain\Review;

/**
 * Title: Site Review Query Repository
 *
 * Purpose:
 * - Fetches approved reviews from the database for display purposes.
 *
 * Why / Why this design:
 * - CQRS Separation: Isolates read-only presentation queries from the command logic.
 * - Keyset Pagination: Uses `id < :last_id` for constant-time cursor pagination.
 *
 * Teaching notes:
 * - Always bind domain constants like `Review::STATUS_APPROVED` instead of hardcoding strings.
 * - Yields DTOs via a Generator to keep memory footprint minimal.
 */
class SiteReviewQueryRepository extends AbstractQueryRepository implements SiteReviewQueryInterface
{
    /**
     * Fetches a paginated list of approved reviews from the database.
     *
     * Execution Flow:
     * 1. Constructs the base SELECT query targeting approved reviews.
     * 2. Appends keyset pagination conditions if a last ID is provided.
     * 3. Binds parameters safely and executes the query.
     * 4. Yields each row as a ReviewDTO via a generator.
     *
     * Logic behind the logic:
     * - Using generators (`yield`) drastically reduces memory consumption for large result sets.
     * - Keyset pagination (`id < :last_id`) offers consistent performance regardless of dataset size, avoiding the offset penalty.
     *
     * @param int $limit Maximum number of records to return.
     * @param int|null $lastId The ID of the last seen record for cursor pagination.
     * @return iterable Generator yielding ReviewDTO objects.
     */
    public function getApprovedReviews(int $limit = 20, ?int $lastId = null): iterable
    {
        $tenantId = $this->getTenantId();
        if ($tenantId === null) {
            throw new \RuntimeException('Tenant context is required for querying site reviews.');
        }

        $sql = "SELECT id, comment, author, rating FROM site_reviews WHERE status = :status AND tenant_id = :tenant_id";
        if ($lastId !== null) {
            $sql .= " AND id < :last_id";
        }
        $sql .= " ORDER BY id DESC LIMIT :limit";
        
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':status', Review::STATUS_APPROVED, \PDO::PARAM_STR);
        $stmt->bindValue(':tenant_id', $tenantId, \PDO::PARAM_INT);
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
}

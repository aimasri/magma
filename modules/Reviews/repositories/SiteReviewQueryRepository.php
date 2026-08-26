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
    public const TABLE_NAME = 'site_reviews';

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
     * @param int $tenantId The tenant ID.
     * @param int $limit Maximum number of records to return.
     * @param int|null $lastId The ID of the last seen record for cursor pagination.
     * @return iterable Generator yielding ReviewDTO objects.
     */
    public function getApprovedReviews(int $tenantId, int $limit = SiteReviewQueryInterface::DEFAULT_LIMIT, ?int $lastId = null): iterable
    {

        $pagination = new \Magma\dto\PaginationDTO(limit: $limit, lastId: $lastId);
        
        $sql = "SELECT id, comment, author, rating FROM \"" . self::TABLE_NAME . "\" WHERE status = :status AND tenant_id = :tenant_id";
        $params = [
            ':status' => Review::STATUS_APPROVED,
            ':tenant_id' => $tenantId
        ];
        
        $result = $this->cursorPaginate($sql, $params, $pagination, 'id', 'DESC');
        
        foreach ($result['items'] as $row) {
            yield new ReviewDTO(
                author: $row['author'],
                comment: $row['comment'],
                rating: (int)$row['rating'],
                id: (int)$row['id']
            );
        }
    }
}

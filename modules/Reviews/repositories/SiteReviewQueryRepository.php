<?php

namespace Modules\Reviews\repositories;

use Modules\Reviews\interfaces\cqrs\SiteReviewQueryInterface;
use Magma\database\BaseQueryRepository;
use Modules\Reviews\dto\ReviewDTO;

class SiteReviewQueryRepository extends BaseQueryRepository implements SiteReviewQueryInterface
{
    public function getApprovedReviews(int $limit = 20, ?int $lastId = null): iterable
    {
        $sql = "SELECT id, comment, author, rating FROM site_reviews WHERE status = 'approved'";
        if ($lastId !== null) {
            $sql .= " AND id < :last_id";
        }
        $sql .= " ORDER BY id DESC LIMIT :limit";
        
        $stmt = $this->getDb()->prepare($sql);
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

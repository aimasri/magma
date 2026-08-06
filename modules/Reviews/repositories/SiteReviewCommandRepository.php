<?php

namespace Modules\Reviews\repositories;

use Modules\Reviews\interfaces\cqrs\SiteReviewCommandInterface;
use Magma\database\BaseCommandRepository;
use Modules\Reviews\domain\Review;

class SiteReviewCommandRepository extends BaseCommandRepository implements SiteReviewCommandInterface
{
    public function addReview(Review $review): bool
    {
        $sql = "INSERT INTO site_reviews (author, comment, rating, status) 
                VALUES (:author, :comment, :rating, 'pending')";
        $stmt = $this->getDb()->prepare($sql);
        return $stmt->execute([
            'author'  => $review->getAuthor(),
            'comment' => $review->getComment(),
            'rating'  => $review->getRating()
        ]);
    }
}

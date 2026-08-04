<?php

namespace Magma\interfaces\cqrs;

interface SiteReviewQueryInterface extends \Magma\database\QueryInterface
{
    public function getApprovedReviews(int $limit = 20, ?int $lastId = null): iterable;
}

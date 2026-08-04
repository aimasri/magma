<?php

declare(strict_types=1);

namespace Magma\interfaces\cqrs;

interface SiteReviewCommandInterface extends \Magma\database\CommandInterface
{
    public function addReview(\Magma\domain\Review $review): bool;
}

<?php

declare(strict_types=1);

namespace Modules\Reviews\interfaces;

use Modules\Reviews\domain\Review;

/**
 * Title: Review Factory Interface
 *
 * Purpose:
 * - Defines the contract for instantiating Review domain entities.
 */
interface ReviewFactoryInterface
{
    /**
     * Creates a new Review entity.
     */
    public function create(int $tenantId, string $author, string $comment, int $rating): Review;
}

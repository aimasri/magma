<?php

declare(strict_types=1);

namespace Modules\Reviews\interfaces;

use Modules\Reviews\domain\Review;

/**
 * Title: Review Factory Interface
 *
 * Purpose:
 * - Defines the contract for instantiating Review domain entities.
 *
 * Why this design:
 * - Employs the Factory Pattern. It offloads the complex responsibility of constructing a valid entity (including ID generation or date-time stamping) from the Application Services, keeping the instantiation logic encapsulated and DRY.
 *
 * Teaching notes:
 * - Inject this factory into your controllers or services rather than using the `new` keyword to create Reviews manually.
 */
interface ReviewFactoryInterface
{
    /**
     * Creates a new Review entity.
     */
    public function create(int $tenantId, string $author, string $comment, int $rating): Review;
}

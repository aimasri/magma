<?php

declare(strict_types=1);

namespace Modules\Reviews\domain;

/**
 * Title: Review Factory
 *
 * Purpose:
 * - Encapsulates the instantiation of the Review domain entity.
 *
 * Why / Why this design:
 * - A factory avoids instantiating domain models directly using the `new` keyword 
 *   within services, complying with strict Dependency Inversion Principles.
 *
 * Teaching notes:
 * - Domain entity factories are excellent places to hook in standard domain events (e.g. `ReviewCreatedEvent`) without cluttering your core entity logic.
 */
class ReviewFactory implements \Modules\Reviews\interfaces\ReviewFactoryInterface
{
    /**
     * Creates a new Review entity.
     */
    public function create(int $tenantId, string $author, string $comment, int $rating): Review
    {
        return new Review($tenantId, $author, $comment, $rating);
    }
}

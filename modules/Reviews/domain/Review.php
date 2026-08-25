<?php

declare(strict_types=1);

namespace Modules\Reviews\domain;

use App\constants\AppConstants;

/**
 * Title: Review Domain Entity
 *
 * Purpose:
 * - Encapsulate the core data and behavior of a user-submitted review.
 * - Centralize business rules, data formatting, and default value assignment 
 *   for new reviews.
 *
 * Why / Why this design:
 * - Domain-Driven Design (DDD): By moving data mapping and default values from 
 *   the `ReviewSubmissionService` into the `Review` entity, behavior is kept 
 *   with the data. This creates a "skinny" entity that orchestrators can rely on, 
 *   ensuring that no malformed reviews can be instantiated.
 * - Strictly typed properties ensure we avoid "magic" and implicit state.
 *
 * Teaching notes:
 * - Entities in this framework must never query the database directly. 
 *   They only hold and validate internal state.
 * - The constructor receives the required scalar values, applies 
 *   defaults, and strictly types the resulting properties.
 */
readonly class Review
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    private string $author;
    private string $comment;
    private int $rating;
    private string $status;

    /**
     * Constructs a new Review entity.
     *
     * Execution Flow:
     * 1. Extracts the author, comment, and rating values.
     * 2. Sets default pending status.
     *
     * Logic behind the logic:
     * - By enforcing defaults and type casting upon instantiation, we guarantee that 
     *   all subsequent layers (services, repositories) deal with a valid, predictable object 
     *   state.
     *
     * @param string $author The review author.
     * @param string $comment The review body.
     * @param int $rating The numeric rating.
     */
    public function __construct(string $author, string $comment, int $rating)
    {
        $this->author = $author;
        $this->comment = $comment;
        $this->rating = $rating;
        $this->status = self::STATUS_PENDING;
    }

    /**
     * Retrieves the review author.
     *
     * @return string
     */
    public function getAuthor(): string
    {
        return $this->author;
    }

    /**
     * Retrieves the review comment body.
     *
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Retrieves the numeric rating.
     *
     * @return int
     */
    public function getRating(): int
    {
        return $this->rating;
    }

    /**
     * Retrieves the review status.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }
}

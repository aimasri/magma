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
class Review
{
    public const MIN_RATING = 1;
    public const MAX_RATING = 5;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    private ?int $id;
    private int $tenantId;
    private string $author;
    private string $comment;
    private int $rating;
    private string $status;

    /**
     * Constructs a new Review entity.
     *
     * Execution Flow:
     * 1. Extracts the author, comment, and rating values.
     * 2. Validates domain invariants.
     * 3. Sets default pending status.
     *
     * Logic behind the logic:
     * - By enforcing defaults and type casting upon instantiation, we guarantee that 
     *   all subsequent layers (services, repositories) deal with a valid, predictable object 
     *   state.
     *
     * @param int $tenantId The tenant ID.
     * @param string $author The review author.
     * @param string $comment The review body.
     * @param int $rating The numeric rating.
     * @param int|null $id Optional entity ID for hydrated records.
     * @param string $status Optional status (defaults to pending).
     * @throws \DomainException
     */
    public function __construct(int $tenantId, string $author, string $comment, int $rating, ?int $id = null, string $status = self::STATUS_PENDING)
    {
        if ($rating < self::MIN_RATING || $rating > self::MAX_RATING) {
            throw new \DomainException("Review rating must be between " . self::MIN_RATING . " and " . self::MAX_RATING . ".");
        }

        $this->tenantId = $tenantId;
        $this->id = $id;
        $this->author = $author;
        $this->comment = $comment;
        $this->rating = $rating;
        $this->status = $status;
    }

    /**
     * Retrieves the tenant ID.
     *
     * @return int
     */
    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    /**
     * Retrieves the review ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Sets the review ID.
     *
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
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

    /**
     * Sets the review status.
     *
     * @param string $status
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }
}

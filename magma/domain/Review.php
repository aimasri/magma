<?php

declare(strict_types=1);

namespace Magma\domain;

/**
 * Review Domain Entity
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
 * - The constructor receives the raw array, extracts what it needs, applies 
 *   defaults, and strictly types the resulting properties.
 */
readonly class Review
{
    private string $author;
    private string $comment;
    private int $rating;

    /**
     * Constructs a new Review entity.
     *
     * Execution Flow:
     * 1. Extracts the raw author and comment strings, defaulting to empty strings if missing.
     * 2. Extracts the rating and casts it to an integer, defaulting to 5.
     *
     * Logic behind the logic:
     * - By enforcing defaults and type casting upon instantiation, we guarantee that 
     *   all subsequent layers (services, repositories) deal with a valid, predictable object 
     *   state.
     *
     * @param array $data The raw input array, typically from a POST request.
     */
    public function __construct(array $data)
    {
        $this->author = $data['author'] ?? '';
        $this->comment = $data['comment'] ?? '';
        $this->rating = (int)($data['rating'] ?? 5);
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
}

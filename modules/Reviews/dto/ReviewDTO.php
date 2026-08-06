<?php

namespace Modules\Reviews\dto;

/**
 * Review Data Transfer Object
 *
 * Purpose:
 * - Standardizes the shape of a review regardless of its origin (SQL, XML, API).
 * - Implements JsonSerializable to ensure frontend JavaScript receives the exact 
 *   JSON structure it expects when reviews are encoded.
 *
 * Why / Why this design:
 * - Prevents arrays with arbitrary keys from leaking into the domain layer. 
 *   By using a strongly-typed class, we gain IDE autocomplete, type safety, 
 *   and guarantee that the data structure is consistent.
 *
 * Teaching notes:
 * - Readonly properties (PHP 8.1+) make this DTO immutable, which is a best 
 *   practice for data transfer objects. Once created, a review's data should 
 *   not change in transit.
 */
class ReviewDTO implements \JsonSerializable
{
    /**
     * Initializes the DTO with strictly typed, immutable properties.
     * 
     * Execution Flow:
     * 1. Accept the required author, comment, and rating values.
     * 2. Store them as readonly properties.
     * 
     * Logic behind the logic:
     * - Using PHP 8.1 readonly properties eliminates the need for getters 
     *   and setters, keeping the DTO clean while enforcing immutability.
     */
    public function __construct(
        public readonly string $author,
        public readonly string $comment,
        public readonly int $rating,
        public readonly ?int $id = null
    ) {}

    /**
     * Serializes the DTO into an associative array for JSON encoding.
     * 
     * Execution Flow:
     * 1. Map the DTO properties to a standard associative array.
     * 2. Return the array to the json_encode() caller.
     * 
     * Logic behind the logic:
     * - Implementing \JsonSerializable allows us to pass an array of these DTOs 
     *   directly to `json_encode` in the view, guaranteeing the frontend 
     *   receives the exact property names it expects.
     */
    public function jsonSerialize(): array
    {
        return [
            'id'      => $this->id,
            'author'  => $this->author,
            'comment' => $this->comment,
            'rating'  => $this->rating,
        ];
    }
}

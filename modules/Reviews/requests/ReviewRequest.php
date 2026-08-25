<?php

namespace Modules\Reviews\requests;


use Magma\validation\FormRequest;
use Modules\Reviews\dto\ReviewDTO;

/**
 * Title: Review Request
 *
 * Purpose:
 * - Ensure submissions include author, comment and numeric rating before
 *   they are stored as pending moderation entries.
 *
 * Why / Why this design:
 * - Enforces the "Skinny Controller" principle by moving validation logic out of 
 *   the HTTP handlers and into dedicated request objects.
 *
 * Teaching notes:
 * - This architecture mimics modern frameworks (like Laravel), promoting high 
 *   reusability and clean separation of concerns.
 */
class ReviewRequest extends FormRequest
{
    /**
     * Define the validation rules for submitting a customer review.
     *
     * Execution Flow:
     * 1. Evaluates 'author' field for presence and minimum length.
     * 2. Validates 'comment' for length constraints to prevent spam or excessively large payloads.
     * 3. Checks 'rating' bounds (1 to 5) to maintain statistical integrity.
     *
     * Logic behind the logic:
     * Setting a hard maximum on text fields like 'comment' mitigates denial-of-service (DoS) vectors via payload bloating and prevents database truncation exceptions.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'author'  => 'required|min:2',
            'comment' => 'required|min:5|max:1000',
            'rating'  => 'required|numeric|min:1|max:5'
        ];
    }



    /**
     * Converts validated request data into a Review DTO.
     *
     * Execution Flow:
     * 1. Retrieves request data.
     * 2. Maps the properties directly to the strongly-typed DTO.
     *
     * Logic behind the logic:
     * The controller does not have to deal with raw arrays anymore.
     *
     * @return ReviewDTO
     */
    public function toDTO(): ReviewDTO
    {
        $data = $this->request->request();
        return new ReviewDTO(
            author: $data['author'] ?? '',
            comment: $data['comment'] ?? '',
            rating: (int)($data['rating'] ?? 5)
        );
    }
}

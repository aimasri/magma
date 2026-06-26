<?php

namespace Magma\requests;


use Magma\validation\FormRequest;

/**
 * ReviewRequest — validation for customer reviews.
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
    public function rules(): array
    {
        return [
            'author'  => 'required|min:2',
            'comment' => 'required|min:5|max:1000',
            'rating'  => 'required|numeric|min:1|max:5'
        ];
    }
}
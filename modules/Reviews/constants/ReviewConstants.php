<?php

declare(strict_types=1);

namespace Modules\Reviews\constants;

/**
 * Title: Review Module Constants
 *
 * Purpose:
 * - Centralizes static string literals, validation rules, and configuration flags specific to the Reviews module.
 *
 * Why this design:
 * - Prevents "magic strings" from being scattered across controllers and services, drastically reducing typo-induced bugs and making system-wide changes (like adjusting the max rating length) a single-line fix.
 *
 * Teaching notes:
 * - These constants are exclusively for the Reviews domain. Do not place framework-wide configuration keys here.
 */
class ReviewConstants
{
    public const MSG_REVIEW_SUBMITTED = 'Review Submitted! Thank you. Your review is now pending moderation.';

    public const RULE_REVIEW_AUTHOR = 'required|min:2';
    public const RULE_REVIEW_COMMENT = 'required|min:5|max:1000';
    public const RULE_REVIEW_RATING = 'required|numeric|min:1|max:5';
}

<?php

declare(strict_types=1);

namespace Modules\Reviews\constants;

/**
 * Title: Review Module Constants
 */
class ReviewConstants
{
    public const MSG_REVIEW_SUBMITTED = 'Review Submitted! Thank you. Your review is now pending moderation.';

    public const RULE_REVIEW_AUTHOR = 'required|min:2';
    public const RULE_REVIEW_COMMENT = 'required|min:5|max:1000';
    public const RULE_REVIEW_RATING = 'required|numeric|min:1|max:5';
}

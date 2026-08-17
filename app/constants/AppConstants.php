<?php

namespace App\constants;

/**
 * Title: App Constants
 *
 * Purpose:
 * - Centralize all configuration and session magic strings to avoid typos and scattered dependencies.
 *
 * Why / Why this design:
 * - DRY principle. Avoid hardcoded string literals across controllers.
 * - Improves refactorability.
 *
 * Teaching notes:
 * - Using constants makes code cleaner and autocomplete-friendly.
 */
class AppConstants
{
    public const ENV_APP_ENV = 'APP_ENV';
    public const ENV_APP_DEBUG = 'APP_DEBUG';
    public const ENV_DB_DRIVER = 'DB_DRIVER';
    public const SESSION_SUCCESS_MESSAGE = 'success_message';
    public const ENV_DEFAULT_DEVELOPMENT = 'development';
    public const DB_DEFAULT_PGSQL = 'pgsql';
    public const HOME_TITLE = 'Magma Framework Core';
    public const MEGABYTE_IN_BYTES = 1024 * 1024;

    public const REVIEW_STATUS_PENDING = 'pending';
    public const REVIEW_STATUS_APPROVED = 'approved';
    public const REVIEW_STATUS_REJECTED = 'rejected';

    public const MSG_REVIEW_SUBMITTED = 'Review Submitted! Thank you. Your review is now pending moderation.';

    public const RULE_REVIEW_AUTHOR = 'required|min:2';
    public const RULE_REVIEW_COMMENT = 'required|min:5|max:1000';
    public const RULE_REVIEW_RATING = 'required|numeric|min:1|max:5';
}

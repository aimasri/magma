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
    public const ENV_DEFAULT_DEBUG = true;
    public const DB_DEFAULT_PGSQL = 'pgsql';
    public const HOME_TITLE = 'Core Engine';
    public const SYLLABUS_TITLE = 'Architectural Syllabus';
    public const MEGABYTE_IN_BYTES = 1024 * 1024;
}

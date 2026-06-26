<?php

namespace Magma\routing;

/**
 * Route Not Found Exception
 *
 * Purpose:
 * - Specific exception thrown when the router cannot match an incoming HTTP request
 *   to any defined route or valid HTTP method.
 *
 * Why / Why this design:
 * - Distinguishing a 404 error from a general \RuntimeException allows the global ErrorHandler
 *   (or the Router itself) to cleanly catch routing failures and display a user-friendly 404 page
 *   without triggering a 500 Internal Server Error trace or spamming the application logs.
 *
 * Teaching notes:
 * - In robust systems, expected "not found" events should always use specific typed Exceptions.
 *   This ensures error monitoring tools (like Sentry) don't flag missing URLs as critical application crashes.
 */
class RouteNotFoundException extends \RuntimeException
{
}

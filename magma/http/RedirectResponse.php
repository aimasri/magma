<?php

namespace Magma\http;

/**
 * Title: Redirect Response
 *
 * Purpose:
 * - Represents HTTP redirects as objects.
 * - Allows controllers to return a redirect without emitting headers directly.
 * 
 * Why this design:
 * - Standardizes redirects into the `Response` object lifecycle. This prevents premature `header()` emissions which break middleware execution and testing.
 * 
 * Teaching notes:
 * - By extending `Response`, a `RedirectResponse` can bubble backwards up through the middleware pipeline, allowing global logic (like session serialization) to run.
 */
class RedirectResponse extends Response
{
    /**
     * Initializes the redirect response with a target URL and HTTP status code.
     * 
     * Logic behind the logic:
     * - Passes the URL to the parent constructor as a `Location` header to leverage the existing Response header management.
     */
    public function __construct(string $url, int $status = 302)
    {
        parent::__construct('', $status, ['Location' => $url]);
    }
}

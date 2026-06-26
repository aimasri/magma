<?php

namespace Magma\http;

/**
 * RedirectResponse — convenience for Location redirects.
 *
 * Purpose:
 * - Represent HTTP redirects as objects so controllers can return
 *   a redirect without emitting headers directly.
 * 
 * Why / Why this design:
 * - Standardizes redirects into the `Response` object lifecycle. This prevents 
 *   premature `header()` emissions which break middleware execution and testing.
 * 
 * Teaching notes:
 * - By extending `Response`, a `RedirectResponse` can bubble backwards up through 
 *   the middleware pipeline, allowing global logic (like session serialization) to run.
 */
class RedirectResponse extends Response
{
    public function __construct(string $url, int $status = 302)
    {
        parent::__construct('', $status, ['Location' => $url]);
    }
}

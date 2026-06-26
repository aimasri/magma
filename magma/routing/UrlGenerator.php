<?php

namespace Magma\routing;

use Magma\http\Request;

/**
 * UrlGenerator — helper for building URLs.
 *
 * Purpose:
 * - Provides a centralized way to generate absolute URLs, automatically
 *   detecting the current scheme (HTTP/HTTPS) and host to avoid hardcoding.
 *
 * Why / Why this design:
 * - Centralizing URL generation ensures consistency across the application. It decouples 
 *   views and controllers from the actual environment configuration, allowing seamless 
 *   transitions between local, staging, and production environments.
 *
 * Teaching notes:
 * - We explicitly use `APP_URL` from the environment configuration instead of relying on 
 *   `$_SERVER['HTTP_HOST']` or `X-Forwarded-Host`. This prevents Host Header Injection 
 *   attacks where malicious actors poison password reset links by supplying a fake Host header.
 */
class UrlGenerator
{
    protected Request $request;
    protected string $appUrl;

    public function __construct(Request $request, string $appUrl)
    {
        $this->request = $request;
        $this->appUrl = rtrim($appUrl, '/');
    }

    /**
     * Generates an absolute URL for a given path and query parameters.
     * 
     * Execution Flow:
     * 1. Retrieve the trusted `APP_URL` from the environment configuration.
     * 2. Strip trailing slashes from the base URL and leading slashes from the path.
     * 3. Concatenate the base URL and path.
     * 4. Append encoded query parameters if provided.
     * 
     * Logic behind the logic:
     * - By completely ignoring user-supplied headers (`Host` and `X-Forwarded-Host`), 
     *   we guarantee that generated URLs (especially sensitive ones like password reset links) 
     *   always point back to our actual application domain.
     * 
     * @param string $path The relative path (e.g., '/reset-password')
     * @param array $queryParams Optional query parameters to append.
     * @return string The fully qualified URL.
     */
    public function generateAbsolute(string $path, array $queryParams = []): string
    {
        $url = $this->appUrl . '/' . ltrim($path, '/');
        
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        return $url;
    }
}

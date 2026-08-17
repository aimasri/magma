<?php

declare(strict_types=1);

namespace Magma\middleware;

use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\security\CsrfManager;

/**
 * Title: Cross-Site Request Forgery (CSRF) Protection Middleware
 *
 * Purpose:
 * - Defends state-changing HTTP requests (POST, PUT, DELETE, PATCH) against CSRF attacks using the Synchronizer Token Pattern.
 * - Inspects client content-negotiation to return structured 403 JSON payloads for API/AJAX callers.
 * - Intelligently pauses aggressive token rotation on AJAX/API requests to eliminate false-positive 403 Forbidden errors during debounced form interactions.
 *
 * Why / Why this design:
 * - Debounce-Safe AJAX Rotation: Live form calculators and autosave features issue rapid asynchronous requests. Rotating the token on every debounced keystroke causes race conditions where later requests fail. Pausing rotation on AJAX calls preserves UX while maintaining full security against cross-site origins.
 *
 * Teaching notes:
 * - Custom headers like `X-CSRF-TOKEN` or `X-XSRF-TOKEN` cannot be sent cross-origin without CORS preflight authorization, making header-based validation extremely secure.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private const UNSAFE_METHODS = ['POST', 'PUT', 'DELETE', 'PATCH'];

    private CsrfManager $csrfManager;

    public function __construct(CsrfManager $csrfManager)
    {
        $this->csrfManager = $csrfManager;
    }

    /**
     * Intercepts and validates CSRF tokens on mutating HTTP requests.
     *
     * Execution Flow:
     * 1. Ensures an active token is present in the session (generating one if needed).
     * 2. If the request method is safe (GET, HEAD, OPTIONS), passes to `$next`.
     * 3. For mutating verbs, extracts token from `_token` post field or `X-CSRF-TOKEN` / `X-XSRF-TOKEN` headers.
     * 4. Validates token against the session grace-period window.
     * 5. If invalid:
     *    a. If JSON or AJAX request, returns 403 JSON error payload.
     *    b. Otherwise returns 403 HTML Forbidden response.
     * 6. If valid and not an AJAX/JSON request, consumes token and regenerates for subsequent navigations.
     * 7. Hands execution over to `$next`.
     *
     * @param RequestInterface $request
     * @param callable $next
     * @return Response
     */
    public function process(RequestInterface $request, callable $next): Response
    {
        // Ensure an active token exists in the session
        $this->csrfManager->getToken();

        $method = strtoupper($request->getMethod());

        if (in_array($method, self::UNSAFE_METHODS, true)) {
            $submittedToken = $request->request('_token')
                ?? $request->header('X-CSRF-TOKEN')
                ?? $request->header('X-XSRF-TOKEN');

            if (!is_string($submittedToken) || !$this->csrfManager->validateToken($submittedToken)) {
                if ($request->isJsonExpected() || $request->expectsJson()) {
                    $payload = json_encode([
                        'success' => false,
                        'error'   => 'Forbidden: Invalid or missing CSRF token.',
                        'code'    => 403,
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

                    return new Response($payload, 403, ['Content-Type' => 'application/json; charset=utf-8']);
                }

                return new Response("Forbidden: Invalid or missing CSRF token.", 403);
            }

            // Pause aggressive token rotation on AJAX/API requests to avoid race conditions on debounced inputs
            $isAjaxOrJson = $request->isJsonExpected()
                || $request->expectsJson()
                || $request->header('X-CSRF-TOKEN') !== null
                || $request->header('X-XSRF-TOKEN') !== null;

            if (!$isAjaxOrJson) {
                $this->csrfManager->consumeToken($submittedToken);
                $this->csrfManager->regenerateToken();
            }
        }

        return $next($request);
    }
}
<?php

declare(strict_types=1);

namespace Magma\middleware;

use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\http\SessionInterface;

/**
 * Title: Authentication Access Guard Middleware
 *
 * Purpose:
 * - Guarantees that only authenticated users with an active session can access protected routes.
 * - Inspects client content-negotiation to return structured 401 JSON payloads for API/AJAX requests or 302 redirects to `/login` for HTML browser navigation.
 * - Automatically captures deep links for unauthenticated GET requests into session storage for post-authentication redirection.
 *
 * Why / Why this design:
 * - Content-Negotiated Access Control: Prevents SPA/AJAX fetch calls from receiving a 302 HTML login redirect which causes CORS and JSON parsing crashes.
 * - Deep Link Preservation: Capturing the unauthenticated user's requested URI into session storage ensures seamless UX by restoring their target destination post-login.
 * - Dependency Inversion: Injects `SessionInterface` rather than concrete session classes, facilitating unit testing without global state.
 *
 * Teaching notes:
 * - Intercepting requests at the middleware boundary prevents protected controllers and domain services from ever executing on unauthenticated requests.
 */
class AuthMiddleware implements MiddlewareInterface
{
    /** @var string Session storage key for capturing intended deep links */
    public const INTENDED_URL_SESSION_KEY = 'intended_url';

    private SessionInterface $session;
    private \Magma\interfaces\cqrs\UserQueryInterface $userRepository;

    /**
     * Initializes the AuthMiddleware.
     *
     * @param SessionInterface $session The session service for retrieving user data.
     * @param \Magma\interfaces\cqrs\UserQueryInterface $userRepository Repository to query user specific data, such as password changes.
     */
    public function __construct(
        SessionInterface $session,
        \Magma\interfaces\cqrs\UserQueryInterface $userRepository
    ) {
        $this->session = $session;
        $this->userRepository = $userRepository;
    }

    /**
     * Filters the incoming request for active authentication credentials.
     *
     * Execution Flow:
     * 1. Inspects the active session for an authenticated user payload.
     * 2. If user is absent:
     *    a. Checks if client expects JSON (`isJsonExpected()`).
     *    b. If JSON expected, returns an immediate 401 Unauthorized JSON response.
     *    c. If HTML expected and request is an idempotent GET request, saves the intended URI in session.
     *    d. Returns a RedirectResponse to `/login`.
     * 3. If user is authenticated:
     *    a. Verifies password timestamp validity against database records.
     *    b. If invalidated, destroys session and handles unauthorized flow.
     *    c. Otherwise, passes execution to `$next`.
     *
     * Logic behind the logic:
     * - Differentiating between API and HTML clients preserves REST contracts while maintaining standard web browser UX.
     * - Capturing deep links only on GET requests ensures idempotent redirection post-authentication, preventing unintended form re-submissions.
     *
     * @param RequestInterface $request
     * @param callable $next
     * @return Response
     */
    public function process(RequestInterface $request, callable $next): Response
    {
        $user = $this->session->get('user');
        
        if (!is_array($user)) {
            return $this->handleUnauthorized($request);
        }
        
        $loginTime = $this->session->get('login_time');
        $userId = $user['id'] ?? null;
        
        if (is_numeric($userId) && is_numeric($loginTime)) {
            $passwordChangedAt = $this->userRepository->getPasswordChangedAt((int) $userId);
            if ($passwordChangedAt && $passwordChangedAt > $loginTime) {
                $this->session->destroy();
                return $this->handleUnauthorized($request);
            }
        }
        
        return $next($request);
    }
    
    /**
     * Handles unauthorized access attempts.
     *
     * Execution Flow:
     * 1. Checks if the incoming request expects a JSON response via headers or route path.
     * 2. If JSON is expected, returns a 401 Unauthorized JSON response payload.
     * 3. If HTML navigation on an idempotent GET or HEAD request, captures the full URI into session storage.
     * 4. Returns a 302 RedirectResponse to the login page.
     *
     * Logic behind the logic:
     * - Providing content negotiation ensures API consumers receive proper HTTP status codes and payloads, preventing SPA/AJAX fetch calls from failing due to unexpected HTML redirects or CORS issues.
     * - Storing intended deep links in session for browser navigation ensures users return directly to their requested destination after authentication.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @return Response The structured error response or redirection.
     */
    private function handleUnauthorized(RequestInterface $request): Response
    {
        if ($request->isJsonExpected() || $request->expectsJson()) {
            $payload = json_encode([
                'success' => false,
                'error'   => 'Unauthenticated access.',
                'code'    => 401,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            return new Response($payload, 401, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        $method = strtoupper($request->getMethod());
        $path = rtrim(strtolower($request->getPath()), '/');
        if ($path === '') {
            $path = '/';
        }

        if (in_array($method, ['GET', 'HEAD'], true) && !in_array($path, ['/login', '/logout', '/register'], true)) {
            $this->session->set(self::INTENDED_URL_SESSION_KEY, $request->getUri());
        }

        return new RedirectResponse('/login');
    }
}

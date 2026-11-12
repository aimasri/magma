<?php

declare(strict_types=1);

namespace Magma\middleware;

use Magma\http\RedirectResponse;
use Magma\http\Request;
use Magma\http\Response;
use Magma\http\SessionInterface;
use Magma\services\AuthenticationService;

/**
 * Title: Role-Based Authorization Guard Middleware
 *
 * Purpose:
 * - Validates that the authenticated user possesses one of the allowed roles.
 * - Protects endpoints by returning 401/403 JSON responses for API clients or redirects for web clients.
 * - Captures unauthenticated deep-link targets into session storage for seamless post-login redirection.
 *
 * Why / Why this design:
 * - Single Responsibility Principle (SRP): Focuses exclusively on role verification and access guard orchestration.
 * - Content Negotiation: Distinguishes between JSON API clients and browser navigations.
 * - Deep Link UX: Saves intended URI for unauthenticated GET/HEAD requests to `/login`, without looping already-authenticated users.
 *
 * Teaching notes:
 * - Centralizing role authorization checks in middleware prevents controllers from duplicating access guard logic.
 */
class RoleMiddleware implements MiddlewareInterface
{
    /** @var array<int, string> */
    private array $allowedRoles = [];
    private string $redirectPath = '/';

    /**
     * Initializes the middleware with required authentication service and session dependencies.
     *
     * @param AuthenticationService $authService Service used to resolve the authenticated user.
     * @param SessionInterface $session Application session interface for capturing deep links.
     */
    public function __construct(
        private readonly AuthenticationService $authService,
        private readonly SessionInterface $session
    ) {}

    /**
     * Configures the allowed roles and fallback redirect path for this middleware instance.
     *
     * @param array<int, string>|string $allowedRoles Allowed role names.
     * @param string $redirectPath Fallback redirect URI.
     * @return void
     */
    public function configure(array|string $allowedRoles, string $redirectPath = '/'): void
    {
        $this->allowedRoles = (array) $allowedRoles;
        $this->redirectPath = $redirectPath;
    }

    /**
     * Executes role-based access validation.
     *
     * Execution Flow:
     * 1. Resolves authenticated user via `AuthenticationService::getAuthenticatedUser()`.
     * 2. If user is unauthenticated ($user === null):
     *    a. If JSON requested, returns 401 Unauthorized JSON response.
     *    b. If GET/HEAD web request and redirect destination is login, saves intended URI in session.
     *    c. Returns RedirectResponse to configured redirect path.
     * 3. If user lacks required role:
     *    a. If JSON requested, returns 403 Forbidden JSON response.
     *    b. Otherwise returns RedirectResponse to fallback path (without overwriting intended_url).
     * 4. If authorized, delegates to `$next($request)`.
     *
     * Logic behind the logic:
     * - We do NOT overwrite `intended_url` when an authenticated user lacks the required role; otherwise redirecting an authenticated user from a restricted area to a fallback path would pollute their post-login destination.
     *
     * @param Request $request Incoming HTTP request.
     * @param callable $next Downstream middleware handler.
     * @return Response Resulting HTTP response.
     */
    public function process(Request $request, callable $next): Response
    {
        $user = $this->authService->getAuthenticatedUser();

        if ($user === null) {
            if ($request->isJsonExpected() || $request->expectsJson()) {
                $payload = json_encode([
                    'success' => false,
                    'error'   => 'Unauthenticated access.',
                    'code'    => 401,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

                return new Response($payload, 401, ['Content-Type' => 'application/json; charset=utf-8']);
            }

            $method = strtoupper($request->getMethod());
            if (in_array($method, ['GET', 'HEAD'], true) && str_starts_with($this->redirectPath, '/login')) {
                $this->session->set(AuthMiddleware::INTENDED_URL_SESSION_KEY, $request->getUri());
            }

            return new RedirectResponse($this->redirectPath);
        }

        if (!in_array($user->getRole(), $this->allowedRoles, true)) {
            if ($request->isJsonExpected() || $request->expectsJson()) {
                $payload = json_encode([
                    'success' => false,
                    'error'   => 'Forbidden: Insufficient role permissions.',
                    'code'    => 403,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

                return new Response($payload, 403, ['Content-Type' => 'application/json; charset=utf-8']);
            }

            return new RedirectResponse($this->redirectPath);
        }

        return $next($request);
    }
}

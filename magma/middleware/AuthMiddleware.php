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
 *
 * Why / Why this design:
 * - Content-Negotiated Access Control: Prevents SPA/AJAX fetch calls from receiving a 302 HTML login redirect which causes CORS and JSON parsing crashes.
 * - Dependency Inversion: Injects `SessionInterface` rather than concrete session classes, facilitating unit testing without global state.
 *
 * Teaching notes:
 * - Intercepting requests at the middleware boundary prevents protected controllers and domain services from ever executing on unauthenticated requests.
 */
class AuthMiddleware implements MiddlewareInterface
{
    private SessionInterface $session;
    private \Magma\interfaces\cqrs\UserQueryInterface $userRepository;

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
     *    c. Otherwise, returns a RedirectResponse to `/login`.
     * 3. If user is authenticated, passes execution to `$next`.
     *
     * Logic behind the logic:
     * - Differentiating between API and HTML clients preserves REST contracts while maintaining standard web browser UX.
     *
     * @param RequestInterface $request
     * @param callable $next
     * @return Response
     */
    public function process(RequestInterface $request, callable $next): Response
    {
        $user = $this->session->get('user');
        
        if (!$user) {
            return $this->handleUnauthorized($request);
        }
        
        $loginTime = $this->session->get('login_time');
        $userId = $user['id'] ?? null;
        
        if ($userId && $loginTime) {
            $passwordChangedAt = $this->userRepository->getPasswordChangedAt((int) $userId);
            if ($passwordChangedAt && $passwordChangedAt > $loginTime) {
                $this->session->destroy();
                return $this->handleUnauthorized($request);
            }
        }
        
        return $next($request);
    }
    
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

        return new RedirectResponse('/login');
    }
}

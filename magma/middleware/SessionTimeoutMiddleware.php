<?php

declare(strict_types=1);

namespace Magma\middleware;

use Magma\contracts\ClockInterface;
use Magma\contracts\RolePolicyInterface;
use Magma\http\RedirectResponse;
use Magma\http\Request;
use Magma\http\Response;
use Magma\http\SessionInterface;

/**
 * Title: Session Timeout Middleware
 *
 * Purpose:
 * - Enforce inactivity timeouts for authenticated users based on application role policy.
 * - Allow dynamic expiration times decoupled from hardcoded framework role enums.
 *
 * Why / Why this design:
 * - Single Responsibility Principle (SRP): Isolates session inactivity enforcement from raw session persistence.
 * - Dependency Inversion Principle (DIP): Injects `SessionInterface`, `ClockInterface`, and `RolePolicyInterface` (exactly 3 dependencies).
 * - Open/Closed Principle (OCP): Dynamic timeout calculation delegates to `RolePolicyInterface`, enabling applications to configure custom role timeouts without modifying middleware code.
 *
 * Teaching notes:
 * - Server-side `last_activity` timestamp validation guarantees inactive sessions are destroyed even if client-side cookies persist.
 */
class SessionTimeoutMiddleware implements MiddlewareInterface
{
    /**
     * Initializes the middleware with required session, clock, and role policy dependencies.
     *
     * @param SessionInterface $session The session service.
     * @param ClockInterface $clock Deterministic system clock.
     * @param RolePolicyInterface $rolePolicy Policy resolving role-specific session inactivity limits.
     */
    public function __construct(
        private readonly SessionInterface $session,
        private readonly ClockInterface $clock,
        private readonly RolePolicyInterface $rolePolicy
    ) {}

    /**
     * Processes the request and enforces session inactivity timeouts.
     *
     * Execution Flow:
     * 1. Checks if the user is authenticated in the session. If not, passes request to `$next`.
     * 2. Retrieves the `last_activity` timestamp and current time from `ClockInterface`.
     * 3. Resolves the allowed timeout duration for the user's role via `RolePolicyInterface::resolveSessionTimeout()`.
     * 4. If inactivity exceeds allowed threshold, destroys session, clears `remember_user` cookie, and returns a 401 JSON response (for API requests) or 302 redirect to `/login` (for web navigation).
     * 5. Otherwise updates `last_activity` timestamp to current time and passes request to `$next`.
     *
     * Logic behind the logic:
     * - Checking both `isJsonExpected()` and standard browser navigation ensures SPA/AJAX consumers receive structured 401 responses instead of HTML redirects.
     *
     * @param Request $request Incoming HTTP request.
     * @param callable $next Downstream middleware handler.
     * @return Response Resulting HTTP response.
     */
    public function process(Request $request, callable $next): Response
    {
        $user = $this->session->get('user');

        if (is_array($user)) {
            $lastActivity = $this->session->get('last_activity');
            $lastActivity = is_numeric($lastActivity) ? (int) $lastActivity : null;
            $currentTime = $this->clock->now()->getTimestamp();

            $role = isset($user['role']) && is_scalar($user['role']) ? (string) $user['role'] : null;
            $allowedTimeout = $this->rolePolicy->resolveSessionTimeout($role);

            if ($lastActivity !== null && ($currentTime - $lastActivity > $allowedTimeout)) {
                // Timeout exceeded. Destroy session and redirect to login.
                $this->session->destroy();

                if ($request->isJsonExpected() || $request->expectsJson()) {
                    $payload = json_encode([
                        'success' => false,
                        'error'   => 'Session expired due to inactivity.',
                        'code'    => 401,
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

                    $response = new Response($payload, 401, ['Content-Type' => 'application/json; charset=utf-8']);
                    return $response->withCookie('remember_user', '', $currentTime - 3600);
                }

                return (new RedirectResponse('/login'))
                    ->withCookie('remember_user', '', $currentTime - 3600);
            }

            // Update last activity timestamp
            $this->session->set('last_activity', $currentTime);
        }

        return $next($request);
    }
}

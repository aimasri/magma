<?php

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;

/**
 * Title: GuestMiddleware — public route enforcement.
 *
 * Purpose:
 * - Ensures the user is NOT authenticated.
 * - Prevents logged-in users from accessing login/register pages.
 * 
 * Teaching notes:
 * - Enforcing guest status via middleware centralizes flow control for
 *   authentication pages and prevents logic leaks in controllers.
 */
class GuestMiddleware implements MiddlewareInterface
{
    private \Magma\http\Session $session;

    public function __construct(\Magma\http\Session $session)
    {
        $this->session = $session;
    }
    /**
     * Filters the request to ensure the user is an unauthenticated guest.
     * 
     * Execution Flow:
     * 1. Extracts the user state from the current session.
     * 2. If a user is found, their 'role' is evaluated.
     * 3. Vendors are redirected to the '/admin' dashboard, while standard users go to '/user'.
     * 4. If no user is found, the guest is allowed to proceed to the requested route.
     * 
     * Logic behind the logic:
     * This middleware acts as a UX optimization and a defensive measure against authenticated users inadvertently submitting guest-only forms like registration or login, which could pollute session state.
     * 
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function process(Request $request, callable $next): Response
    {
        $user = $this->session->get('user');
        if (is_array($user)) {
            if (isset($user['role']) && $user['role'] === 'vendor') {
                return new RedirectResponse('/admin');
            }
            return new RedirectResponse('/user');
        }
        return $next($request);
    }
}

<?php

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;

/**
 * GuestMiddleware — public route enforcement.
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
    public function process(Request $request, callable $next): Response
    {
        $user = $request->session('user');
        if ($user) {
            if (isset($user['role']) && $user['role'] === 'vendor') {
                return new RedirectResponse('/admin');
            }
            return new RedirectResponse('/user');
        }
        return $next($request);
    }
}

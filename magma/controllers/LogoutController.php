<?php

namespace Magma\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\services\AuthenticationService;
use Magma\services\AuthenticationResult;
use Magma\view\TemplateEngine;

/**
 * Title: Logout Controller
 *
 * Purpose:
 * - Handle user logout operations.
 * - Delegate session destruction and token invalidation to the AuthenticationService.
 *
 * Why / Why this design:
 * - SRP: Isolates logout logic from other authentication concerns.
 * - HTTP-agnostic Domain: Relies on AuthenticationService and parses AuthenticationResult to modify cookies.
 *
 * Teaching notes:
 * - Notice how it checks for 'remember_user' cookie to pass to logout, enabling single-source session destruction.
 */
class LogoutController
{
    use \Magma\controllers\traits\AppliesAuthenticationCookiesTrait;

    public function __construct(
        private readonly AuthenticationService $authService
    ) {}

    /**
     * Executes the logout action.
     * 
     * Execution Flow:
     * 1. Retrieves the 'remember_user' cookie if present.
     * 2. Calls AuthenticationService to destroy session and invalidate token.
     * 3. Applies the resulting cookies to a RedirectResponse back to root.
     * 
     * @return Response
     */
    public function logout(Request $request): Response
    {
        $token = $request->cookie('remember_user');
        $tokenStr = is_string($token) ? $token : null;
        $result = $this->authService->logout($tokenStr);

        return $this->applyAuthResult($result, new RedirectResponse('/'), $request);
    }
}

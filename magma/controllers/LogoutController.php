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
    public function logout(Request $request, AuthenticationService $authService): Response
    {
        $token = $request->cookie('remember_user');
        $tokenStr = is_string($token) ? $token : null;
        $result = $authService->logout($tokenStr);

        return $this->applyAuthResult($result, new RedirectResponse('/'), $request);
    }

    /**
     * Applies authentication result cookies to the HTTP response.
     * 
     * @param AuthenticationResult $result
     * @param Response $response
     * @return Response
     */
    private function applyAuthResult(AuthenticationResult $result, Response $response, Request $request): Response
    {
        foreach ($result->getCookiesToSet() as $cookie) {
            $response->withCookie($cookie['name'], $cookie['value'], $cookie['expiry'], "/", "", $request->isSecure(), true);
        }
        foreach ($result->getCookiesToClear() as $name) {
            $response->withCookie($name, '', time() - 3600, "/", "", $request->isSecure(), true);
        }
        return $response;
    }
}

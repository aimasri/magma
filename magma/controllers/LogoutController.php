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
class LogoutController extends BaseController
{
    protected Request $request;
    protected AuthenticationService $authService;

    public function __construct(
        TemplateEngine $templateEngine, 
        \Magma\security\CsrfManager $csrfManager,
        \Magma\http\Session $session,
        Request $request, 
        AuthenticationService $authService
    ) {
        parent::__construct($templateEngine, $csrfManager, $session);
        $this->request = $request;
        $this->authService = $authService;
    }

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
    public function logout(): Response
    {
        $token = $this->request->cookie('remember_user');
        $result = $this->authService->logout($token);

        return $this->applyAuthResult($result, new RedirectResponse('/'));
    }

    /**
     * Applies authentication result cookies to the HTTP response.
     * 
     * @param AuthenticationResult $result
     * @param Response $response
     * @return Response
     */
    private function applyAuthResult(AuthenticationResult $result, Response $response): Response
    {
        foreach ($result->getCookiesToSet() as $cookie) {
            $response->withCookie($cookie['name'], $cookie['value'], $cookie['expiry'], "/", "", $this->request->isSecure(), true);
        }
        foreach ($result->getCookiesToClear() as $name) {
            $response->withCookie($name, '', time() - 3600, "/", "", $this->request->isSecure(), true);
        }
        return $response;
    }
}

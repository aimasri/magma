<?php

namespace Magma\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\services\AuthenticationService;
use Magma\services\AuthenticationResult;
use Magma\view\TemplateEngine;

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

    public function logout(): Response
    {
        $token = $this->request->cookie('remember_user');
        $result = $this->authService->logout($token);

        return $this->applyAuthResult($result, new RedirectResponse('/'));
    }

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

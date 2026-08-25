<?php

namespace Magma\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\services\AuthenticationService;
use Magma\services\AuthenticationResult;
use Magma\requests\LoginRequest;
use Magma\validation\Validator;
use Magma\view\TemplateEngine;

/**
 * Title: Login Controller
 *
 * Purpose:
 * - Handles user login presentation and authentication logic.
 *
 * Why this design:
 * - Delegates actual authentication to the AuthenticationService.
 */
class LoginController
{
    public function login(
        Request $request, 
        AuthenticationService $authService, 
        \Magma\view\HtmlResponseBuilderInterface $html, 
        \Magma\http\SessionInterface $session
    ): Response {
        $token = $request->cookie('remember_user');

        if ($token) {
            $result = $authService->attemptAutoLogin($token);
            if ($result->isSuccessful()) {
                return $this->applyAuthResult($result, $this->redirectToDashboard($result->getUser()), $request);
            }
            
            $response = $html->render('auth/login', ['title' => 'Login']);
            return $this->applyAuthResult($result, $response, $request);
        }

        if ($session->get('user')) {
            $sessionUser = new \Magma\domain\AuthUser($session->get('user'));
            return $this->redirectToDashboard($sessionUser);
        }

        return $html->render('auth/login', [
            'title'   => 'Login',
        ]);
    }

    public function authenticate(
        LoginRequest $loginRequest, 
        Request $request, 
        AuthenticationService $authService, 
        \Magma\http\SessionInterface $session
    ): Response {
        // Validation is automatically handled by RouteParameterResolver for the LoginRequest parameter.
        
        $data = $request->request();
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $remember = !empty($data['remember_me']);

        $result = $authService->attempt($email, $password, $remember);

        if (!$result->isSuccessful()) {
            $session->set('old', ['email' => $data['email'] ?? '']);
            $session->set('errors', ['auth' => 'Invalid credentials']);
            return new RedirectResponse('/login');
        }

        return $this->applyAuthResult($result, $this->redirectToDashboard($result->getUser()), $request);
    }

    private function redirectToDashboard(\Magma\domain\AuthUser $user): RedirectResponse
    {
        return new RedirectResponse(\Magma\enums\UserRole::dashboardPath($user->getRole() ?? null));
    }

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

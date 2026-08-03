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

class LoginController extends BaseController
{
    protected Request $request;
    protected AuthenticationService $authService;
    protected Validator $validator;

    public function __construct(
        TemplateEngine $templateEngine, 
        \Magma\security\CsrfManager $csrfManager,
        \Magma\http\Session $session,
        Request $request, 
        AuthenticationService $authService, 
        Validator $validator
    ) {
        parent::__construct($templateEngine, $csrfManager, $session);
        $this->request = $request;
        $this->authService = $authService;
        $this->validator = $validator;
    }

    public function login(): Response
    {
        $token = $this->request->cookie('remember_user');

        if ($token) {
            $result = $this->authService->attemptAutoLogin($token);
            if ($result->isSuccessful()) {
                return $this->applyAuthResult($result, clone $this->redirectToDashboard($result->getUser()));
            }
            
            $response = $this->render('auth/login', ['title' => 'Login']);
            return $this->applyAuthResult($result, clone $response);
        }

        if ($this->session->get('user')) {
            $sessionUser = new \Magma\domain\AuthUser($this->session->get('user'));
            return clone $this->redirectToDashboard($sessionUser);
        }

        return $this->render('auth/login', [
            'title'   => 'Login',
        ]);
    }

    public function authenticate(): Response
    {
        $this->validateOrRedirect(new LoginRequest($this->request, $this->validator), '/login');

        $data = $this->request->request();
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $remember = !empty($data['remember_me']);

        $result = $this->authService->attempt($email, $password, $remember);

        if (!$result->isSuccessful()) {
            $this->session->set('old', ['email' => $data['email'] ?? '']);
            $this->session->set('errors', ['auth' => 'Invalid credentials']);
            return new RedirectResponse('/login');
        }

        return $this->applyAuthResult($result, clone $this->redirectToDashboard($result->getUser()));
    }

    private function redirectToDashboard(\Magma\domain\AuthUser $user): RedirectResponse
    {
        return new RedirectResponse(\Magma\enums\UserRole::dashboardPath($user->getRole() ?? null));
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

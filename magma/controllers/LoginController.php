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
 * Title: LoginController
 *
 * Purpose:
 * - Handles user login presentation and authentication logic.
 * - Manages session creation and cookie handling for authentication.
 *
 * Why / Why this design:
 * - Delegates actual authentication to the AuthenticationService.
 * - Promotes Separation of Concerns (SoC) by keeping HTTP concerns in the controller and domain logic in the service.
 *
 * Teaching notes:
 * - Always apply auth results to set/clear cookies when authentication occurs.
 */
class LoginController
{
    private AuthenticationService $authService;
    private \Magma\view\HtmlResponseBuilderInterface $html;
    private \Magma\http\SessionInterface $session;

    public function __construct(
        AuthenticationService $authService,
        \Magma\view\HtmlResponseBuilderInterface $html,
        \Magma\http\SessionInterface $session
    ) {
        $this->authService = $authService;
        $this->html = $html;
        $this->session = $session;
    }

    /**
     * Handles the GET request for the login page.
     * 1. Checks for a "remember me" token and attempts auto-login.
     * 2. Checks if a user is already in the session.
     * 3. Renders the login form if neither of the above applies.
     */
    public function login(Request $request): Response 
    {
        $token = $request->cookie('remember_user');

        if ($token) {
            $result = $this->authService->attemptAutoLogin($token);
            if ($result->isSuccessful()) {
                return $this->applyAuthResult($result, $this->redirectToDashboard($result->getUser()), $request);
            }
            
            $response = $this->html->render('auth/login', ['title' => 'Login']);
            return $this->applyAuthResult($result, $response, $request);
        }

        if ($this->session->get('user')) {
            $sessionUser = new \Magma\domain\AuthUser($this->session->get('user'));
            return $this->redirectToDashboard($sessionUser);
        }

        return $this->html->render('auth/login', [
            'title'   => 'Login',
        ]);
    }

    /**
     * Handles the POST request to authenticate a user.
     * 1. Converts the validated request into a DTO.
     * 2. Delegates authentication attempt to the AuthenticationService.
     * 3. Redirects back with errors if unsuccessful.
     * 4. Applies auth result and redirects to dashboard if successful.
     */
    public function authenticate(LoginRequest $loginRequest, Request $request): Response 
    {
        // Validation is automatically handled by RouteParameterResolver for the LoginRequest parameter.
        
        $dto = $loginRequest->toDTO();

        $result = $this->authService->attempt($dto->email, $dto->password, $dto->rememberMe);

        if (!$result->isSuccessful()) {
            $this->session->set('old', ['email' => $dto->email]);
            $this->session->set('errors', ['auth' => 'Invalid credentials']);
            return new RedirectResponse('/login');
        }

        return $this->applyAuthResult($result, $this->redirectToDashboard($result->getUser()), $request);
    }

    /**
     * Redirects the user to their appropriate dashboard based on role.
     */
    private function redirectToDashboard(\Magma\domain\AuthUser $user): RedirectResponse
    {
        return new RedirectResponse(\Magma\enums\UserRole::dashboardPath($user->getRole() ?? null));
    }

    /**
     * Applies cookies from the authentication result to the HTTP response.
     * 1. Sets new cookies required by the result.
     * 2. Clears expired or removed cookies.
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

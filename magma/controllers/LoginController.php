<?php

declare(strict_types=1);

namespace Magma\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\services\AuthenticationService;
use Magma\services\AuthenticationResult;
use Magma\requests\LoginRequest;
use Magma\security\PostLoginRedirectResolver;

/**
 * Title: Login Controller
 *
 * Purpose:
 * - Handles user login presentation and authentication workflow.
 * - Coordinates authentication orchestration, cookie application, and post-authentication redirection.
 *
 * Why / Why this design:
 * - Separation of Concerns (SoC): Coordinates HTTP transport mechanics while delegating credential verification to `AuthenticationService` and destination resolution / flash management to `PostLoginRedirectResolver`.
 * - Single Responsibility Principle (SRP): Restricted to exactly 3 injected dependencies (`AuthenticationService`, `HtmlResponseBuilderInterface`, `PostLoginRedirectResolver`), eliminating direct session state manipulation from the controller.
 *
 * Teaching notes:
 * - Always apply auth results (`applyAuthResult`) to set or clear remember-me cookies when authentication state changes.
 */
class LoginController
{
    use \Magma\controllers\traits\AppliesAuthenticationCookiesTrait;

    /**
     * Initializes the LoginController with required services.
     *
     * Execution Flow:
     * 1. Stores injected authentication service, HTML view builder, and redirect resolver.
     *
     * Logic behind the logic:
     * - Restricting constructor injection to exactly 3 focused domain services complies strictly with Magma architectural guidelines.
     *
     * @param AuthenticationService $authService
     * @param \Magma\view\HtmlResponseBuilderInterface $html
     * @param PostLoginRedirectResolver $redirectResolver
     */
    public function __construct(
        private readonly AuthenticationService $authService,
        private readonly \Magma\view\HtmlResponseBuilderInterface $html,
        private readonly PostLoginRedirectResolver $redirectResolver
    ) {}

    /**
     * Handles the GET request for the login page.
     *
     * Execution Flow:
     * 1. Checks for a "remember_user" cookie and attempts automated persistent login.
     * 2. If auto-login succeeds, redirects user to their intended destination or role dashboard.
     * 3. Checks if an authenticated user session is already active via `AuthenticationService`; if so, redirects immediately.
     * 4. Otherwise, renders the login view.
     *
     * Logic behind the logic:
     * - Checking remember-me and existing sessions avoids redundant re-authentication and restores intended deep links immediately.
     *
     * @param Request $request
     * @return Response
     */
    public function login(Request $request): Response 
    {
        $token = $request->cookie('remember_user');

        if (is_string($token) && $token !== '') {
            $result = $this->authService->attemptAutoLogin($token);
            if ($result->isSuccessful()) {
                $user = $result->getUser();
                if ($user !== null) {
                    return $this->applyAuthResult($result, $this->redirectResolver->redirectAfterAuthentication($user, $request), $request);
                }
            }
            
            $response = $this->html->render('auth/login', ['title' => 'Login']);
            return $this->applyAuthResult($result, $response, $request);
        }

        $authUser = $this->authService->getAuthenticatedUser();
        if ($authUser !== null) {
            return $this->redirectResolver->redirectAfterAuthentication($authUser, $request);
        }

        return $this->html->render('auth/login', [
            'title' => 'Login',
        ]);
    }

    /**
     * Handles the POST request to authenticate a user.
     *
     * Execution Flow:
     * 1. Extracts validated credentials DTO from the LoginRequest.
     * 2. Delegates authentication attempt to the AuthenticationService.
     * 3. If unsuccessful, delegates failed redirect and error flashing to `PostLoginRedirectResolver`.
     * 4. If successful, resolves and sanitizes the destination via `PostLoginRedirectResolver`.
     * 5. Applies authentication cookies and redirects the user.
     *
     * Logic behind the logic:
     * - Flashing session errors and old inputs is delegated to `PostLoginRedirectResolver`, preserving intended destination deep-links across retries while keeping this controller decoupled from session primitives.
     *
     * @param LoginRequest $loginRequest
     * @param Request $request
     * @return Response
     */
    public function authenticate(LoginRequest $loginRequest, Request $request): Response 
    {
        // Validation is automatically handled by RouteParameterResolver for the LoginRequest parameter.
        
        $dto = $loginRequest->toDTO();

        $result = $this->authService->attempt($dto->email, $dto->password, $dto->rememberMe);

        if (!$result->isSuccessful()) {
            return $this->redirectResolver->redirectAfterFailedLogin($dto->email, 'Invalid credentials');
        }

        $user = $result->getUser();
        if ($user !== null) {
            return $this->applyAuthResult($result, $this->redirectResolver->redirectAfterAuthentication($user, $request), $request);
        }

        return new RedirectResponse('/login');
    }

    /**
     * Handles the GET request to log the user out.
     *
     * Execution Flow:
     * 1. Retrieves the 'remember_user' cookie if present.
     * 2. Delegates the logout process to the AuthenticationService.
     * 3. Applies the auth result to clear cookies and redirects to /login.
     *
     * @param Request $request
     * @return Response
     */
    public function logout(Request $request): Response
    {
        $token = $request->cookie('remember_user');
        $token = is_string($token) ? $token : null;

        $result = $this->authService->logout($token);

        return $this->applyAuthResult($result, new RedirectResponse('/login'), $request);
    }
}

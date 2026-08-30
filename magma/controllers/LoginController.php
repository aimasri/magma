<?php

declare(strict_types=1);

namespace Magma\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\services\AuthenticationService;
use Magma\services\AuthenticationResult;
use Magma\requests\LoginRequest;
use Magma\validation\Validator;
use Magma\view\TemplateEngine;
use Magma\security\RedirectSanitizer;
use Magma\middleware\AuthMiddleware;

/**
 * Title: Login Controller
 *
 * Purpose:
 * - Handles user login presentation and authentication workflow.
 * - Manages session creation, cookie handling, and post-authentication intended destination redirection.
 *
 * Why / Why this design:
 * - Separation of Concerns (SoC): Coordinates HTTP transport mechanics while delegating credential verification to `AuthenticationService` and URL safety validation to `RedirectSanitizer`.
 * - Deep Link UX: Restores user deep links and query string redirects safely while guarding against open-redirect exploits.
 *
 * Teaching notes:
 * - Always apply auth results (`applyAuthResult`) to set or clear remember-me cookies when authentication state changes.
 */
class LoginController
{
    use \Magma\controllers\traits\AppliesAuthenticationCookiesTrait;

    private AuthenticationService $authService;
    private \Magma\view\HtmlResponseBuilderInterface $html;
    private \Magma\http\SessionInterface $session;

    /**
     * Initializes the LoginController with required dependencies.
     *
     * Logic behind the logic:
     * - Injects the authentication service, HTML response builder, and session interface via Constructor Injection, avoiding hidden global state.
     *
     * @param AuthenticationService $authService
     * @param \Magma\view\HtmlResponseBuilderInterface $html
     * @param \Magma\http\SessionInterface $session
     */
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
     *
     * Execution Flow:
     * 1. Checks for a "remember_user" cookie and attempts automated persistent login.
     * 2. If auto-login succeeds, redirects user to their intended destination or dashboard.
     * 3. Checks if an authenticated user session is already active; if so, redirects to intended destination or dashboard.
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
                    return $this->applyAuthResult($result, $this->redirectToDestination($user, $request), $request);
                }
            }
            
            $response = $this->html->render('auth/login', ['title' => 'Login']);
            return $this->applyAuthResult($result, $response, $request);
        }

        $sessionUser = $this->session->get('user');
        if (is_array($sessionUser)) {
            $userData = [];
            foreach ($sessionUser as $k => $v) {
                if (is_string($k)) {
                    $userData[$k] = $v;
                }
            }
            $authUser = new \Magma\domain\AuthUser($userData);
            return $this->redirectToDestination($authUser, $request);
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
     * 3. If unsuccessful, flashes old input and errors to session and redirects back to /login.
     * 4. If successful, resolves and sanitizes the intended redirect destination.
     * 5. Applies authentication cookies and redirects the user.
     *
     * Logic behind the logic:
     * - Preserving session `intended_url` on failed attempts allows users to mistype credentials without losing their original target destination.
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
            $this->session->set('old', ['email' => $dto->email]);
            $this->session->set('errors', ['auth' => 'Invalid credentials']);
            return new RedirectResponse('/login');
        }

        $user = $result->getUser();
        if ($user !== null) {
            return $this->applyAuthResult($result, $this->redirectToDestination($user, $request), $request);
        }

        return new RedirectResponse('/login');
    }

    /**
     * Resolves the post-authentication redirect destination, sanitizing against open-redirect exploits.
     *
     * Execution Flow:
     * 1. Checks for an explicit redirect target in the POST request body.
     * 2. If absent, checks query string parameters (`redirect` or `return_to`).
     * 3. If absent, retrieves and removes the session-stored intended URL.
     * 4. Sanitizes candidate URL using `RedirectSanitizer::sanitize()` enforcing RFC 3986 path-absolute constraints.
     * 5. If valid, returns a RedirectResponse to the sanitized target; otherwise falls back to the user's role dashboard.
     *
     * Logic behind the logic:
     * - Strictly sanitizing before issuing redirect headers completely neutralizes protocol-relative (`//evil.com`) and backslash exploits while ensuring deep links and query parameters are seamlessly preserved.
     *
     * @param \Magma\domain\AuthUser $user
     * @param Request $request
     * @return RedirectResponse
     */
    private function redirectToDestination(\Magma\domain\AuthUser $user, Request $request): RedirectResponse
    {
        // 1. Inspect POST body parameter
        $postRedirect = $request->request('redirect');
        $candidate = is_string($postRedirect) && trim($postRedirect) !== '' ? trim($postRedirect) : null;

        // 2. Inspect Query string parameter
        if ($candidate === null) {
            $queryRedirect = $request->query('redirect', $request->query('return_to'));
            $candidate = is_string($queryRedirect) && trim($queryRedirect) !== '' ? trim($queryRedirect) : null;
        }

        // 3. Inspect and consume session-captured deep link
        $sessionIntended = $this->session->get(AuthMiddleware::INTENDED_URL_SESSION_KEY);
        if ($this->session->has(AuthMiddleware::INTENDED_URL_SESSION_KEY)) {
            $this->session->remove(AuthMiddleware::INTENDED_URL_SESSION_KEY);
        }

        if ($candidate === null && is_string($sessionIntended) && trim($sessionIntended) !== '') {
            $candidate = trim($sessionIntended);
        }

        // 4. Sanitize candidate target with RFC 3986 validation
        $sanitizedUrl = RedirectSanitizer::sanitize($candidate);

        if ($sanitizedUrl !== null) {
            return new RedirectResponse($sanitizedUrl);
        }

        return $this->redirectToDashboard($user);
    }

    /**
     * Redirects the user to their default dashboard based on role.
     *
     * @param \Magma\domain\AuthUser $user
     * @return RedirectResponse
     */
    private function redirectToDashboard(\Magma\domain\AuthUser $user): RedirectResponse
    {
        return new RedirectResponse(\Magma\enums\UserRole::dashboardPath($user->getRole()));
    }

    /**
     * Handles the GET request to log the user out.
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


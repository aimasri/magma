<?php

namespace Magma\controllers;

use Magma\controllers\BaseController;
use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;

use Magma\services\AuthenticationService;
use Magma\services\RegistrationService;
use Magma\services\AuthenticationResult;
use Magma\models\UserRepositoryInterface;
use Magma\requests\LoginRequest;
use Magma\requests\RegisterRequest;
use Magma\validation\Validator;
use Magma\view\TemplateEngine;

/**
 * Title: Authentication HTTP Controller
 *
 * Purpose:
 * - Offers HTTP endpoints for the authentication lifecycle (login, registration, logout).
 * - Bridges the gap between the HTTP transport layer (`Request`/`Response`) and the domain layer (`AuthenticationService`, `RegistrationService`).
 *
 * Why this design:
 * - This controller acts strictly as an orchestrator. It receives a request, triggers validation, delegates the actual security checks and session mechanics to the domain services, and then returns an HTTP redirect. It contains zero business logic itself.
 *
 * Teaching notes:
 * - This thin-controller architecture adheres to the "Fat Model, Skinny Controller" paradigm (though more accurately "Fat Service, Skinny Controller" here). This makes the controller incredibly easy to read and trivial to unit test by mocking the services.
 */
class AuthController extends BaseController
{
    protected Request $request;
    protected AuthenticationService $authService;
    protected RegistrationService $registrationService;
    protected UserRepositoryInterface $userRepository;
    protected Validator $validator;

    public function __construct(
        TemplateEngine $templateEngine, 
        \Magma\security\CsrfManager $csrfManager,
        Request $request, 
        AuthenticationService $authService, 
        RegistrationService $registrationService,
        UserRepositoryInterface $userRepository,
        Validator $validator
    ) {
        parent::__construct($templateEngine, $csrfManager);
        $this->request = $request;
        $this->authService = $authService;
        $this->registrationService = $registrationService;
        $this->userRepository = $userRepository;
        $this->validator = $validator;
    }

    /**
     * Renders the Login interface or bypasses it via Auto-Login.
     * 
     * Execution Flow:
     * 1. Check for a 'remember_user' persistent token cookie.
     * 2. If present and valid, log the user in, rotate the token, and immediately redirect.
     * 3. If no valid token, check if an active session already exists.
     * 4. If an active session exists, redirect based on the user's role.
     * 5. Otherwise, render the standard login HTML view.
     * 
     * Logic behind the logic:
     * - Checking the persistent cookie *before* the active session allows users whose 
     *   sessions have naturally expired (but who checked "Remember Me") to seamlessly 
     *   bypass the login screen without realizing their session had technically died.
     */
    public function login(): Response
    {
        $token = $this->request->cookie('remember_user');

        if ($token) {
            $result = $this->authService->attemptAutoLogin($token);
            if ($result->isSuccessful()) {
                return $this->applyAuthResult($result, $this->redirectToDashboard($result->getUser()));
            }
            
            // Invalid token; fall through to the login form, but attach the clear cookie instruction
            $response = $this->render('auth/login', ['title' => 'Login']);
            return $this->applyAuthResult($result, $response);
        }

        if ($this->request->session('user')) {
            return $this->redirectToDashboard($this->request->session('user'));
        }

        return $this->render('auth/login', [
            'title'   => 'Login',
        ]);
    }

    /**
     * Generates a redirect response to the appropriate dashboard based on user role.
     * 
     * @param array $user
     * @return RedirectResponse
     */
    private function redirectToDashboard(array $user): RedirectResponse
    {
        return new RedirectResponse(\Magma\enums\UserRole::dashboardPath($user['role'] ?? null));
    }

    /**
     * Validates and processes a login attempt.
     * 
     * Execution Flow:
     * 1. Validate the incoming POST request data against LoginRequest rules.
     * 2. Pass the sanitized email and password to AuthenticationService::attempt().
     * 3. On failure, flash old input/errors to the session and redirect back to login.
     * 4. On success, call AuthenticationService::login() to explicitly establish the PHP session state.
     * 5. Redirect the authenticated user to their appropriate dashboard based on their role.
     * 6. Optionally issue a "Remember Me" persistent cookie if requested.
     * 
     * Logic behind the logic:
     * - Attempting authentication and establishing the session are deliberately decoupled.
     *   This allows the system to verify credentials without blindly creating sessions 
     *   (e.g., if we were building an API that issues JWTs instead of relying on cookies).
     * - Flashing the 'old' email back to the session prevents the user from having 
     *   to re-type it if they simply fat-fingered their password.
     */
    public function authenticate(): Response
    {
        $this->validateOrRedirect(new LoginRequest($this->request, $this->validator), '/login');

        $data = $this->request->request();

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $remember = !empty($data['remember_me']);

        $result = $this->authService->attempt($email, $password, $remember);

        if (!$result->isSuccessful()) {
            // Flash old input (minus password) so the user doesn't have to re-type it
            $this->request->setSession('old', ['email' => $data['email'] ?? '']);
            $this->request->setSession('errors', ['auth' => 'Invalid credentials']);
            return new RedirectResponse('/login');
        }

        return $this->applyAuthResult($result, $this->redirectToDashboard($result->getUser()));
    }

    /**
     * Renders the account creation form.
     * 
     * Execution Flow:
     * 1. Return the registration HTML template.
     * 
     * Logic behind the logic:
     * - Even for simple form renders, utilizing a dedicated controller method 
     *   ensures all page loads pass through standard routing and middleware.
     */
    public function register(): Response
    {
        return $this->render('auth/register', [
            'title'   => 'Create Account',
        ]);
    }

    /**
     * Finalizes user registration.
     * 
     * Execution Flow:
     * 1. Validate the POST payload against RegisterRequest rules.
     * 2. Attempt to register the user via RegistrationService.
     * 3. If the service throws a ValidationException (e.g., email taken), flash errors and redirect.
     * 4. On success, the user is automatically logged in and redirected to their dashboard.
     * 
     * Logic behind the logic:
     * - We let the Domain layer (RegistrationService) handle business rule validation 
     *   (like email uniqueness), but we catch the domain exception here in the Controller 
     *   to handle the HTTP response orchestration gracefully.
     */
    public function store(): Response
    {
        $this->validateOrRedirect(new RegisterRequest($this->request, $this->validator), '/register');

        $data = $this->request->request();
        
        try {
            $user = $this->registrationService->registerUser($data);
            $this->authService->login($user);
        } catch (\Magma\validation\ValidationException $e) {
            $this->request->setSession('errors', $e->getErrors());
            $this->request->setSession('old', ['name' => $data['name'] ?? '', 'email' => $data['email'] ?? '']);
            return new RedirectResponse('/register');
        }

        return new RedirectResponse('/user');
    }

    /**
     * Destroys the user session.
     * 
     * Execution Flow:
     * 1. Check if a 'remember_user' cookie is present.
     * 2. If present, invalidate the token in the database and attach an expired cookie to the response.
     * 3. Call AuthenticationService::logout() to destroy the active PHP session.
     * 4. Redirect the user to the public homepage.
     * 
     * Logic behind the logic:
     * - We must explicitly invalidate the persistent token in the database; otherwise, 
     *   the user's old token would remain active and could be exploited if intercepted.
     */
    public function logout(): Response
    {
        $token = $this->request->cookie('remember_user');
        $result = $this->authService->logout($token);

        return $this->applyAuthResult($result, new RedirectResponse('/'));
    }

    /**
     * Helper to apply cookie instructions from an AuthenticationResult to an HTTP Response.
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
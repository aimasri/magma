<?php

namespace Magma\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\services\AuthenticationService;
use Magma\services\RegistrationService;
use Magma\requests\RegisterRequest;
use Magma\validation\Validator;
use Magma\view\TemplateEngine;

/**
 * Title: User Registration Controller
 *
 * Purpose:
 * - Handle the presentation and submission of the user registration form.
 * - Delegate the actual registration domain logic to RegistrationService.
 *
 * Why / Why this design:
 * - SRP: Keeps the HTTP parsing and redirection separate from the business logic of creating a user, assigning roles, and firing events.
 *
 * Teaching notes:
 * - Validation is handled cleanly through `RegisterRequest` abstraction before delegating to the service layer.
 */
class RegisterController extends BaseController
{
    protected Request $request;
    protected AuthenticationService $authService;
    protected RegistrationService $registrationService;
    protected Validator $validator;

    public function __construct(
        TemplateEngine $templateEngine, 
        \Magma\security\CsrfManager $csrfManager,
        \Magma\http\Session $session,
        Request $request, 
        AuthenticationService $authService, 
        RegistrationService $registrationService,
        Validator $validator
    ) {
        parent::__construct($templateEngine, $csrfManager, $session);
        $this->request = $request;
        $this->authService = $authService;
        $this->registrationService = $registrationService;
        $this->validator = $validator;
    }

    /**
     * Renders the registration form view.
     * 
     * @return Response
     */
    public function register(): Response
    {
        return $this->render('auth/register', [
            'title'   => 'Create Account',
        ]);
    }

    /**
     * Processes a registration form submission.
     * 
     * Execution Flow:
     * 1. Validates the incoming HTTP request payload against rules defined in RegisterRequest.
     * 2. If validation fails, redirects back to the form.
     * 3. Delegates the payload to RegistrationService to create the user and fire domain events.
     * 4. Automatically logs the new user into the session via AuthenticationService.
     * 5. Redirects the user to their dashboard.
     * 
     * @return Response
     */
    public function store(): Response
    {
        if ($redirect = $this->validateOrRedirect(new RegisterRequest($this->request, $this->validator), '/register')) {
            return $redirect;
        }

        $data = $this->request->request();
        
        try {
            $user = $this->registrationService->registerUser($data);
            $this->authService->login($user);
        } catch (\Magma\validation\ValidationException $e) {
            $this->session->set('errors', $e->getErrors());
            $this->session->set('old', ['name' => $data['name'] ?? '', 'email' => $data['email'] ?? '']);
            return new RedirectResponse('/register');
        }

        return new RedirectResponse('/user');
    }
}

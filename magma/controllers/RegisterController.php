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

    public function register(): Response
    {
        return $this->render('auth/register', [
            'title'   => 'Create Account',
        ]);
    }

    public function store(): Response
    {
        $this->validateOrRedirect(new RegisterRequest($this->request, $this->validator), '/register');

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

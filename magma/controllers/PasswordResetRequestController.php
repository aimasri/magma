<?php

declare(strict_types=1);

namespace Magma\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\requests\ForgotPasswordRequest;
use Magma\services\PasswordResetRequestService;
use Magma\enums\PasswordResetStatus;

/**
 * Title: Password Reset Request Controller
 *
 * Purpose:
 * - Orchestrates the request phase of the password recovery flow.
 *
 * Why this design:
 * - Split from PasswordResetController to adhere to SRP (distinct workflow from completion).
 *
 * Teaching notes:
 * - Emitting identical responses for both found and missing emails is a crucial architectural defense against User Enumeration attacks.
 */
class PasswordResetRequestController
{
    /**
     * Initializes the controller with required dependencies.
     * 
     * @param \Magma\view\HtmlResponseBuilderInterface $html Response builder for HTML pages.
     * @param \Magma\http\SessionInterface $session Session manager for flashing messages.
     * @param PasswordResetRequestService $passwordResetRequestService Service handling password reset requests.
     */
    public function __construct(
        private readonly \Magma\view\HtmlResponseBuilderInterface $html,
        private readonly \Magma\http\SessionInterface $session,
        private readonly PasswordResetRequestService $passwordResetRequestService
    ) {}

    /**
     * Renders the forgot password form.
     */
    public function forgotPassword(): Response
    {
        $status = $this->session->flash('reset_status');
        $error = $this->session->flash('reset_error');

        return $this->html->render('auth/forgot_password', [
            'title'   => 'Forgot Password',
            'status'  => $status,
            'error'   => $error,
        ]);
    }

    /**
     * Handles the submission of the forgot password form.
     */
    public function sendResetLink(ForgotPasswordRequest $forgotPasswordRequest, Request $request): Response 
    {
        $email = $request->request('email');
        $email = is_string($email) ? trim($email) : '';
        $status = $this->passwordResetRequestService->requestReset($email);

        if ($status === PasswordResetStatus::SUCCESS || $status === PasswordResetStatus::USER_NOT_FOUND) {
            $this->session->set('reset_status', 'If an account exists, a link has been sent.');
        } else {
            $this->session->set('reset_error', 'Failed to send email.');
        }

        return new RedirectResponse('/forgot-password');
    }
}

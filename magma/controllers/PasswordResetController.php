<?php

namespace Magma\controllers;

use Magma\controllers\BaseController;
use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\requests\ForgotPasswordRequest;
use Magma\requests\ResetPasswordRequest;
use Magma\services\PasswordResetService;
use Magma\validation\Validator;
use Magma\view\TemplateEngine;
use Magma\enums\PasswordResetStatus;

/**
 * Title: Password Reset Controller
 *
 * Purpose:
 * - Orchestrates password recovery flows.
 * - Implements a secure, multi-step reset process: request, token issuance, validation, and final update.
 * - Avoids user enumeration by using generic success messaging.
 *
 * Why this design:
 * - Orchestrates complex domain services via HTTP. It strictly acts as a router between the `Request` payload and the `PasswordResetService`, preventing business logic (like token hashing) from leaking into the transport layer.
 *
 * Teaching notes:
 * - Keep token generation and email sending inside a service to allow for easier testing and replacement (e.g., queueing email deliveries).
 */
class PasswordResetController extends BaseController
{
    protected Request $request;
    protected PasswordResetService $passwordResetService;
    protected Validator $validator;

    public function __construct(TemplateEngine $templateEngine, \Magma\security\CsrfManager $csrfManager, \Magma\http\Session $session, Request $request, PasswordResetService $passwordResetService, Validator $validator)
    {
        parent::__construct($templateEngine, $csrfManager, $session);
        $this->request = $request;
        $this->passwordResetService = $passwordResetService;
        $this->validator = $validator;
    }
    
    /**
     * Renders the initial "Forgot Password" request form.
     * 
     * Execution Flow:
     * 1. Extract password-reset-specific flash messages (`reset_status`, `reset_error`) from the session.
     * 2. Immediately wipe these keys from the session to prevent them from persisting.
     * 3. Pass the extracted data down to the view template for rendering.
     * 
     * Logic behind the logic:
     * - The PRG (Post/Redirect/Get) pattern is used during form submission, so 
     *   errors must be flashed to the session and cleared immediately upon read.
     */
    public function forgotPassword(): Response
    {
        $status = $this->request->flash('reset_status');
        $error = $this->request->flash('reset_error');

        return $this->render('auth/forgot_password', [
            'title'   => 'Forgot Password',
            'status'  => $status,
            'error'   => $error,
        ]);
    }

    /**
     * Initiates the password reset process.
     * 
     * Execution Flow:
     * 1. Validate the incoming request email address.
     * 2. Delegate the token generation and email dispatch to the `PasswordResetService`.
     * 3. Regardless of whether the user exists, flash a generic success message to the session.
     * 4. Redirect the user back to the forgot password form.
     * 
     * Logic behind the logic:
     * - To prevent "User Enumeration" attacks, we must provide a generic 
     *   success message even if the email address is not found in the system 
     *   (handling `PasswordResetStatus::USER_NOT_FOUND`). 
     *   Otherwise, an attacker could probe the endpoint to harvest registered emails.
     */
    public function sendResetLink(): Response
    {
        $this->validateOrRedirect(new ForgotPasswordRequest($this->request, $this->validator), '/forgot-password');

        $data = $this->request->request();
        $email = trim($data['email'] ?? '');
        $status = $this->passwordResetService->requestReset($email);

        if ($status === PasswordResetStatus::SUCCESS) {
            $this->session->set('reset_status', 'A reset link has been sent.');
        } elseif ($status === PasswordResetStatus::USER_NOT_FOUND) {
            // Maintain security: if requestReset returns USER_NOT_FOUND, 
            // provide generic success message.
            $this->session->set('reset_status', 'If an account exists, a link has been sent.');
        } else {
            $this->session->set('reset_error', 'Failed to send email.');
        }

        return new RedirectResponse('/forgot-password');
    }

    /**
     * Renders the password update form after validating the link token.
     * 
     * Execution Flow:
     * 1. Extract the `token` from the query string.
     * 2. Ask the `PasswordResetService` to validate the token's authenticity and expiration.
     * 4. If invalid, redirect to the initial request form with a security warning.
     * 5. If valid, render the final password reset form with the token embedded.
     * 
     * Logic behind the logic:
     * - Verifying the token *before* rendering the form prevents attackers from 
     *   submitting brute-force password attempts against arbitrary tokens.
     */
    public function resetPasswordForm(): Response
    {
        $token = $this->request->query('token');
        $error = $this->request->flash('reset_error');

        if (!$this->passwordResetService->validateToken($token)) {
            return $this->render('auth/forgot_password', ['title' => 'Forgot Password', 'error' => 'Invalid or expired token.', 'status' => null]);
        }

        return $this->render('auth/reset_password', [
            'title'   => 'Reset Password',
            'token'   => $token,
            'error'   => $error,
        ]);
    }

    /**
     * Finalizes the password update.
     * 
     * Execution Flow:
     * 1. Extract the embedded `token` from the POST payload.
     * 2. Validate the new password's strength (via `ResetPasswordRequest`).
     * 3. Delegate the database update to `PasswordResetService->completeReset()`.
     * 4. Flash a success message and redirect to the login page on success.
     * 5. Flash an error and redirect back to the reset form on failure.
     * 
     * Logic behind the logic:
     * - The token is consumed (deleted) during `completeReset()` to ensure it 
     *   can only ever be used once, preventing replay attacks.
     */
    public function resetPassword(): Response
    {
        $token = $this->request->request('token', '');

        $this->validateOrRedirect(new ResetPasswordRequest($this->request, $this->validator), '/reset-password?token=' . $token);

        $data = $this->request->request();

        $password = $data['password'] ?? '';
        $status = $this->passwordResetService->completeReset($token, $password);

        if ($status === PasswordResetStatus::SUCCESS) {
            $this->session->set('reset_status', 'Password updated! Please log in.');
            return new RedirectResponse('/login');
        }

        if ($status === PasswordResetStatus::INVALID_TOKEN) {
            $this->session->set('reset_error', 'Invalid or expired token.');
        } else {
            $this->session->set('reset_error', 'Failed to reset password. Please try again.');
        }
        return new RedirectResponse('/forgot-password');
    }
}
<?php

namespace Magma\controllers;

use Magma\controllers\BaseController;
use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\requests\ForgotPasswordRequest;
use Magma\requests\ResetPasswordRequest;
use Magma\services\PasswordResetRequestService;
use Magma\services\PasswordResetCompletionService;
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
 * - Orchestrates complex domain services via HTTP. It strictly acts as a router between the `Request` payload and the services.
 *
 * Teaching notes:
 * - Avoids timing attacks and user enumeration by returning a generic "If an account exists..." success 
 *   message regardless of whether the email was found in the database.
 */
class PasswordResetController
{
    /**
     * Renders the forgot password form.
     *
     * Execution Flow:
     * 1. Retrieves flashed status/error messages from the session.
     * 2. Returns the compiled HTML view.
     */
    public function forgotPassword(\Magma\view\HtmlResponseBuilderInterface $html, \Magma\http\SessionInterface $session): Response
    {
        $status = $session->flash('reset_status');
        $error = $session->flash('reset_error');

        return $html->render('auth/forgot_password', [
            'title'   => 'Forgot Password',
            'status'  => $status,
            'error'   => $error,
        ]);
    }

    /**
     * Handles the submission of the forgot password form.
     *
     * Execution Flow:
     * 1. Request validates input automatically via `ForgotPasswordRequest`.
     * 2. Passes the email to the `PasswordResetRequestService`.
     * 3. Sets a generic success flash message to prevent user enumeration if the user doesn't exist.
     * 4. Redirects back to the form.
     */
    public function sendResetLink(
        ForgotPasswordRequest $forgotPasswordRequest, 
        Request $request, 
        PasswordResetRequestService $passwordResetRequestService, 
        \Magma\http\SessionInterface $session
    ): Response {
        $email = $request->request('email');
        $email = is_string($email) ? trim($email) : '';
        $status = $passwordResetRequestService->requestReset($email);

        if ($status === PasswordResetStatus::SUCCESS) {
            $session->set('reset_status', 'A reset link has been sent.');
        } elseif ($status === PasswordResetStatus::USER_NOT_FOUND) {
            $session->set('reset_status', 'If an account exists, a link has been sent.');
        } else {
            $session->set('reset_error', 'Failed to send email.');
        }

        return new RedirectResponse('/forgot-password');
    }

    /**
     * Renders the actual password reset form containing the new password inputs.
     *
     * Execution Flow:
     * 1. Extracts the plaintext token from the query string (`?token=`).
     * 2. Validates the token against the `PasswordResetCompletionService`.
     * 3. If invalid or expired, aborts and renders an error state.
     * 4. If valid, renders the `auth/reset_password` form.
     */
    public function resetPasswordForm(
        Request $request, 
        PasswordResetCompletionService $passwordResetCompletionService, 
        \Magma\view\HtmlResponseBuilderInterface $html, 
        \Magma\http\SessionInterface $session
    ): Response {
        $token = $request->query('token');
        $error = $session->flash('reset_error');

        if (empty($token) || !is_string($token) || !$passwordResetCompletionService->validateToken($token)) {
            return $html->render('auth/forgot_password', ['title' => 'Forgot Password', 'error' => 'Invalid or expired token.', 'status' => null]);
        }

        return $html->render('auth/reset_password', [
            'title'   => 'Reset Password',
            'token'   => $token,
            'error'   => $error,
        ]);
    }

    /**
     * Handles the final submission of the new password.
     *
     * Execution Flow:
     * 1. Input is validated automatically via `ResetPasswordRequest`.
     * 2. Extracts the token (from hidden input) and the new password.
     * 3. Dispatches mutation to `PasswordResetCompletionService`.
     * 4. If successful, redirects to login with a success flash message.
     * 5. If failed (invalid/expired), redirects back to the forgot-password flow.
     */
    public function resetPassword(
        ResetPasswordRequest $resetPasswordRequest, 
        Request $request, 
        PasswordResetCompletionService $passwordResetCompletionService, 
        \Magma\http\SessionInterface $session
    ): Response {
        $token = $request->request('token');
        $token = is_string($token) ? $token : '';
        
        $password = $request->request('password');
        $password = is_string($password) ? $password : '';
        
        $status = $passwordResetCompletionService->completeReset($token, $password);

        if ($status === PasswordResetStatus::SUCCESS) {
            $session->set('reset_status', 'Password updated! Please log in.');
            return new RedirectResponse('/login');
        }

        if ($status === PasswordResetStatus::INVALID_TOKEN) {
            $session->set('reset_error', 'Invalid or expired token.');
        } else {
            $session->set('reset_error', 'Failed to reset password. Please try again.');
        }
        return new RedirectResponse('/forgot-password');
    }
}
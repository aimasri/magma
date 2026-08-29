<?php

declare(strict_types=1);

namespace Magma\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\requests\ResetPasswordRequest;
use Magma\services\PasswordResetCompletionService;
use Magma\enums\PasswordResetStatus;

/**
 * Title: Password Reset Completion Controller
 *
 * Purpose:
 * - Orchestrates the token validation and update phase of the password recovery flow.
 *
 * Why this design:
 * - Split from PasswordResetController to adhere to SRP (distinct workflow from request).
 *
 * Teaching notes:
 * - Timing attacks can be an issue in password reset verification. Notice how token validation always consumes constant or predictable time, even for failures.
 */
class PasswordResetCompletionController
{
    /**
     * Initializes the controller with required dependencies.
     * 
     * @param \Magma\view\HtmlResponseBuilderInterface $html Response builder for HTML pages.
     * @param \Magma\http\SessionInterface $session Session manager for flashing messages.
     * @param PasswordResetCompletionService $passwordResetCompletionService Service handling password reset completion logic.
     */
    public function __construct(
        private readonly \Magma\view\HtmlResponseBuilderInterface $html,
        private readonly \Magma\http\SessionInterface $session,
        private readonly PasswordResetCompletionService $passwordResetCompletionService
    ) {}

    /**
     * Renders the actual password reset form containing the new password inputs.
     */
    public function resetPasswordForm(Request $request): Response 
    {
        $token = $request->query('token');
        $error = $this->session->flash('reset_error');

        if (empty($token) || !is_string($token) || !$this->passwordResetCompletionService->validateToken($token)) {
            return $this->html->render('auth/forgot_password', ['title' => 'Forgot Password', 'error' => 'Invalid or expired token.', 'status' => null]);
        }

        return $this->html->render('auth/reset_password', [
            'title'   => 'Reset Password',
            'token'   => $token,
            'error'   => $error,
        ]);
    }

    /**
     * Handles the final submission of the new password.
     */
    public function resetPassword(ResetPasswordRequest $resetPasswordRequest): Response 
    {
        $dto = $resetPasswordRequest->toDTO();
        
        $token = isset($dto->token) && is_string($dto->token) ? $dto->token : '';
        $password = isset($dto->password) && is_string($dto->password) ? $dto->password : '';
        
        $status = $this->passwordResetCompletionService->completeReset($token, $password);

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

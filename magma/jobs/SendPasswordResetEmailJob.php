<?php

namespace Magma\jobs;

use Magma\queue\JobInterface;
use Magma\services\MailerService;
use Magma\mail\PasswordResetEmail;

/**
 * Title: Send Password Reset Email Job
 *
 * Purpose:
 * - Handles the asynchronous dispatch of password reset emails.
 *
 * Why this design:
 * - Adheres strictly to the Single Responsibility Principle. This class only knows how to extract email parameters from a payload and pass them to the MailerService.
 *
 * Teaching notes:
 * - Notice how this job is completely unaware of *where* it is running. It doesn't know it was popped from Redis, and it doesn't know it's running in a CLI daemon. It just does its job. This makes the code highly testable.
 */
class SendPasswordResetEmailJob implements JobInterface
{
    private MailerService $mailerService;
    private \Magma\queue\IdempotentProjectionGuard $guard;

    public function __construct(MailerService $mailerService, \Magma\queue\IdempotentProjectionGuard $guard)
    {
        $this->mailerService = $mailerService;
        $this->guard = $guard;
    }

    /**
     * Executes the email dispatch.
     *
     * Execution Flow:
     * 1. Extract `to_email`, `to_name`, and `reset_link` from the payload.
     * 2. Call the synchronous `sendPasswordResetEmail` on the MailerService.
     *
     * Logic behind the logic:
     * - The `$payload` array contains all the scalar context required to execute the job. 
     *   We do not pass complex objects (like a User model) into the queue because 
     *   objects might become stale between the time they are pushed to the queue 
     *   and when the worker finally executes them.
     */
    public function handle(array $payload): void
    {
        $toName = is_scalar($payload['to_name'] ?? null) ? (string)$payload['to_name'] : '';
        $resetLink = is_scalar($payload['reset_link'] ?? null) ? (string)$payload['reset_link'] : '';
        $toEmail = is_scalar($payload['to_email'] ?? null) ? (string)$payload['to_email'] : '';

        // The reset link is unique per password reset attempt, so it makes a perfect idempotency key
        $this->guard->guard('email_password_reset', md5($resetLink), function () use ($toName, $resetLink, $toEmail) {
            $mailable = new PasswordResetEmail(
                $toName,
                $resetLink
            );
            
            $this->mailerService->sendMailable(
                $toEmail,
                $mailable
            );
        });
    }
}

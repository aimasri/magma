<?php

namespace Magma\jobs;

use Magma\queue\JobInterface;
use Magma\services\MailerService;
use Magma\mail\WelcomeEmail;

/**
 * Title: Send Welcome Email Job
 *
 * Purpose:
 * - Handles the asynchronous dispatch of the welcome email for new users.
 *
 * Why this design:
 * - By decoupling the registration flow from the email dispatch, the user experiences a rapid signup process. The queue handles the heavy lifting of communicating with the SMTP server in the background.
 *
 * Teaching notes:
 * - This class perfectly demonstrates the Open/Closed Principle. We added new background functionality without needing to modify the `bin/worker.php` daemon at all.
 */
class SendWelcomeEmailJob implements JobInterface
{
    private MailerService $mailerService;

    public function __construct(MailerService $mailerService)
    {
        $this->mailerService = $mailerService;
    }

    /**
     * Executes the email dispatch.
     *
     * Execution Flow:
     * 1. Extract `to_email` and `to_name` from the payload.
     * 2. Call the synchronous `sendWelcomeEmail` on the MailerService.
     * 
     * Logic behind the logic:
     * - We expect scalar primitives in the `$payload` array rather than a 
     *   full User object to avoid stale state issues.
     */
    public function handle(array $payload): void
    {
        $toName = is_scalar($payload['to_name'] ?? null) ? (string)$payload['to_name'] : '';
        $toEmail = is_scalar($payload['to_email'] ?? null) ? (string)$payload['to_email'] : '';

        $mailable = new WelcomeEmail($toName);
        
        $this->mailerService->sendMailable(
            $toEmail,
            $mailable
        );
    }
}

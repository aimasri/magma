<?php

namespace Magma\services;

use Magma\view\TemplateEngine;

/**
 * Transactional Email Abstraction
 *
 * Purpose:
 * - Wrap outbound email delivery logic into a single cohesive service.
 * - Render HTML email templates bypassing the standard application layout.
 *
 * Why / Why this design:
 * - By encapsulating the transport mechanism, we decouple the application from the underlying 
 *   email provider. We can swap PHP's native `mail()` for a robust API library (like SendGrid 
 *   or AWS SES) by only modifying this single class.
 *
 * Teaching notes:
 * - This implementation processes `mail()` synchronously *within its execution context*. However, 
 *   to avoid blocking the user's browser during the HTTP request cycle, this service is exclusively 
 *   called by asynchronous background workers (like `SendWelcomeEmailJob`), maintaining strict SRP.
 */
class MailerService
{
    protected array $config;
    protected TemplateEngine $templateEngine;

    public function __construct(TemplateEngine $templateEngine, array $config)
    {
        $this->templateEngine = $templateEngine;
        $this->config = $config;
    }

    /**
     * Dispatches a recovery email to the user.
     * 
     * It constructs a multipart HTML message. Note that success here means the 
     * message was accepted by the local MTA; it does not guarantee final 
     * inbox delivery, which depends on server reputation and SPF/DKIM 
     * configurations.
     */
    public function sendPasswordResetEmail(string $toEmail, string $toName, string $resetLink): bool
    {
        $subject = 'Password Reset Request for Fussy Baby Cakes';

        /**
         * HTML Email Body
         * 
         * Render the email view template. The third parameter 'null' ensures 
         * the global website layout is bypassed.
         */
        $body = $this->templateEngine->render('emails/password_reset', [
            'toName' => $toName,
            'resetLink' => $resetLink
        ], null);

        return $this->send($toEmail, $subject, $body);
    }

    /**
     * Dispatches a welcome email to a newly registered user.
     * 
     * Execution Flow:
     * 1. Set the email subject and standard headers.
     * 2. Render the `emails/welcome.php` view into an HTML string.
     * 3. Dispatch via the local MTA.
     * 
     * Logic behind the logic:
     * - Like the password reset email, we catch exceptions to ensure that an 
     *   email failure does not crash the broader process (like a user logging in).
     */
    public function sendWelcomeEmail(string $toEmail, string $toName): bool
    {
        $subject = 'Welcome to FussyBaby!';

        $body = $this->templateEngine->render('emails/welcome', [
            'toName' => $toName
        ], null);

        return $this->send($toEmail, $subject, $body);
    }

    /**
     * Dispatch an email using the local MTA.
     *
     * Execution Flow:
     * 1. Normalize the headers array into a formatted string.
     * 2. Append generic MIME headers (MIME-Version and Content-Type) for HTML email support.
     * 3. Invoke the native PHP `mail()` function to dispatch the payload to the local Sendmail/Postfix process.
     *
     * Logic behind the logic:
     * - By centralizing the `mail()` call here, we eliminate redundant header string building 
     *   across different email templates. If we ever migrate to an external SMTP provider 
     *   (like SendGrid or AWS SES), we only have to rewrite this one private method.
     *
     * @param string $toEmail The recipient email address.
     * @param string $subject The email subject line.
     * @param string $body    The compiled HTML body.
     * @return bool True if accepted for delivery, false otherwise.
     */
    private function send(string $toEmail, string $subject, string $body): bool
    {
        try {
            // Standard headers for HTML email delivery
            $headers = "From: {$this->config['from_name']} <{$this->config['from_email']}>\r\n";
            $headers .= "Reply-To: {$this->config['from_email']}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            // The mail() function returns true on success, false on failure.
            // Note: Success only means the email was accepted by the local MTA, not necessarily delivered.
            $success = mail($toEmail, $subject, $body, $headers);

            if (!$success) {
                error_log("Email to {$toEmail} failed to be accepted by local MTA.");
            }

            return $success;
        } catch (\Exception $e) {
            error_log("Email to {$toEmail} failed: {$e->getMessage()}");
            return false;
        }
    }
}
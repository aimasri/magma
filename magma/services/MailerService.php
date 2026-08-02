<?php

namespace Magma\services;

use Magma\view\TemplateEngine;
use Magma\mail\MailableInterface;

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
    private TemplateEngine $templateEngine;
    private MailTransportInterface $transport;
    private array $config;

    /**
     * @param TemplateEngine $templateEngine Used to compile HTML templates.
     * @param MailTransportInterface $transport Used to send emails.
     * @param array $config Must contain 'from_email' and 'from_name'.
     */
    public function __construct(TemplateEngine $templateEngine, MailTransportInterface $transport, array $config)
    {
        $this->templateEngine = $templateEngine;
        $this->transport = $transport;
        $this->config = $config;
    }

    /**
     * Dispatches a mailable email object.
     * 
     * Execution Flow:
     * 1. Retrieve the subject from the mailable.
     * 2. Render the HTML body using the TemplateEngine.
     * 3. Dispatch via the local MTA.
     *
     * @param string $toEmail The recipient email address.
     * @param MailableInterface $mailable The mailable object to send.
     * @return bool True if accepted for delivery, false otherwise.
     */
    public function sendMailable(string $toEmail, MailableInterface $mailable): bool
    {
        $subject = $mailable->getSubject();
        $body = $mailable->renderBody($this->templateEngine);

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
        $headers = [
            'From' => "{$this->config['from_name']} <{$this->config['from_email']}>",
            'Reply-To' => $this->config['from_email'],
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8'
        ];

        return $this->transport->send($toEmail, $subject, $body, $headers);
    }
}
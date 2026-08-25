<?php

namespace Magma\services;

/**
 * Title: Native Mail Transport Service
 *
 * Purpose:
 * - Implements the MailTransportInterface using PHP's native mail() function.
 * - Formats and dispatches email headers and bodies.
 *
 * Why / Why this design:
 * - Acts as an Adapter over the native procedural mail() function, integrating it into the OOP ecosystem.
 * - Allows simple email dispatch without relying on external SMTP libraries for basic environments.
 *
 * Teaching notes:
 * - In enterprise applications, native mail() is often unreliable. Compare this to robust transports like SMTP or API-based mailers (e.g., SendGrid, Mailgun).
 * - Consider extending this to support attachments or multipart MIME messages.
 */
class NativeMailTransport implements MailTransportInterface
{
    /**
     * Sends an email using the native mail function.
     *
     * Execution Flow:
     * 1. Iterate over provided headers and format them into a standard string.
     * 2. Append default MIME and Content-Type headers if they are missing.
     * 3. Invoke PHP's native mail() function.
     * 4. Log failures if the local MTA rejects the mail or an exception occurs.
     *
     * Logic behind the logic:
     * - Graceful degradation: Catches exceptions and logs them rather than crashing the request, ensuring the user experience isn't interrupted by a transient mail server issue.
     *
     * @param string $toEmail
     * @param string $subject
     * @param string $body
     * @param array $headers
     * @return bool
     */
    public function send(string $toEmail, string $subject, string $body, array $headers = []): bool
    {
        try {
            $headerString = "";
            foreach ($headers as $key => $value) {
                $headerString .= "{$key}: {$value}\r\n";
            }
            
            // Standard headers for HTML email delivery if not provided
            if (empty($headers['MIME-Version'])) {
                $headerString .= "MIME-Version: 1.0\r\n";
            }
            if (empty($headers['Content-Type'])) {
                $headerString .= "Content-Type: text/html; charset=UTF-8\r\n";
            }

            $success = mail($toEmail, $subject, $body, $headerString);

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

<?php

namespace Magma\services;

/**
 * Title: Mail Transport Interface
 *
 * Purpose:
 * - Defines the contract for sending emails within the application.
 * - Abstracts the underlying mail delivery mechanism (e.g., Native, SMTP, API).
 *
 * Why this design:
 * - Strategy Pattern: Allows different email delivery strategies to be swapped at runtime (e.g., using a Mock transport during testing).
 * - Adheres to Dependency Inversion, ensuring high-level modules don't depend on low-level mail implementations.
 *
 * Teaching notes:
 * - A great example of isolating infrastructure concerns from business logic.
 * - When adding new transports, simply implement this interface and update the container bindings.
 */
interface MailTransportInterface
{
    /**
     * Dispatches an email to a specified recipient.
     *
     * @param string $toEmail
     * @param string $subject
     * @param string $body
     * @param array $headers
     * @return bool True if successful, false otherwise.
     */
    public function send(string $toEmail, string $subject, string $body, array $headers = []): bool;
}

<?php

declare(strict_types=1);

namespace Magma\mail;



/**
 * Title: Password Reset Mailable
 *
 * Purpose:
 * - Represents the password reset email notification.
 * - Binds the user's name and reset link to the `emails/password_reset` template.
 *
 * Why this design:
 * - Type Safety: Strongly types the required parameters (name, link) rather than relying on loose associative arrays.
 * - Encapsulation: Hides the exact subject line and template path from the calling controller/service.
 *
 * Teaching notes:
 * - Use the container's mailer service to send this object: `$mailer->send(new PasswordResetEmail(...))`.
 */

class PasswordResetEmail implements MailableInterface
{
    private const SUBJECT = 'Password Reset Request for Magma Framework';
    private const TEMPLATE = 'emails/password_reset';

    private string $toName;
    private string $resetLink;

    /**
     * Initializes the PasswordResetEmail mailable.
     *
     * @param string $toName The recipient's name.
     * @param string $resetLink The generated password reset URL.
     */
    public function __construct(string $toName, string $resetLink)
    {
        $this->toName = $toName;
        $this->resetLink = $resetLink;
    }

    /**
     * Retrieves the email subject.
     *
     * @return string
     */
    public function getSubject(): string
    {
        return self::SUBJECT;
    }

    /**
     * Retrieves the view template path.
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return self::TEMPLATE;
    }

    /**
     * Retrieves the variables to bind to the template.
     *
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return [
            'toName' => $this->toName,
            'resetLink' => $this->resetLink
        ];
    }
}

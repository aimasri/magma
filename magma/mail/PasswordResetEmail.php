<?php

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
    private string $toName;
    private string $resetLink;

    public function __construct(string $toName, string $resetLink)
    {
        $this->toName = $toName;
        $this->resetLink = $resetLink;
    }

    public function getSubject(): string
    {
        return 'Password Reset Request for Magma Framework';
    }

    public function getTemplate(): string
    {
        return 'emails/password_reset';
    }

    public function getVariables(): array
    {
        return [
            'toName' => $this->toName,
            'resetLink' => $this->resetLink
        ];
    }
}

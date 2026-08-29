<?php

declare(strict_types=1);

namespace Magma\mail;



/**
 * Title: Welcome Mailable
 *
 * Purpose:
 * - Represents the welcome email notification sent upon user registration.
 * - Binds the user's name to the `emails/welcome` template.
 *
 * Why this design:
 * - Type Safety: Strongly types the required parameters (name) rather than relying on loose associative arrays.
 * - Encapsulation: Hides the exact subject line and template path from the calling controller/service.
 *
 * Teaching notes:
 * - This mailable is typically dispatched by an EventListener reacting to a `UserRegisteredEvent`.
 */

class WelcomeEmail implements MailableInterface
{
    private const SUBJECT = 'Welcome to Magma!';
    private const TEMPLATE = 'emails/welcome';

    private string $toName;

    /**
     * Initializes the WelcomeEmail mailable.
     *
     * @param string $toName The recipient's name.
     */
    public function __construct(string $toName)
    {
        $this->toName = $toName;
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
            'toName' => $this->toName
        ];
    }
}

<?php

namespace Magma\mail;

use Magma\view\TemplateEngine;

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
    private string $toName;

    public function __construct(string $toName)
    {
        $this->toName = $toName;
    }

    public function getSubject(): string
    {
        return 'Welcome to Magma!';
    }

    public function renderBody(TemplateEngine $engine): string
    {
        return $engine->render('emails/welcome', [
            'toName' => $this->toName
        ], null);
    }
}

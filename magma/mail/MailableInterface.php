<?php

namespace Magma\mail;



/**
 * Title: Mailable Interface
 *
 * Purpose:
 * - Defines the contract for all email notification classes.
 * - Bridges raw data and the template engine to produce a rendered HTML body.
 *
 * Why this design:
 * - Builder Pattern variant: Encapsulates the specific template name, subject, and variable binding for each distinct email type (e.g., Welcome, Password Reset).
 * - Open/Closed Principle: Adding a new email type simply requires creating a new Mailable class, rather than bloating a single `EmailService`.
 *
 * Teaching notes:
 * - Mailables should only contain scalar data and small DTOs necessary for rendering. Avoid injecting heavy services like Repositories.
 */

interface MailableInterface
{
    public function getSubject(): string;
    public function getTemplate(): string;

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array;
}

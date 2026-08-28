<?php

declare(strict_types=1);

namespace Magma\listeners;

use Magma\domain\events\PasswordResetRequestedEvent;
use Magma\queue\QueueInterface;
use Magma\routing\UrlGenerator;
use Magma\jobs\SendPasswordResetEmailJob;

/**
 * Title: Send Password Reset Email Listener
 *
 * Purpose:
 * - Coordinates the creation of an email job when a password reset is requested.
 * - Bridges the event domain to the background queueing infrastructure.
 *
 * Why this design:
 * - Employs the Outbox pattern. Instead of sending emails synchronously or pushing directly to Redis during the web request, it records the intent in the database outbox, ensuring atomicity with the password token generation.
 *
 * Teaching notes:
 * - Listeners should strictly avoid heavy domain logic. Their role is to translate domain events into infrastructure side-effects (like queue jobs).
 */
class SendPasswordResetEmailListener
{
    private \Magma\database\OutboxJobRepositoryInterface $outboxJobRepository;
    private UrlGenerator $urlGenerator;

    public function __construct(\Magma\database\OutboxJobRepositoryInterface $outboxJobRepository, UrlGenerator $urlGenerator)
    {
        $this->outboxJobRepository = $outboxJobRepository;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Handles the password reset requested event.
     *
     * Execution Flow:
     * 1. Generates an absolute URL for the reset link using the provided token.
     * 2. Packages the email intent and URL into a DTO payload.
     * 3. Records the job in the database outbox to guarantee delivery.
     *
     * Logic behind the logic:
     * - We catch \Throwable around the outbox recording to prevent an infrastructure failure from bubbling up and crashing the user's web request. The user should still see a success message, while ops is notified via error log.
     */
    public function handle(PasswordResetRequestedEvent $event): void
    {
        $resetLink = $this->urlGenerator->generateAbsolute('/reset-password', ['token' => $event->token]);
        
        $payload = [
            'to_email' => $event->email,
            'to_name' => $event->name,
            'reset_link' => $resetLink
        ];

        try {
            $jobDto = new \Magma\dto\OutboxJobDTO(
                'emails',
                SendPasswordResetEmailJob::class,
                $payload
            );
            $this->outboxJobRepository->record($jobDto);
        } catch (\Throwable $e) {
            error_log("Failed to queue password reset email in outbox: " . $e->getMessage());
        }
    }
}

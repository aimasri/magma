<?php

declare(strict_types=1);

namespace Magma\listeners;

use Magma\domain\events\PasswordResetRequestedEvent;
use Magma\events\EventListenerInterface;
use Magma\database\OutboxJobRepositoryInterface;
use Magma\routing\UrlGenerator;
use Magma\jobs\SendPasswordResetEmailJob;
use Magma\dto\OutboxJobDTO;

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
class SendPasswordResetEmailListener implements EventListenerInterface
{
    private OutboxJobRepositoryInterface $outboxJobRepository;
    private UrlGenerator $urlGenerator;

    public function __construct(OutboxJobRepositoryInterface $outboxJobRepository, UrlGenerator $urlGenerator)
    {
        $this->outboxJobRepository = $outboxJobRepository;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Handles the password reset requested event.
     *
     * Execution Flow:
     * 1. Generates an absolute URL for the reset link using the provided token.
     * 2. Packages the email intent and URL into an OutboxJobDTO payload.
     * 3. Records the job in the database outbox to guarantee delivery.
     *
     * @param mixed $event The dispatched event object.
     */
    public function handle(mixed $event): void
    {
        assert($event instanceof PasswordResetRequestedEvent);
        $resetLink = $this->urlGenerator->generateAbsolute('/reset-password', ['token' => $event->token]);
        
        $jobDto = new OutboxJobDTO(
            'emails',
            SendPasswordResetEmailJob::class,
            [
                'to_email' => $event->email,
                'to_name' => $event->name,
                'reset_link' => $resetLink
            ]
        );

        $this->outboxJobRepository->record($jobDto);
    }
}

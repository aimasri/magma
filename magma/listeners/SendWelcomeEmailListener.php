<?php

namespace Magma\listeners;

use Magma\domain\events\UserRegisteredEvent;
use Magma\jobs\SendWelcomeEmailJob;

/**
 * Title: Send Welcome Email Listener
 *
 * Purpose:
 * - Listens for the UserRegisteredEvent and dispatches an asynchronous job to send
 *   the welcome email to the new user.
 *
 * Why / Why this design:
 * - Decouples the secondary side-effect (sending an email) from the primary business
 *   logic (registering a user). The RegistrationService doesn't need to know about
 *   email queues or mailers anymore.
 *
 * Teaching notes:
 * - Notice how the listener depends on the `OutboxJobRepositoryInterface`. Even though it's reacting
 *   to an event, it still pushes the actual email sending to a background job to keep
 *   the HTTP response fast for the user. By writing to the Outbox, we guarantee at-least-once delivery.
 */
class SendWelcomeEmailListener
{
    private \Magma\database\OutboxJobRepositoryInterface $outboxJobRepository;

    /**
     * Initializes the SendWelcomeEmailListener.
     *
     * @param \Magma\database\OutboxJobRepositoryInterface $outboxJobRepository Used to record the outbox job reliably.
     */
    public function __construct(\Magma\database\OutboxJobRepositoryInterface $outboxJobRepository)
    {
        $this->outboxJobRepository = $outboxJobRepository;
    }

    /**
     * Handles the user registered event.
     *
     * Execution Flow:
     * 1. Extracts the user's email and name from the event payload.
     * 2. Constructs an OutboxJobDTO intended for the emails queue.
     * 3. Records the job in the database outbox.
     *
     * Logic behind the logic:
     * - Using the outbox pattern guarantees that the email job is recorded reliably and avoids data loss if the queue system is temporarily down.
     *
     * @param UserRegisteredEvent $event The domain event triggered upon user registration.
     */
    public function handle(UserRegisteredEvent $event): void
    {
        $jobDto = new \Magma\dto\OutboxJobDTO(
            'emails',
            SendWelcomeEmailJob::class,
            [
                'to_email' => $event->registration->getEmail(),
                'to_name'  => $event->registration->getName()
            ]
        );
        
        $this->outboxJobRepository->record($jobDto);
    }
}

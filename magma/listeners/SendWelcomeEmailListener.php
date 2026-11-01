<?php

declare(strict_types=1);

namespace Magma\listeners;

use Magma\domain\events\UserRegisteredEvent;
use Magma\events\TransactionalListenerInterface;
use Magma\jobs\SendWelcomeEmailJob;
use Magma\database\OutboxJobRepositoryInterface;
use Magma\dto\OutboxJobDTO;

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
class SendWelcomeEmailListener implements TransactionalListenerInterface
{
    private OutboxJobRepositoryInterface $outboxJobRepository;
    private \Magma\security\TenantContext $tenantContext;

    /**
     * Initializes the SendWelcomeEmailListener.
     *
     * @param OutboxJobRepositoryInterface $outboxJobRepository Used to record the outbox job reliably.
     * @param \Magma\security\TenantContext $tenantContext Used to capture active tenant context.
     */
    public function __construct(
        OutboxJobRepositoryInterface $outboxJobRepository,
        \Magma\security\TenantContext $tenantContext
    ) {
        $this->outboxJobRepository = $outboxJobRepository;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Handles the user registered event.
     *
     * Execution Flow:
     * 1. Extracts the user's email, name, and user ID from the event payload.
     * 2. Captures current tenant context.
     * 3. Constructs an OutboxJobDTO intended for the emails queue.
     * 4. Records the job in the database outbox.
     *
     * @param mixed $event The domain event triggered upon user registration.
     */
    public function handle(mixed $event): void
    {
        assert($event instanceof UserRegisteredEvent);
        $tenantId = $this->tenantContext->getTenantId();

        $jobDto = new OutboxJobDTO(
            'emails',
            SendWelcomeEmailJob::class,
            [
                'user_id'   => $event->userId,
                'to_email'  => $event->registration->getEmail(),
                'to_name'   => $event->registration->getName(),
                'tenant_id' => $tenantId,
            ],
            tenantId: $tenantId
        );
        
        $this->outboxJobRepository->record($jobDto);
    }
}

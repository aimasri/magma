<?php

namespace Magma\listeners;

use Magma\domain\events\UserRegisteredEvent;
use Magma\queue\QueueInterface;
use Magma\jobs\SendWelcomeEmailJob;

/**
 * Send Welcome Email Listener
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
 * - Notice how the listener depends on the `QueueInterface`. Even though it's reacting
 *   to an event, it still pushes the actual email sending to a background job to keep
 *   the HTTP response fast for the user.
 */
class SendWelcomeEmailListener
{
    private QueueInterface $queue;

    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
    }

    public function handle(UserRegisteredEvent $event): void
    {
        $payload = json_encode([
            \Magma\queue\JobInterface::HANDLER_KEY => SendWelcomeEmailJob::class,
            \Magma\queue\JobInterface::PAYLOAD_KEY => [
                'to_email' => $event->user['email'],
                'to_name'  => $event->user['name']
            ]
        ]);
        
        try {
            $this->queue->push('emails', $payload);
        } catch (\Throwable $e) {
            error_log("Failed to push welcome email to queue: " . $e->getMessage());
        }
    }
}

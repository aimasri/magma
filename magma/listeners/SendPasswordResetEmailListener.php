<?php

declare(strict_types=1);

namespace Magma\listeners;

use Magma\domain\events\PasswordResetRequestedEvent;
use Magma\queue\QueueInterface;
use Magma\routing\UrlGenerator;
use Magma\jobs\SendPasswordResetEmailJob;

class SendPasswordResetEmailListener
{
    private QueueInterface $queue;
    private UrlGenerator $urlGenerator;

    public function __construct(QueueInterface $queue, UrlGenerator $urlGenerator)
    {
        $this->queue = $queue;
        $this->urlGenerator = $urlGenerator;
    }

    public function handle(PasswordResetRequestedEvent $event): void
    {
        $resetLink = $this->urlGenerator->generateAbsolute('/reset-password', ['token' => $event->token]);
        
        $payload = [
            'to_email' => $event->email,
            'to_name' => $event->name,
            'reset_link' => $resetLink
        ];

        try {
            $this->queue->push('emails', SendPasswordResetEmailJob::class, $payload);
        } catch (\Throwable $e) {
            error_log("Failed to queue password reset email: " . $e->getMessage());
        }
    }
}

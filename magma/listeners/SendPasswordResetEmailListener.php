<?php

declare(strict_types=1);

namespace Magma\listeners;

use Magma\domain\events\PasswordResetRequestedEvent;
use Magma\queue\QueueInterface;
use Magma\routing\UrlGenerator;
use Magma\jobs\SendPasswordResetEmailJob;

class SendPasswordResetEmailListener
{
    private \Magma\database\OutboxJobRepositoryInterface $outboxJobRepository;
    private UrlGenerator $urlGenerator;

    public function __construct(\Magma\database\OutboxJobRepositoryInterface $outboxJobRepository, UrlGenerator $urlGenerator)
    {
        $this->outboxJobRepository = $outboxJobRepository;
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

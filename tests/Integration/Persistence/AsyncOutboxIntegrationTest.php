<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use Magma\queue\AbstractDomainWorkerJob;
use PHPUnit\Framework\Attributes\Test;

class DummyOutboxJob extends AbstractDomainWorkerJob
{
    public static array $processedPayloads = [];

    protected function getRequiredPayloadKeys(): array
    {
        return ['message'];
    }

    protected function execute(array $payload): void
    {
        self::$processedPayloads[] = $payload['message'];
    }
}

class AsyncOutboxIntegrationTest extends AsyncIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DummyOutboxJob::$processedPayloads = [];
        
        // Ensure outbox_jobs exists, as some environments might not have run full schema
        $this->dbManager->getWriteConnection()->exec("
            CREATE TABLE IF NOT EXISTS outbox_jobs (
                id BIGSERIAL PRIMARY KEY,
                queue VARCHAR(255) NOT NULL,
                handler VARCHAR(255) NOT NULL,
                payload JSONB NOT NULL,
                headers JSONB DEFAULT '{}'::jsonb,
                attempts INTEGER DEFAULT 0,
                locked_at TIMESTAMP,
                last_error TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    #[Test]
    public function it_processes_pending_outbox_events_synchronously(): void
    {
        $db = $this->dbManager->getWriteConnection();
        
        $payload = json_encode(['message' => 'hello world']);
        
        $stmt = $db->prepare("INSERT INTO outbox_jobs (queue, handler, payload) VALUES (:queue, :handler, :payload)");
        $stmt->execute([
            'queue' => 'default',
            'handler' => DummyOutboxJob::class,
            'payload' => $payload
        ]);
        
        $this->assertCount(0, DummyOutboxJob::$processedPayloads);
        
        $processed = $this->processPendingOutboxEvents();
        
        $this->assertEquals(1, $processed);
        $this->assertCount(1, DummyOutboxJob::$processedPayloads);
        $this->assertEquals('hello world', DummyOutboxJob::$processedPayloads[0]);
        
        // Ensure it was deleted from outbox
        $count = $db->query("SELECT COUNT(*) FROM outbox_jobs")->fetchColumn();
        $this->assertEquals(0, $count);
    }
}

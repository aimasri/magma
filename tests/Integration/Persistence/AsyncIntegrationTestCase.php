<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use Magma\queue\JobInterface;
use Magma\queue\AbstractDomainWorkerJob;
use PDO;

/**
 * Title: Async Integration Test Case
 *
 * Purpose:
 * - Provides a test harness for safely testing Domain Events and background jobs.
 * - Allows tests to execute the transactional outbox manually and synchronously.
 *
 * Why / Why this design:
 * - Deterministic Testing: By manually flushing the outbox and invoking handlers
 *   synchronously in the test runner, we avoid timing issues, brittle sleeps, 
 *   and reliance on external daemons.
 */
abstract class AsyncIntegrationTestCase extends DatabaseIntegrationTestCase
{
    /**
     * Manually queries the outbox table, extracts events, and dispatches them
     * to their registered handlers synchronously.
     *
     * @return int The number of processed events.
     */
    protected function processPendingOutboxEvents(): int
    {
        $db = $this->dbManager->getWriteConnection();

        // Magma core uses 'outbox_jobs' as defined in schema.sql
        $stmt = $db->query("SELECT * FROM outbox_jobs WHERE locked_at IS NULL ORDER BY id ASC");
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processedCount = 0;

        foreach ($jobs as $job) {
            $handlerClass = $job['handler'];
            $payload = json_decode($job['payload'], true);

            if (class_exists($handlerClass)) {
                $handler = $this->container->get($handlerClass);
                
                if ($handler instanceof JobInterface) {
                    $handler->handle($payload);
                    $processedCount++;
                }
            }

            // Remove the job from the outbox to simulate successful publish/processing
            $delStmt = $db->prepare("DELETE FROM outbox_jobs WHERE id = :id");
            $delStmt->execute(['id' => $job['id']]);
        }

        return $processedCount;
    }
}

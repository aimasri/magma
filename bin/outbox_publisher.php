#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Title: Asynchronous Outbox Publisher CLI Daemon
 *
 * Purpose:
 * - Continuously polls the transactional outbox table for locked pending jobs and dispatches
 *   them to destination message queues (e.g. RedisQueue, SQS, RabbitMQ).
 * - Deletes successfully published outbox records in atomic batches.
 *
 * Why / Why this design:
 * - Guaranteed At-Least-Once Delivery: Combines PostgreSQL row locking (`FOR UPDATE SKIP LOCKED`)
 *   with queue publishing so that crashes never drop messages and multiple publisher processes
 *   can run simultaneously across distributed nodes without race conditions or duplicate dispatch.
 * - Connection and Memory Safety: Explicitly disconnects idle database connections and manages
 *   execution time limits to provide stability for systemd / Supervisor daemon processes.
 *
 * Teaching notes:
 * - Notice how publishing and outbox deletion happen inside a transactional boundary.
 *   If the worker process is killed before deletion, the row locks are automatically released
 *   by PostgreSQL, allowing another daemon instance to process them without data loss.
 *
 * Usage:
 * - Run directly or via Supervisor: `php bin/outbox_publisher.php`
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../magma/config/bootstrap.php';

use Magma\database\OutboxJobRepositoryInterface;
use Magma\database\OutboxJobRepository;
use Magma\queue\QueueInterface;
use Magma\database\DatabaseTransactionManager;
use Magma\database\DatabaseConnectionManager;

// Configure graceful termination signals if pcntl is installed
$keepRunning = true;
if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    $signalHandler = function (int $signal) use (&$keepRunning) {
        echo "[" . date('Y-m-d H:i:s') . "] Signal {$signal} received. Initiating graceful shutdown...\n";
        $keepRunning = false;
    };
    pcntl_signal(SIGTERM, $signalHandler);
    pcntl_signal(SIGINT, $signalHandler);
}

/** @var OutboxJobRepositoryInterface $outboxRepo */
$outboxRepo = $container->has(OutboxJobRepositoryInterface::class)
    ? $container->get(OutboxJobRepositoryInterface::class)
    : $container->get(OutboxJobRepository::class);

/** @var QueueInterface $queue */
$queue = $container->get(QueueInterface::class);

/** @var DatabaseTransactionManager $txManager */
$txManager = $container->get(DatabaseTransactionManager::class);

/** @var DatabaseConnectionManager $dbManager */
$dbManager = $container->get(DatabaseConnectionManager::class);

$batchSize = 100;
$idleSleepMicroseconds = 1000000; // 1 second

echo "[" . date('Y-m-d H:i:s') . "] Outbox publisher daemon started. Polling outbox_jobs...\n";

while ($keepRunning) {
    try {
        set_time_limit(60);

        $processedCount = $txManager->transactional(function () use ($outboxRepo, $queue, $batchSize): int {
            $jobs = $outboxRepo->fetchAndLockPending($batchSize);
            if (empty($jobs)) {
                return 0;
            }

            $publishedIds = [];
            foreach ($jobs as $job) {
                try {
                    $queue->push($job['queue'], $job['handler'], $job['payload']);
                    $publishedIds[] = $job['id'];
                } catch (\Throwable $publishError) {
                    echo "[" . date('Y-m-d H:i:s') . "] Failed to publish outbox job ID {$job['id']}: " . $publishError->getMessage() . "\n";
                    $outboxRepo->releaseOrMarkFailed($job['id'], $publishError->getMessage());
                }
            }

            if (!empty($publishedIds)) {
                $outboxRepo->deleteBatch($publishedIds);
            }

            return count($publishedIds);
        });

        if ($processedCount > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Published and cleared {$processedCount} outbox jobs.\n";
            // Check for more immediate jobs without sleeping
            continue;
        }

        // Idle state: disconnect database connection and sleep to save server resources
        $dbManager->disconnect();
        usleep($idleSleepMicroseconds);

    } catch (\Throwable $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Outbox Publisher Error: " . $e->getMessage() . "\n";
        $dbManager->disconnect();
        sleep(2);
    } finally {
        set_time_limit(0);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Outbox publisher daemon shut down successfully.\n";
exit(0);

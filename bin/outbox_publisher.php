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
use Magma\database\DatabaseConnectionManager;
use Magma\queue\QueueInterface;
use Magma\config\Config;

/** @var OutboxJobRepositoryInterface $outboxRepo */
$outboxRepo = $container->has(OutboxJobRepositoryInterface::class)
    ? $container->get(OutboxJobRepositoryInterface::class)
    : $container->get(OutboxJobRepository::class);

/** @var QueueInterface $queue */
$queue = $container->get(QueueInterface::class);

/** @var DatabaseConnectionManager $dbManager */
$dbManager = $container->get(DatabaseConnectionManager::class);

/** @var \Magma\contracts\ClockInterface $clock */
$clock = $container->get(\Magma\contracts\ClockInterface::class);

$batchSize = (int)Config::get('OUTBOX_BATCH_SIZE', 100);
$idleSleepMicroseconds = (int)Config::get('OUTBOX_SLEEP_MICROSECONDS', 1000000); // 1 second
$timeLimit = (int)Config::get('OUTBOX_TIME_LIMIT', 60);

// Unique distributed fencing token for this daemon instance
$workerId = gethostname() . ':' . getmypid() . ':' . bin2hex(random_bytes(4));

// Configure graceful termination signals if pcntl is installed
$keepRunning = true;
if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    $signalHandler = function (int $signal) use (&$keepRunning, $clock) {
        echo "[" . $clock->now()->format('Y-m-d H:i:s') . "] Signal {$signal} received. Initiating graceful shutdown...\n";
        $keepRunning = false;
    };
    pcntl_signal(SIGTERM, $signalHandler);
    pcntl_signal(SIGINT, $signalHandler);
    if (defined('SIGALRM')) {
        pcntl_signal(SIGALRM, function () use ($timeLimit) {
            throw new \RuntimeException("Outbox publisher iteration timeout exceeded ({$timeLimit}s).");
        });
    }
}

echo "[" . $clock->now()->format('Y-m-d H:i:s') . "] Outbox publisher daemon started (worker: {$workerId}). Polling outbox_jobs...\n";

while ($keepRunning) {
    try {
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm($timeLimit);
        }
        set_time_limit($timeLimit);

        // Claim pending batch using distributed fencing token and lease expiration
        $jobs = method_exists($outboxRepo, 'claimPending')
            ? $outboxRepo->claimPending($workerId, $batchSize, $timeLimit)
            : $outboxRepo->fetchAndLockPending($batchSize);

        if (empty($jobs)) {
            // Idle state: disconnect database connection and sleep to conserve pool resources
            $dbManager->disconnect();
            usleep($idleSleepMicroseconds);
            continue;
        }

        $publishedIds = [];
        foreach ($jobs as $job) {
            try {
                // Dispatch to destination queue outside database transaction
                $queue->push($job['queue'], $job['handler'], $job['payload']);
                $publishedIds[] = $job['id'];
            } catch (\Throwable $publishError) {
                echo "[" . $clock->now()->format('Y-m-d H:i:s') . "] Failed to publish outbox job ID {$job['id']}: " . $publishError->getMessage() . "\n";
                $outboxRepo->releaseOrMarkFailed($job['id'], $publishError->getMessage());
            }
        }

        // Delete successfully dispatched records in a batched delete protected by worker lease token
        if (!empty($publishedIds)) {
            if (method_exists($outboxRepo, 'deleteClaimedBatch')) {
                $outboxRepo->deleteClaimedBatch($publishedIds, $workerId);
            } else {
                $outboxRepo->deleteBatch($publishedIds);
            }
            echo "[" . $clock->now()->format('Y-m-d H:i:s') . "] Published and cleared " . count($publishedIds) . " outbox jobs.\n";
        }

    } catch (\Throwable $e) {
        echo "[" . $clock->now()->format('Y-m-d H:i:s') . "] Outbox Publisher Error: " . $e->getMessage() . "\n";
        $dbManager->disconnect();
        sleep(2);
    } finally {
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm(0);
        }
        set_time_limit(0);
    }
}

echo "[" . $clock->now()->format('Y-m-d H:i:s') . "] Outbox publisher daemon shut down successfully.\n";
exit(0);

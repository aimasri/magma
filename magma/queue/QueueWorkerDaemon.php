<?php

declare(strict_types=1);

namespace Magma\queue;

use Magma\container\Container;
use Magma\queue\QueueInterface;
use Throwable;

/**
 * Title: Queue Worker Daemon
 *
 * Purpose:
 * - Coordinates the polling of queue jobs from a backend (e.g., Redis).
 * - Handles process signals for graceful shutdown.
 * - Deserializes job payloads, instantiates handlers, and processes jobs.
 *
 * Why this design:
 * - Implements a Long-Running Process pattern. It flushes the dependency container between jobs to prevent memory leaks and tenant data contamination in a persistent PHP process.
 *
 * Teaching notes:
 * - Daemons require careful memory management (e.g., checking memory usage and exiting if threshold is breached) because PHP is typically designed for short-lived request lifecycles.
 */
class QueueWorkerDaemon
{
    private Container $container;
    private QueueInterface $queue;
    private \Magma\logging\LoggerInterface $logger;

    private bool $running = true;

    /**
     * Initializes the queue worker daemon with its required dependencies.
     *
     * Logic behind the logic:
     * - Injects the DI container to instantiate job handlers dynamically.
     * - Uses the QueueInterface for polling jobs and the LoggerInterface for robust daemon telemetry.
     *
     * @param Container $container
     * @param QueueInterface $queue
     * @param \Magma\logging\LoggerInterface $logger
     */
    public function __construct(Container $container, QueueInterface $queue, \Magma\logging\LoggerInterface $logger)
    {
        $this->container = $container;
        $this->queue = $queue;
        $this->logger = $logger;
    }

    public function run(string $queueName = 'emails'): void
    {
        $this->logger->info("Worker started. Listening for jobs on '{$queueName}' queue...");

        while ($this->running) {
            // Flush DI container state to prevent tenant data leakage across jobs
            $this->container->flushInstances();

            try {
                $jobString = $this->queue->pop($queueName, 0);
            } catch (\Throwable $e) {
                $this->logger->critical("Queue pop failed (e.g. Redis offline). Sleeping for 5s.", ['exception' => $e->getMessage()]);
                sleep(5);
                continue;
            }

            if ($jobString) {
                $this->processJob($jobString, $queueName);
            }

            // Prevent OOM in long-running processes (128MB threshold)
            if (memory_get_usage() > 134217728) {
                $this->logger->warning("Memory limit exceeded (128MB). Exiting daemon safely.");
                exit(0);
            }
        }
    }

    /**
     * Processes a single job popped from the queue.
     *
     * Execution Flow:
     * 1. Decodes the JSON payload to extract the job handler class and its parameters.
     * 2. Validates the handler class existence and ensures it implements JobInterface.
     * 3. Registers a PCNTL alarm to enforce a strict execution timeout (e.g., 120 seconds).
     * 4. Resolves the handler from the container and invokes its handle() method.
     * 5. Catches any exceptions, logs the error, and either re-queues the job or routes it to the dead-letter queue (failed_jobs) based on the attempt count.
     * 6. Cleans up by disabling the PCNTL alarm and disconnecting the database to prevent connection drops on long idle periods.
     *
     * Logic behind the logic:
     * - Disconnecting the database at the end of each job prevents "MySQL server has gone away" errors
     *   during idle periods when the daemon is waiting for new jobs.
     * - The PCNTL alarm prevents hanging jobs (e.g., infinite loops or stuck network calls) from permanently blocking the worker.
     *
     * @param string $jobString The JSON-encoded job payload.
     * @param string $queueName The name of the queue being processed.
     * @return void
     */
    private function processJob(string $jobString, string $queueName): void
    {
        $job = json_decode($jobString, true);

        if (!is_array($job)) {
            $this->logger->warning("Invalid job payload format. Pushing to DLQ.");
            $this->queue->push('failed_jobs', 'UnknownHandler', [
                'raw_payload' => $jobString,
                'error' => 'json_decode failed or payload is not an array',
                'failed_at' => date('c')
            ]);
            return;
        }

        $handlerClass = $job[\Magma\queue\JobInterface::HANDLER_KEY] ?? null;

        if (is_string($handlerClass) && class_exists($handlerClass)) {
            $this->logger->info("Received job: " . $handlerClass);
            try {
                if (function_exists('pcntl_alarm') && function_exists('pcntl_signal')) {
                    pcntl_signal(SIGALRM, function () {
                        throw new \RuntimeException("Job execution timeout exceeded (120s).");
                    });
                    pcntl_alarm(120);
                }

                $handler = $this->container->get($handlerClass);
                
                if (!$handler instanceof \Magma\queue\JobInterface) {
                    throw new \RuntimeException("Job handler does not implement JobInterface");
                }

                $payload = $job[\Magma\queue\JobInterface::PAYLOAD_KEY] ?? [];
                if (!is_array($payload)) {
                    $payload = [];
                }
                
                $attempts = $payload['attempts'] ?? 0;
                $payload['attempts'] = $attempts + 1;

                $handler->handle($payload);
                $this->logger->info("Successfully processed job.");
            } catch (Throwable $e) {
                $this->logger->error("Failed to process job.", ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                
                $payload['error'] = $e->getMessage();
                $payload['trace'] = $e->getTraceAsString();
                
                if ($payload['attempts'] < 3) {
                    $this->logger->info("Re-queueing job (Attempt {$payload['attempts']}/3)");
                    sleep(1); // Brief delay before requeue
                    $this->queue->push($queueName, $handlerClass, $payload);
                } else {
                    $payload['failed_at'] = date('c');
                    $this->queue->push('failed_jobs', $handlerClass, $payload);
                }
            } finally {
                if (function_exists('pcntl_alarm')) {
                    pcntl_alarm(0);
                }
                if ($this->container->has(\Magma\database\DatabaseConnectionManager::class)) {
                    $db = $this->container->get(\Magma\database\DatabaseConnectionManager::class);
                    if ($db instanceof \Magma\database\DatabaseConnectionManager) {
                        $db->disconnect();
                    }
                }
            }
        } else {
            $this->logger->warning("Invalid job payload or handler does not exist.");
        }
    }
}

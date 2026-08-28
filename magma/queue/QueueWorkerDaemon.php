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

    public function __construct(Container $container, QueueInterface $queue, \Magma\logging\LoggerInterface $logger)
    {
        $this->container = $container;
        $this->queue = $queue;
        $this->logger = $logger;
    }

    /**
     * Runs the worker daemon loop.
     *
     * Execution Flow:
     * 1. Registers PCNTL signal handlers to intercept kill signals for graceful shutdowns.
     * 2. Enters an infinite loop polling the specified queue.
     * 3. Flushes the Dependency Injection container state per iteration.
     * 4. Pops a job, processes it, and checks memory limits to prevent Out-Of-Memory (OOM) crashes.
     *
     * Logic behind the logic:
     * - The container flush is critical: it prevents singleton pollution (e.g., leftover Tenant contexts or DB connections) from bleeding into subsequent, unrelated background jobs.
     */
    public function run(string $queueName = 'emails'): void
    {
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            $signalHandler = function (int $signal) {
                $this->logger->info("Signal {$signal} received. Initiating graceful shutdown...");
                $this->running = false;
            };
            pcntl_signal(SIGTERM, $signalHandler);
            pcntl_signal(SIGINT, $signalHandler);
        }

        $this->logger->info("Worker started. Listening for jobs on '{$queueName}' queue...");

        while ($this->running) {
            // Flush DI container state to prevent tenant data leakage across jobs
            $this->container->flushInstances();

            try {
                $jobString = $this->queue->pop($queueName, 3);
            } catch (\Throwable $e) {
                $this->logger->critical("Queue pop failed (e.g. Redis offline). Sleeping for 5s.", ['exception' => $e->getMessage()]);
                sleep(5);
                continue;
            }

            if ($jobString) {
                $this->processJob($jobString);
            }

            // Prevent OOM in long-running processes (128MB threshold)
            if (memory_get_usage() > 134217728) {
                $this->logger->warning("Memory limit exceeded (128MB). Exiting daemon safely.");
                exit(0);
            }
        }
    }

    private function processJob(string $jobString): void
    {
        $job = json_decode($jobString, true);

        if (!is_array($job)) {
            $this->logger->warning("Invalid job payload format.");
            return;
        }

        $handlerClass = $job[\Magma\queue\JobInterface::HANDLER_KEY] ?? null;

        if (is_string($handlerClass) && class_exists($handlerClass)) {
            $this->logger->info("Received job: " . $handlerClass);
            try {
                set_time_limit(120);

                $handler = $this->container->get($handlerClass);
                
                if (!$handler instanceof \Magma\queue\JobInterface) {
                    throw new \RuntimeException("Job handler does not implement JobInterface");
                }

                $payload = $job[\Magma\queue\JobInterface::PAYLOAD_KEY] ?? [];
                if (!is_array($payload)) {
                    $payload = [];
                }

                $handler->handle($payload);
                $this->logger->info("Successfully processed job.");
            } catch (Throwable $e) {
                $this->logger->error("Failed to process job.", ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                $job['error'] = $e->getMessage();
                $job['failed_at'] = date('c');
                $this->queue->push('failed_jobs', $handlerClass, $job);
            } finally {
                if ($this->container->has(\Magma\database\DatabaseConnectionManager::class)) {
                    $db = $this->container->get(\Magma\database\DatabaseConnectionManager::class);
                    if ($db instanceof \Magma\database\DatabaseConnectionManager) {
                        $db->disconnect();
                    }
                }
                set_time_limit(0);
            }
        } else {
            $this->logger->warning("Invalid job payload or handler does not exist.");
        }
    }
}

<?php

declare(strict_types=1);

namespace Magma\queue;

use Magma\container\Container;
use Magma\queue\QueueInterface;
use Magma\queue\ReliableQueueInterface;
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
 * - Detects ReliableQueueInterface to provide two-phase at-least-once delivery (RPOPLPUSH/BLMOVE) and delayed retries with exponential backoff and jitter.
 *
 * Teaching notes:
 * - Daemons require careful memory management (e.g., checking memory usage and exiting if threshold is breached) because PHP is typically designed for short-lived request lifecycles.
 */
class QueueWorkerDaemon
{
    private Container $container;
    private QueueInterface $queue;
    private \Magma\logging\LoggerInterface $logger;
    private \Magma\contracts\ClockInterface $clock;

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
     * @param \Magma\contracts\ClockInterface $clock
     */
    public function __construct(
        Container $container, 
        QueueInterface $queue, 
        \Magma\logging\LoggerInterface $logger,
        \Magma\contracts\ClockInterface $clock
    ) {
        $this->container = $container;
        $this->queue = $queue;
        $this->logger = $logger;
        $this->clock = $clock;
    }

    /**
     * Executes the long-running worker polling loop.
     *
     * Execution Flow:
     * 1. Register asynchronous POSIX signal handlers (SIGTERM, SIGINT, SIGQUIT) if pcntl extension is present.
     * 2. If the queue implements ReliableQueueInterface, recover any orphaned in-flight jobs from prior crashes.
     * 3. Enter polling loop checking $this->running flag before every iteration.
     * 4. Call popReliable() or pop() with a 2-second timeout (blocking in Redis, 0% CPU spin).
     * 5. Process any received job payload synchronously.
     * 6. Check memory consumption against safety limit (128MB).
     * 7. Terminate gracefully when signaled or memory threshold breached.
     *
     * Logic behind the logic:
     * - Using a 2-second blocking pop prevents CPU busy-waiting (100% core spin) while ensuring
     *   POSIX termination signals are caught and handled within 2 seconds without killing jobs mid-flight.
     *
     * @param string $queueName
     */
    public function run(string $queueName = 'emails'): void
    {
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            $signalHandler = function (int $signal): void {
                $this->logger->info("Received termination signal ({$signal}). Stopping worker gracefully after current job...");
                $this->running = false;
            };
            pcntl_signal(SIGTERM, $signalHandler);
            pcntl_signal(SIGINT, $signalHandler);
            if (defined('SIGQUIT')) {
                pcntl_signal(SIGQUIT, $signalHandler);
            }
        }

        $this->logger->info("Worker started. Listening for jobs on '{$queueName}' queue...");

        if ($this->queue instanceof ReliableQueueInterface) {
            try {
                $recoveredCount = $this->queue->recoverOrphaned($queueName);
                if ($recoveredCount > 0) {
                    $this->logger->info("Recovered {$recoveredCount} orphaned in-flight jobs on queue '{$queueName}'.");
                }
            } catch (\Throwable $e) {
                $this->logger->warning("Failed to recover orphaned jobs on startup: " . $e->getMessage());
            }
        }

        while ($this->running) {
            try {
                // 2-second blocking timeout prevents CPU spinning while allowing responsive signal handling
                if ($this->queue instanceof ReliableQueueInterface) {
                    $jobString = $this->queue->popReliable($queueName, 2);
                } else {
                    $jobString = $this->queue->pop($queueName, 2);
                }
            } catch (\Throwable $e) {
                $this->logger->critical("Queue pop failed (e.g. Redis offline). Sleeping for 5s.", ['exception' => $e->getMessage()]);
                sleep(5);
                continue;
            }

            if ($jobString !== null && $jobString !== '') {
                $this->processJob($jobString, $queueName);
            }

            // Periodic garbage collection to maintain memory hygiene in long-running processes
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // Prevent OOM in long-running processes (128MB threshold)
            if (memory_get_usage() > 134217728) {
                $this->logger->warning("Memory limit exceeded (128MB). Exiting daemon safely.");
                $this->running = false;
                break;
            }
        }

        if ($this->container->has(\Magma\database\DatabaseConnectionManager::class)) {
            $db = $this->container->get(\Magma\database\DatabaseConnectionManager::class);
            if ($db instanceof \Magma\database\DatabaseConnectionManager) {
                $db->disconnect();
            }
        }

        $this->logger->info("Worker daemon terminated cleanly.");
    }

    /**
     * Programmatically requests the worker daemon to stop after its current iteration.
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Processes a single job popped from the queue.
     *
     * Execution Flow:
     * 1. Decodes the JSON payload to extract the job handler class and its parameters.
     * 2. Validates the handler class existence and ensures it implements JobInterface.
     * 3. Registers a PCNTL alarm to enforce a strict execution timeout (e.g., 120 seconds).
     * 4. Resolves the handler from the container and invokes its handle() method.
     * 5. Catches any exceptions, logs the error, and either re-queues the job (with jittered exponential backoff) or routes it to the DLQ.
     * 6. Cleans up by disabling the PCNTL alarm, disconnecting the database, flushing container, and acknowledging the job if reliable queue.
     *
     * Logic behind the logic:
     * - Disconnecting the database at the end of each job prevents "MySQL server has gone away" errors
     *   during idle periods when the daemon is waiting for new jobs.
     * - The PCNTL alarm prevents hanging jobs (e.g., infinite loops or stuck network calls) from permanently blocking the worker.
     * - Acknowledging in finally ensures that if the process terminates abruptly (SIGKILL/panic), the job remains in :processing for recovery.
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
                'failed_at' => $this->clock->now()->format('c')
            ]);
            if ($this->queue instanceof ReliableQueueInterface) {
                $this->queue->acknowledge($queueName, $jobString);
            }
            return;
        }

        $handlerClass = $job[\Magma\queue\JobInterface::HANDLER_KEY] ?? null;

        if (is_string($handlerClass) && class_exists($handlerClass)) {
            $this->logger->info("Received job: " . $handlerClass);
            
            $rawPayload = $job[\Magma\queue\JobInterface::PAYLOAD_KEY] ?? [];
            $payload = [];
            if (is_array($rawPayload)) {
                foreach ($rawPayload as $k => $v) {
                    if (is_string($k)) {
                        $payload[$k] = $v;
                    }
                }
            }
            
            $attemptsVal = $payload['attempts'] ?? 0;
            $attempts = is_numeric($attemptsVal) ? (int)$attemptsVal : 0;
            $payload['attempts'] = $attempts + 1;

            if ($this->container->has(\Magma\logging\CorrelationIdProvider::class)) {
                $correlationProvider = $this->container->get(\Magma\logging\CorrelationIdProvider::class);
                if ($correlationProvider instanceof \Magma\logging\CorrelationIdProvider) {
                    $cid = $payload['correlation_id'] ?? null;
                    $correlationProvider->reset(is_string($cid) ? $cid : null);
                }
            }

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

                $handler->handle($payload);
                $this->logger->info("Successfully processed job.");
            } catch (Throwable $e) {
                $this->logger->error("Failed to process job.", ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                
                $payload['error'] = $e->getMessage();
                $payload['trace'] = $e->getTraceAsString();
                
                if ($payload['attempts'] < 3) {
                    if ($this->queue instanceof ReliableQueueInterface) {
                        $delaySeconds = (int) min(300, (pow(2, $payload['attempts']) * 2) + random_int(1, 5));
                        $this->logger->info("Scheduling job retry (Attempt {$payload['attempts']}/3) with {$delaySeconds}s delay");
                        $this->queue->pushDelayed($queueName, $handlerClass, $payload, $delaySeconds);
                    } else {
                        $this->logger->info("Re-queueing job (Attempt {$payload['attempts']}/3)");
                        sleep(1); // Brief delay before requeue
                        $this->queue->push($queueName, $handlerClass, $payload);
                    }
                } else {
                    $payload['failed_at'] = $this->clock->now()->format('c');
                    $this->queue->push('failed_jobs', $handlerClass, $payload);
                }
            } finally {
                if ($this->queue instanceof ReliableQueueInterface) {
                    $this->queue->acknowledge($queueName, $jobString);
                }
                if (function_exists('pcntl_alarm')) {
                    pcntl_alarm(0);
                }
                if ($this->container->has(\Magma\database\DatabaseConnectionManager::class)) {
                    $db = $this->container->get(\Magma\database\DatabaseConnectionManager::class);
                    if ($db instanceof \Magma\database\DatabaseConnectionManager) {
                        $db->disconnect();
                    }
                }
                if ($this->container->has(\Magma\logging\CorrelationIdProvider::class)) {
                    $correlationProvider = $this->container->get(\Magma\logging\CorrelationIdProvider::class);
                    if ($correlationProvider instanceof \Magma\logging\CorrelationIdProvider) {
                        $correlationProvider->reset();
                    }
                }
                if ($this->container->has(\Magma\security\TenantContext::class)) {
                    $tenantContext = $this->container->get(\Magma\security\TenantContext::class);
                    if ($tenantContext instanceof \Magma\security\TenantContext) {
                        $tenantContext->clear();
                    }
                }
                // Flush DI container instances after disconnecting and clearing job state
                $this->container->flushInstances();
            }
        } else {
            $handlerName = is_string($handlerClass) ? $handlerClass : 'UnknownHandler';
            $this->logger->warning("Invalid job payload or handler does not exist. Pushing to DLQ.", [
                'handler' => $handlerName,
            ]);
            $this->queue->push('failed_jobs', $handlerName, [
                'raw_payload' => $job,
                'error' => is_string($handlerClass) ? "Handler class '{$handlerClass}' does not exist" : "Job missing handler key",
                'failed_at' => $this->clock->now()->format('c'),
            ]);
            if ($this->queue instanceof ReliableQueueInterface) {
                $this->queue->acknowledge($queueName, $jobString);
            }
        }
    }
}

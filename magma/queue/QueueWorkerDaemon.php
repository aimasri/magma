<?php

declare(strict_types=1);

namespace Magma\queue;

use Magma\container\Container;
use Magma\queue\QueueInterface;
use Throwable;

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

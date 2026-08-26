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

    private bool $running = true;

    public function __construct(Container $container, QueueInterface $queue)
    {
        $this->container = $container;
        $this->queue = $queue;
    }

    public function run(string $queueName = 'emails'): void
    {
        echo "Worker started. Listening for jobs on '{$queueName}' queue...\n";

        while ($this->running) {
            $jobString = $this->queue->pop($queueName, 0);

            if ($jobString) {
                $this->processJob($jobString);
            }
        }
    }

    private function processJob(string $jobString): void
    {
        $job = json_decode($jobString, true);

        if (!is_array($job)) {
            echo "Invalid job payload format.\n";
            return;
        }

        $handlerClass = $job[\Magma\queue\JobInterface::HANDLER_KEY] ?? null;

        if (is_string($handlerClass) && class_exists($handlerClass)) {
            echo "Received job: " . $handlerClass . "\n";
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
                echo "Successfully processed job.\n";
            } catch (Throwable $e) {
                echo "Failed to process job: " . $e->getMessage() . "\n";
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
            echo "Invalid job payload or handler does not exist.\n";
        }
    }
}

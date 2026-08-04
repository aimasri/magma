#!/usr/bin/env php
<?php

/**
 * Background Worker Daemon
 *
 * Purpose:
 * - Bootstraps the application container in CLI mode.
 * - Enters an infinite loop to pull jobs from the queue via `BLPOP`.
 * - Executes heavy operations synchronously in the background.
 *
 * Why / Why this design:
 * - Operating as a separate, long-running process allows the web application to 
 *   respond instantly by pushing jobs to Redis, while this script takes the CPU time 
 *   to send emails or process images without holding up the user's browser.
 *
 * Teaching notes:
 * - Notice that this script boots the application container. This gives the CLI worker 
 *   access to the exact same Database connections, Configuration, and Dependency 
 *   Injection logic as the web server, without duplicating code.
 *
 * Usage:
 * - Run via supervisor: `php bin/worker.php`
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

require __DIR__ . '/../magma/core/config/bootstrap.php';

use core\queue\QueueInterface;

$queue = $container->get(QueueInterface::class);

echo "Worker started. Listening for jobs on 'emails' queue...\n";

while (true) {
    // 0 means block indefinitely until a job arrives
    $jobString = $queue->pop('emails', 0);

    if ($jobString) {
        $job = json_decode($jobString, true);

        if (isset($job[\core\queue\JobInterface::HANDLER_KEY]) && class_exists($job[\core\queue\JobInterface::HANDLER_KEY])) {
            echo "Received job: " . $job[\core\queue\JobInterface::HANDLER_KEY] . "\n";
            try {
                // Set max execution time per job to prevent hanging workers
                set_time_limit(120);

                $handlerClass = $job[\core\queue\JobInterface::HANDLER_KEY];
                // Resolve the specific job class out of the DI container
                $handler = $container->get($handlerClass);
                
                // Execute the job logic
                $handler->handle($job[\core\queue\JobInterface::PAYLOAD_KEY] ?? []);
                echo "Successfully processed job.\n";
            } catch (\Throwable $e) {
                echo "Failed to process job: " . $e->getMessage() . "\n";
                $job['error'] = $e->getMessage();
                $job['failed_at'] = date('c');
                $queue->push('failed_jobs', json_encode($job));
            } finally {
                // Always disconnect to prevent connection exhaustion in daemon mode
                $container->get(\Magma\database\DatabaseConnectionManager::class)->disconnect();
                // Reset time limit for the polling loop
                set_time_limit(0);
            }
        } else {
            echo "Invalid job payload or handler does not exist.\n";
        }
    }
}

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
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../magma/config/bootstrap.php';

use Magma\queue\QueueInterface;
use Magma\queue\QueueWorkerDaemon;

$queue = $container->get(QueueInterface::class);
$daemon = new QueueWorkerDaemon($container, $queue);
$daemon->run();

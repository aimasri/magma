#!/usr/bin/env php
<?php

/**
 * Title: Scheduled Token Cleanup Job
 *
 * Purpose:
 * - A CLI worker script to delete expired tokens from the database.
 *
 * Why / Why this design:
 * - Running a heavy database cleanup synchronously during an HTTP request would 
 *   negatively impact user latency. Offloading this to a background CRON job 
 *   ensures high performance for users while maintaining database hygiene.
 *
 * Teaching notes:
 * - This file should be scheduled in the server's crontab to run daily during 
 *   off-peak hours. Example: `0 3 * * * /usr/bin/php /path/to/bin/cleanup_tokens.php`
 */

require_once dirname(__DIR__, 2) . '/magma/config/bootstrap.php';

use Magma\interfaces\repositories\RememberTokenRepositoryInterface;
use Magma\interfaces\repositories\PasswordResetTokenRepositoryInterface;

// Bootstrapping initialized the container as $container
/** @var \Magma\container\Container $container */
$rememberRepo = $container->get(RememberTokenRepositoryInterface::class);
/** @var RememberTokenRepositoryInterface $rememberRepo */
$passwordRepo = $container->get(PasswordResetTokenRepositoryInterface::class);
/** @var PasswordResetTokenRepositoryInterface $passwordRepo */

$deletedCount = $rememberRepo->deleteExpiredTokens();
$deletedCount += $passwordRepo->deleteExpiredTokens();

echo "Token cleanup completed successfully. " . $deletedCount . " expired tokens purged.\n";

<?php

/**
 * The Front Controller.
 * 
 * This is the only file in the entire application that should be publicly 
 * accessible to the web. It serves as the gateway that initiates the 
 * bootstrapping process and hands control over to the Application kernel.
 */

/**
 * Load the application bootstrap.
 * This moves the execution from the public 'www' directory into 
 * the private 'fussy_app' container.
 */
require_once __DIR__ . '/../magma/config/bootstrap.php';

/**
 * Define the execution environment dynamically from the .env configuration.
 * Falls back to 'production' for security if not specified.
 */
define('ENVIRONMENT', \Magma\config\Config::get('APP_ENV', 'production'));

use Magma\Application;
use Magma\middleware\CsrfMiddleware;
use Magma\middleware\UTMTrackerMiddleware;
use Magma\middleware\ViewShareMiddleware;
use Magma\middleware\SessionTimeoutMiddleware;

/**
 * Resolve the Application instance and register global middleware.
 * The order of middleware matters: first in, outermost layer.
 */
$app = $container->get(Application::class);
$app->addMiddleware(UTMTrackerMiddleware::class);
$app->addMiddleware(CsrfMiddleware::class);
$app->addMiddleware(SessionTimeoutMiddleware::class);
$app->addMiddleware(ViewShareMiddleware::class);

// Execute the request.
$app->run();

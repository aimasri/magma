<?php

/**
 * Title: Application Front Controller
 *
 * Purpose:
 * - Serves as the single entry point for all HTTP requests to the application.
 * - Bootstraps the application configuration, DI container, and middleware pipeline.
 * - Dispatches the incoming request through the initialized application kernel.
 *
 * Why / Why this design:
 * - Implementing a Front Controller pattern provides a centralized location for security,
 *   routing, and global middleware initialization.
 * - Hides the internal file structure (`magma` directory) by only exposing the `www` folder to the web server.
 *
 * Teaching notes:
 * - This pattern prevents bypass attacks since all requests must flow through this controlled gateway.
 * - Similar to `public/index.php` in Laravel or Symfony. Keep this file extremely thin.
 */

/**
 * Load the application bootstrap.
 * This moves the execution from the public 'www' directory into 
 * the private 'magma' container.
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
use Magma\middleware\SecurityHeadersMiddleware;

/**
 * Resolve the Application instance and register global middleware.
 * The order of middleware matters: first in, outermost layer.
 */
$app = $container->get(Application::class);
$app->addMiddleware(UTMTrackerMiddleware::class);
$app->addMiddleware(SecurityHeadersMiddleware::class);
$app->addMiddleware(CsrfMiddleware::class);
$app->addMiddleware(SessionTimeoutMiddleware::class);
$app->addMiddleware(ViewShareMiddleware::class);

// Execute the request.
$app->run();

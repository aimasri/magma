<?php

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\config\Config;
use Magma\http\Request;
use Magma\http\RequestInterface;
use Magma\http\Session;
use Magma\error\ErrorHandler;
use Magma\error\ErrorHandlerInterface;
use Magma\routing\Router;
use Magma\routing\RouterInterface;
use Magma\middleware\MiddlewareResolver;
use Magma\routing\UrlGenerator;
use Magma\database\DatabaseConnectionManager;
use Magma\view\TemplateEngine;
use Magma\view\ViewLoaderInterface;
use Magma\view\FileViewLoader;
use Magma\validation\Validator;
use Magma\security\RateLimiterInterface;
use Magma\security\RedisRateLimiter;
use Magma\queue\QueueInterface;
use Magma\queue\RedisQueue;
use PDO;

/**
 * CoreServiceProvider — registers foundational framework services.
 *
 * Purpose:
 * - Bootstraps the application's core architecture including the database,
 *   HTTP layer (Request/Session), routing, templating, and error handling.
 *
 * Why / Why this design:
 * - Utilizes the Service Locator / Registry pattern to decouple the instantiation 
 *   of core components from the classes that depend on them.
 *
 * Teaching notes:
 * - Grouping these foundational classes into a single provider isolates 
 *   framework-level logic from domain-specific business logic.
 */
class CoreServiceProvider implements ServiceProviderInterface
{
    /**
     * Register Core Framework Bindings
     *
     * Execution Flow:
     * 1. Bind structural components (Session, Request, Routing, Templates).
     * 2. Bind the DatabaseConnectionManager and provide Read and Write PDO connections.
     * 3. Bind infrastructure services (Redis, RateLimiter, Queue).
     *
     * Logic behind the logic:
     * - This file explicitly isolates the framework logic. The application logic 
     *   (like Repositories and Controllers) uses these underlying structural bindings.
     *
     * @param Container $container The global dependency injection container.
     * @return void
     */
    public function register(Container $container): void
    {
        $container->set(\Magma\config\ConfigInterface::class, function ($c) {
            return new \Magma\config\ConfigWrapper();
        });

        $container->set(QueueInterface::class, function ($c) {
            return new RedisQueue($c->get(\Redis::class));
        });

        $container->set(Session::class, function ($c) {
            $handler = null;
            if (Config::get('SESSION_DRIVER') === 'redis') {
                $handler = new \Magma\http\RedisSessionHandler(
                    $c->get(\Redis::class),
                    (int)Config::get('SESSION_LIFETIME_ADMIN', 7200)
                );
            }
            return new Session($handler);
        });

        $container->set(RequestInterface::class, function ($c) {
            return new Request(
                $_GET,
                $_POST,
                $_SERVER,
                $_FILES,
                $_COOKIE,
                $c->get(Session::class)
            );
        });

        // Map the concrete Request class to the interface resolution for backwards compatibility
        $container->set(Request::class, function ($c) {
            return $c->get(RequestInterface::class);
        });

        $container->set(DatabaseConnectionManager::class, function ($c) {
            return new DatabaseConnectionManager(
                Config::getDatabaseSettings(),
                Config::getReplicaDatabaseSettings(),
                Config::get('DB_EMULATE_PREPARES', 'false') === 'true'
            );
        });

        $container->set('db.write', function ($c) {
            return $c->get(DatabaseConnectionManager::class)->getWriteConnection();
        });

        $container->set('db.read', function ($c) {
            return $c->get(DatabaseConnectionManager::class)->getReadConnection();
        });

        $container->set(\Redis::class, function () {
            $redis = new \Redis();
            
            try {
                $timeout = (float)Config::get('REDIS_TIMEOUT', 2.0);
                $connected = $redis->connect(
                    Config::get('REDIS_HOST', '127.0.0.1'), 
                    (int)Config::get('REDIS_PORT', 6379),
                    $timeout
                );

                if (!$connected) {
                    throw new \RuntimeException('Redis connection failed.');
                }

                $password = Config::get('REDIS_PASSWORD');
                if ($password !== null) {
                    $redis->auth($password);
                }

                $db = Config::get('REDIS_DB');
                if ($db !== null) {
                    $redis->select((int)$db);
                }
            } catch (\RedisException $e) {
                throw new \RuntimeException('Redis configuration or connection error: ' . $e->getMessage(), 0, $e);
            }

            return $redis;
        });

        $container->set(RateLimiterInterface::class, function ($c) {
            return new RedisRateLimiter($c->get(\Redis::class));
        });

        $container->set(\Magma\security\CsrfManager::class, function ($c) {
            return new \Magma\security\CsrfManager($c->get(Session::class));
        });

        $container->set(ViewLoaderInterface::class, function ($c) {
            return new FileViewLoader();
        });

        $container->set(TemplateEngine::class, function ($c) {
            return new TemplateEngine(
                $c->get(ViewLoaderInterface::class),
                ROOT_DIR . '/app/views', 
                ROOT_DIR . '/app/views/partials'
            );
        });

        $container->set(Validator::class, function ($c) {
            return new Validator();
        });

        $container->set(ErrorHandlerInterface::class, function ($c) {
            return new ErrorHandler(
                $c->get(TemplateEngine::class),
                defined('ENVIRONMENT') && ENVIRONMENT === 'development'
            );
        });

        $container->set(ErrorHandler::class, function ($c) {
            return $c->get(ErrorHandlerInterface::class);
        });

        $container->set(MiddlewareResolver::class, function ($c) {
            return new MiddlewareResolver($c);
        });

        $container->set(RouterInterface::class, function ($c) {
            $cacheFile = ROOT_DIR . '/magma/config/routes.cache.php';
            $routesFile = ROOT_DIR . '/magma/config/routes.php';
            
            $routes = file_exists($cacheFile) ? require $cacheFile : require $routesFile;
            
            return new Router($c, $c->get(MiddlewareResolver::class), $routes);
        });

        $container->set(Router::class, function ($c) {
            return $c->get(RouterInterface::class);
        });

        $container->set(\Magma\interfaces\EventDispatcherInterface::class, function ($c) {
            return new \Magma\events\EventDispatcher($c);
        });

        $container->set(\Magma\events\EventDispatcher::class, function ($c) {
            return $c->get(\Magma\interfaces\EventDispatcherInterface::class);
        });

        $container->set(UrlGenerator::class, function ($c) {
            return new UrlGenerator(
                $c->get(RequestInterface::class),
                Config::getRequired('APP_URL')
            );
        });
    }
}

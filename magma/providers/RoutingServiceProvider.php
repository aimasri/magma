<?php

declare(strict_types=1);

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\routing\Router;
use Magma\routing\RouterInterface;
use Magma\routing\RouteCollection;
use Magma\routing\RouteDispatcher;
use Magma\routing\RouteCacheInterface;
use Magma\routing\ArrayRouteCache;
use Magma\middleware\MiddlewareResolver;
use Magma\routing\UrlGenerator;
use Magma\config\Config;
use Magma\http\RequestInterface;

/**
 * Title: Routing Service Provider
 *
 * Purpose:
 * - Bootstraps the Router, RouteCollection, MiddlewareResolver, and UrlGenerator.
 * - Manages lifecycle bindings for compiled route caching and reverse URL generation.
 *
 * Why / Why this design:
 * - Centralizes routing dependencies in a modular Service Provider, keeping Front Controllers clean.
 * - Injects shared `RouteCollection` instances across `Router` and `UrlGenerator` to enable bidirectional named routing.
 *
 * Teaching notes:
 * - Notice how routes are dynamically loaded from `routes.cache.php` in production or compiled from `routes.php` in development.
 */
class RoutingServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(MiddlewareResolver::class, function (Container $c) {
            return new MiddlewareResolver($c);
        });

        $container->set(RouteCacheInterface::class, function () {
            static $cache = null;
            if ($cache === null) {
                $cache = new ArrayRouteCache();
            }
            return $cache;
        });

        $container->set(RouteCollection::class, function () {
            $cacheFile = ROOT_DIR . '/magma/config/routes.cache.php';
            $routesFile = ROOT_DIR . '/magma/config/routes.php';

            $routes = file_exists($cacheFile) ? require $cacheFile : require $routesFile;

            return new RouteCollection(is_array($routes) ? $routes : []);
        });

        $container->set(RouterInterface::class, function (Container $c) {
            $collection = $c->get(RouteCollection::class);
            assert($collection instanceof RouteCollection);
            $middlewareResolver = $c->get(MiddlewareResolver::class);
            assert($middlewareResolver instanceof MiddlewareResolver);
            $dispatcher = new RouteDispatcher($c, $middlewareResolver);
            $routeCache = $c->get(RouteCacheInterface::class);
            assert($routeCache instanceof RouteCacheInterface);

            return new Router($collection, $dispatcher, $routeCache);
        });

        $container->set(Router::class, function (Container $c) {
            $router = $c->get(RouterInterface::class);
            assert($router instanceof Router);
            return $router;
        });

        $container->set(UrlGenerator::class, function (Container $c) {
            $request = $c->get(RequestInterface::class);
            assert($request instanceof RequestInterface);
            $appUrl = Config::get('APP_URL', 'http://localhost');
            $collection = $c->get(RouteCollection::class);
            assert($collection instanceof RouteCollection);
            return new UrlGenerator(
                $request,
                is_scalar($appUrl) ? (string)$appUrl : 'http://localhost',
                $collection
            );
        });
    }
}

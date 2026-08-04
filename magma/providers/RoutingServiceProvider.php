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
 * Purpose: Bootstraps the Router, MiddlewareResolver, and UrlGenerator.
 */
class RoutingServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(MiddlewareResolver::class, function ($c) {
            return new MiddlewareResolver($c);
        });

        $container->set(RouteCacheInterface::class, function ($c) {
            static $cache = null;
            if ($cache === null) {
                $cache = new ArrayRouteCache();
            }
            return $cache;
        });

        $container->set(RouterInterface::class, function ($c) {
            $cacheFile = ROOT_DIR . '/magma/config/routes.cache.php';
            $routesFile = ROOT_DIR . '/magma/config/routes.php';
            
            $routes = file_exists($cacheFile) ? require $cacheFile : require $routesFile;
            
            $collection = new RouteCollection($routes);
            $dispatcher = new RouteDispatcher($c, $c->get(MiddlewareResolver::class));
            $routeCache = $c->get(RouteCacheInterface::class);
            
            return new Router($collection, $dispatcher, $routeCache);
        });

        $container->set(Router::class, function ($c) {
            return $c->get(RouterInterface::class);
        });
        
        $container->set(UrlGenerator::class, function ($c) {
            return new UrlGenerator(
                $c->get(RequestInterface::class),
                Config::getRequired('APP_URL')
            );
        });
    }
}

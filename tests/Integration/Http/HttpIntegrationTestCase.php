<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use Tests\Integration\Persistence\DatabaseIntegrationTestCase;
use Magma\Application;
use Magma\http\Request;
use Magma\http\Response;
use Magma\error\ErrorHandlerInterface;
use Magma\routing\RouterInterface;
use Magma\routing\RouteCollection;
use Magma\routing\Route;
use Magma\routing\RouteDefinition;

abstract class HttpIntegrationTestCase extends DatabaseIntegrationTestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->container->set(ErrorHandlerInterface::class, function () {
            return new class implements ErrorHandlerInterface {
                public function handleException(\Throwable $e, ?\Magma\http\RequestInterface $request = null): Response {
                    return new Response($e->getMessage(), 500);
                }
                public function renderError(int $code, string $message, ?string $trace = null, ?\Magma\http\RequestInterface $request = null): Response {
                    return new Response($message, $code);
                }
                public function renderNotFound(?\Magma\http\RequestInterface $request = null, ?\Throwable $e = null): Response {
                    return new Response('Not Found', 404);
                }
            };
        });
        
        $this->container->set(\Magma\routing\RouteCollection::class, function () {
            return new \Magma\routing\RouteCollection();
        });
        
        $this->container->set(\Magma\routing\RouteCacheInterface::class, function () {
            return new class implements \Magma\routing\RouteCacheInterface {
                private ?array $cache = null;
                public function get(): ?array { return $this->cache; }
                public function set(array $data): void { $this->cache = $data; }
                public function clear(): void { $this->cache = null; }
            };
        });
        
        $this->container->set(\Magma\middleware\MiddlewareResolver::class, function ($c) {
            return new \Magma\middleware\MiddlewareResolver($c);
        });

        $this->container->set(\Magma\routing\RouteDispatcher::class, function ($c) {
            return new \Magma\routing\RouteDispatcher($c, $c->get(\Magma\middleware\MiddlewareResolver::class));
        });
        
        $this->container->set(\Magma\container\Container::class, function () {
            return $this->container;
        });

        $this->container->set(RouterInterface::class, function ($c) {
            return new \Magma\routing\Router(
                $c->get(\Magma\routing\RouteCollection::class),
                $c->get(\Magma\routing\RouteDispatcher::class),
                $c->get(\Magma\routing\RouteCacheInterface::class)
            );
        });

        $this->app = $this->container->get(Application::class);
    }

    protected function get(string $uri, array $headers = []): Response
    {
        return $this->sendRequest('GET', $uri, [], $headers);
    }

    protected function post(string $uri, array $data = [], array $headers = []): Response
    {
        return $this->sendRequest('POST', $uri, $data, $headers);
    }
    
    protected function put(string $uri, array $data = [], array $headers = []): Response
    {
        return $this->sendRequest('PUT', $uri, $data, $headers);
    }
    
    protected function delete(string $uri, array $data = [], array $headers = []): Response
    {
        return $this->sendRequest('DELETE', $uri, $data, $headers);
    }

    protected function sendRequest(string $method, string $uri, array $data = [], array $headers = []): Response
    {
        $server = [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI'    => $uri,
        ];
        
        foreach ($headers as $key => $value) {
            $serverKey = 'HTTP_' . str_replace('-', '_', strtoupper($key));
            $server[$serverKey] = $value;
            if (strtolower($key) === 'content-type') {
                $server['CONTENT_TYPE'] = $value;
            }
        }
        
        $post = strtoupper($method) === 'POST' ? $data : [];
        $get = strtoupper($method) === 'GET' ? $data : [];
        
        $request = Request::build($get, $post, [], [], $server);
        
        return $this->app->handle($request);
    }
    
    protected function addRoute(string $method, string $path, callable|string|array $handler, array $middleware = []): void
    {
        $collection = $this->container->get(RouteCollection::class);
        $route = new Route($method, $path, $handler, null, $middleware);
        $collection->add($route);
        // Clear route cache if it was already resolved
        $this->container->get(\Magma\routing\RouteCacheInterface::class)->clear();
    }
}

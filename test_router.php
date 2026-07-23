<?php

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/' . str_replace('\\', '/', lcfirst($class)) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use Magma\container\Container;
use Magma\routing\Router;
use Magma\http\Request;
use Magma\middleware\MiddlewareInterface;
use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\middleware\MiddlewareResolver;

class DummyController {
    public function show($id) { return new Response("Show ID: " . $id); }
    public function profile($username) { return new Response("Profile: " . $username); }
}

$routes = [
    ['GET', '/user/{id}', [DummyController::class, 'show'], ['id' => '[0-9]+']],
    ['GET', '/user/{username}/profile', [DummyController::class, 'profile'], ['username' => '[a-z]+']],
    ['GET', '/static', function() { return new Response("Static"); }]
];

$container = new Container();
$container->set(DummyController::class, function() { return new DummyController(); });
$resolver = new MiddlewareResolver($container);

$router = new Router($container, $resolver, $routes);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/user/42';
$req1 = Request::createFromGlobals();
$res1 = $router->dispatch($req1);
echo "Req1: " . $res1->getContent() . "\n";

$_SERVER['REQUEST_URI'] = '/user/alice/profile';
$req2 = Request::createFromGlobals();
$res2 = $router->dispatch($req2);
echo "Req2: " . $res2->getContent() . "\n";

$_SERVER['REQUEST_URI'] = '/static';
$req3 = Request::createFromGlobals();
$res3 = $router->dispatch($req3);
echo "Req3: " . $res3->getContent() . "\n";


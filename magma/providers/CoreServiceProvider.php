<?php

declare(strict_types=1);

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\config\Config;
use Magma\http\Request;
use Magma\http\RequestInterface;
use Magma\http\Session;
use Magma\http\SessionInterface;
use Magma\error\ErrorHandler;
use Magma\error\ErrorHandlerInterface;
use Magma\view\TemplateEngine;
use Magma\validation\Validator;
use Magma\security\CsrfManager;
use Magma\security\TenantContext;

/**
 * Title: Core Service Provider
 * Purpose: Bootstraps the application's foundational components (Request, Session, Config).
 */
class CoreServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(\Magma\config\ConfigInterface::class, function ($c) {
            return new \Magma\config\ConfigWrapper();
        });

        $container->set(TenantContext::class, function ($c) {
            return new TenantContext();
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

        $container->set(SessionInterface::class, function ($c) {
            return $c->get(Session::class);
        });

        $container->set(RequestInterface::class, function ($c) {
            return Request::createFromGlobals();
        });

        $container->set(Request::class, function ($c) {
            return $c->get(RequestInterface::class);
        });

        $container->set(CsrfManager::class, function ($c) {
            return new CsrfManager($c->get(SessionInterface::class));
        });

        $container->set(\Magma\view\ViewLoaderInterface::class, function ($c) {
            $loader = new \Magma\view\LocalFileViewLoader(ROOT_DIR . '/app/views');
            $loader->addNamespace('App', ROOT_DIR . '/app/views');
            if (is_dir(ROOT_DIR . '/modules')) {
                $modules = glob(ROOT_DIR . '/modules/*', GLOB_ONLYDIR);
                if ($modules) {
                    foreach ($modules as $moduleDir) {
                        $moduleName = basename($moduleDir);
                        $moduleViews = $moduleDir . '/views';
                        if (is_dir($moduleViews)) {
                            $loader->addNamespace($moduleName, $moduleViews);
                        }
                    }
                }
            }
            return $loader;
        });

        $container->set(\Magma\view\LocalFileViewLoader::class, function ($c) {
            return $c->get(\Magma\view\ViewLoaderInterface::class);
        });

        $container->set(TemplateEngine::class, function ($c) {
            $loader = $c->has(\Magma\view\ViewLoaderInterface::class) 
                ? $c->get(\Magma\view\ViewLoaderInterface::class) 
                : null;

            return new TemplateEngine(
                ROOT_DIR . '/app/views', 
                ROOT_DIR . '/app/views', 
                ROOT_DIR . '/app/views/partials',
                $loader
            );
        });

        $container->set(Validator::class, function ($c) {
            return new Validator();
        });

        $container->set(ErrorHandlerInterface::class, function ($c) {
            return new ErrorHandler(
                $c->get(TemplateEngine::class),
                $c->get(\Magma\config\ConfigInterface::class)
            );
        });

        $container->set(ErrorHandler::class, function ($c) {
            return $c->get(ErrorHandlerInterface::class);
        });

        $container->set(\Magma\interfaces\EventDispatcherInterface::class, function ($c) {
            return new \Magma\events\EventDispatcher($c);
        });

        $container->set(\Magma\events\EventDispatcher::class, function ($c) {
            return $c->get(\Magma\interfaces\EventDispatcherInterface::class);
        });
    }
}

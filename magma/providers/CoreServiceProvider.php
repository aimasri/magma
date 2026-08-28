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
 * Title: CoreServiceProvider
 *
 * Purpose:
 * - Bootstraps the application's foundational components including HTTP Request, Session, and Config
 * - Registers core security and validation mechanisms like CsrfManager and Validator
 * - Configures error handling and view layer dependencies (TemplateEngine, ViewLoader)
 *
 * Why / Why this design:
 * - Service Provider Pattern: Centralizes the instantiation logic of core application dependencies
 * - High Cohesion: Groups related foundational services to ensure they are available before the application kernel runs
 *
 * Teaching notes:
 * - This provider is crucial for bootstrapping the core HTTP lifecycle dependencies (e.g., Request and Session) before routing occurs.
 */
class CoreServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers core framework components within the DI container.
     *
     * 1. Binds Config wrapper, TenantContext, and Session management.
     * 2. Bootstraps the global Request object and CSRF protection.
     * 3. Configures view loaders, template engines, and error handlers (JSON and Debug).
     * 4. Registers the HTML response builder and event dispatcher.
     *
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $container->set(\Magma\config\ConfigInterface::class, function ($c) {
            return new \Magma\config\ConfigWrapper();
        });

        $container->set(\Magma\security\TenantContextProviderInterface::class, function ($c) {
            return new \Magma\security\DomainTenantContextProvider(
                $c->get(\Magma\database\DatabaseConnectionManager::class)
            );
        });

        $container->set(TenantContext::class, function ($c) {
            return new TenantContext($c->get(\Magma\security\TenantContextProviderInterface::class));
        });

        $container->set(Session::class, function ($c) {
            $handler = null;
            if (Config::get('SESSION_DRIVER') === 'redis') {
                $lifetime = Config::get('SESSION_LIFETIME_ADMIN', 7200);
                $handler = new \Magma\http\RedisSessionHandler(
                    $c->get(\Redis::class),
                    is_scalar($lifetime) ? (int)$lifetime : 7200
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

        $container->set(\Magma\interfaces\JsonErrorPresenterInterface::class, function ($c) {
            return new \Magma\error\JsonErrorPresenter();
        });

        $container->set(\Magma\interfaces\DebugErrorPresenterInterface::class, function ($c) {
            return new \Magma\error\DebugErrorPresenter();
        });

        $container->set(ErrorHandlerInterface::class, function ($c) {
            return new ErrorHandler(
                $c->get(TemplateEngine::class),
                $c->get(\Magma\config\ConfigInterface::class),
                $c->get(\Magma\interfaces\JsonErrorPresenterInterface::class),
                $c->get(\Magma\interfaces\DebugErrorPresenterInterface::class),
                $c
            );
        });

        $container->set(ErrorHandler::class, function ($c) {
            return $c->get(ErrorHandlerInterface::class);
        });

        $container->set(\Magma\view\HtmlResponseBuilderInterface::class, function ($c) {
            return new \Magma\view\HtmlResponseBuilder(
                $c->get(\Magma\view\TemplateEngine::class),
                $c->get(\Magma\security\CsrfManager::class),
                $c->get(\Magma\interfaces\ResponseFactoryInterface::class)
            );
        });

        $container->set(\Magma\interfaces\ResponseFactoryInterface::class, function ($c) {
            return new \Magma\http\ResponseFactory();
        });

        $container->set(\Magma\interfaces\EventDispatcherInterface::class, function ($c) {
            return new \Magma\events\EventDispatcher($c);
        });

        $container->set(\Magma\events\EventDispatcher::class, function ($c) {
            return $c->get(\Magma\interfaces\EventDispatcherInterface::class);
        });
    }
}

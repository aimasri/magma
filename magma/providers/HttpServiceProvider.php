<?php

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\view\TemplateEngine;
use Magma\http\Request;
use Magma\validation\Validator;

use Magma\repositories\VendorRepositoryInterface;
use Magma\repositories\UserRepositoryInterface;

use Modules\Reviews\interfaces\cqrs\SiteReviewQueryInterface;
use Modules\Reviews\services\ReviewSubmissionService;
use Magma\services\PaginationService;
use Magma\services\RegistrationService;
use Magma\services\AuthenticationService;
use Magma\services\RememberMeService;
use Magma\services\PasswordResetService;

use Magma\middleware\ViewShareMiddleware;

use Magma\controllers\HomeController;
use Magma\controllers\LoginController;
use Magma\controllers\RegisterController;
use Magma\controllers\LogoutController;
use Magma\controllers\PasswordResetController;
use Magma\controllers\PolicyController;
use admin\controllers\VendorAdminController;
use user\controllers\UserDashboardController;

/**
 * Title: HttpServiceProvider — registers HTTP controllers and middleware.
 *
 * Purpose:
 * - Bootstraps the application's transport layer dependencies.
 * - Injects Template Engines, Requests, and Domain Services into Controllers.
 *
 * Why / Why this design:
 * - Keeps HTTP-specific bindings separate from core domain logic and data access,
 *   making it easier to manage endpoints and transport mechanics independently. 
 *   If we ever add a CLI or API transport layer, it would get its own Provider.
 *
 * Teaching notes:
 * - Controllers are instantiated here with their full dependency graph. This prevents 
 *   Controllers from needing to know about the Container, keeping them decoupled from the framework.
 */
class HttpServiceProvider implements ServiceProviderInterface
{
    /**
     * Register Transport Layer Bindings
     *
     * Execution Flow:
     * 1. Bind global Middleware classes.
     * 2. Bind HTTP Controllers, injecting services, validators, and the Request object.
     *
     * Logic behind the logic:
     * - Controllers are the top of the dependency chain. By the time a Controller is built, 
     *   every Service and Repository below it has already been recursively resolved by the Container.
     *
     * @param Container $container The global dependency injection container.
     * @return void
     */
    public function register(Container $container): void
    {
        // ------------------------------------------------------------------
        // Middlewares
        // ------------------------------------------------------------------
        $container->set(ViewShareMiddleware::class, function ($c) {
            return new ViewShareMiddleware(
                $c->get(TemplateEngine::class),
                $c->get(\Magma\interfaces\cqrs\VendorQueryInterface::class),
                $c->get(\Magma\http\Session::class)
            );
        });

        $container->set(\Magma\middleware\CsrfMiddleware::class, function ($c) {
            return new \Magma\middleware\CsrfMiddleware(
                $c->get(\Magma\security\CsrfManager::class)
            );
        });

        // ------------------------------------------------------------------
        // Controllers
        // ------------------------------------------------------------------
        $container->set(HomeController::class, function ($c) {
            return new HomeController(
                $c->get(TemplateEngine::class),
                $c->get(\Magma\security\CsrfManager::class),
                $c->get(\Magma\http\Session::class),
                $c->get(\Modules\Reviews\interfaces\cqrs\SiteReviewQueryInterface::class),
                $c->get(Request::class),
                $c->get(PaginationService::class)
            );
        });

        $container->set(\Modules\Reviews\controllers\ReviewController::class, function ($c) {
            return new \Modules\Reviews\controllers\ReviewController(
                $c->get(\Magma\view\TemplateEngine::class),
                $c->get(\Magma\security\CsrfManager::class),
                $c->get(\Magma\http\Session::class),
                $c->get(ReviewSubmissionService::class),
                $c->get(Request::class),
                $c->get(Validator::class)
            );
        });

        $container->set(LoginController::class, function ($c) {
            return new LoginController(
                $c->get(AuthenticationService::class),
                $c->get(\Magma\view\HtmlResponseBuilderInterface::class),
                $c->get(\Magma\http\SessionInterface::class)
            );
        });

        $container->set(RegisterController::class, function ($c) {
            return new RegisterController(
                $c->get(\Magma\view\HtmlResponseBuilderInterface::class),
                $c->get(RegistrationService::class),
                $c->get(AuthenticationService::class),
                $c->get(\Magma\interfaces\cqrs\UserQueryInterface::class),
                $c->get(\Magma\http\SessionInterface::class)
            );
        });

        $container->set(LogoutController::class, function ($c) {
            return new LogoutController(
                $c->get(TemplateEngine::class),
                $c->get(\Magma\security\CsrfManager::class),
                $c->get(\Magma\http\Session::class),
                $c->get(Request::class),
                $c->get(AuthenticationService::class)
            );
        });

        $container->set(PasswordResetController::class, function ($c) {
            return new PasswordResetController();
        });

        $container->set(PolicyController::class, function ($c) {
            return new PolicyController(
                $c->get(TemplateEngine::class),
                $c->get(\Magma\security\CsrfManager::class)
            );
        });

        $container->set(VendorAdminController::class, function ($c) {
            return new VendorAdminController(
                $c->get(TemplateEngine::class),
                $c->get(\Magma\security\CsrfManager::class),
                $c->get(Request::class)
            );
        });

        $container->set(UserDashboardController::class, function ($c) {
            return new UserDashboardController(
                $c->get(TemplateEngine::class),
                $c->get(\Magma\security\CsrfManager::class),
                $c->get(Request::class)
            );
        });
    }
}

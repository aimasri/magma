<?php

declare(strict_types=1);

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\contracts\ClockInterface;
use Magma\contracts\RolePolicyInterface;
use Magma\controllers\LoginController;
use Magma\controllers\LogoutController;
use Magma\controllers\PasswordResetCompletionController;
use Magma\controllers\PasswordResetRequestController;
use Magma\controllers\PolicyController;
use Magma\controllers\RegisterController;
use Magma\http\Session;
use Magma\http\SessionInterface;
use Magma\interfaces\cqrs\TenantQueryInterface;
use Magma\interfaces\cqrs\UserQueryInterface;
use Magma\middleware\CsrfMiddleware;
use Magma\middleware\RoleMiddleware;
use Magma\middleware\SessionTimeoutMiddleware;
use Magma\middleware\ViewShareMiddleware;
use Magma\security\CsrfManager;
use Magma\security\PostLoginRedirectResolver;
use Magma\security\TenantContext;
use Magma\services\AuthenticationService;
use Magma\services\PasswordResetCompletionService;
use Magma\services\PasswordResetRequestService;
use Magma\services\RegistrationService;
use Magma\view\HtmlResponseBuilderInterface;
use Magma\view\TemplateEngine;

/**
 * Title: HttpServiceProvider — registers core HTTP controllers and middleware.
 *
 * Purpose:
 * - Bootstraps the framework's core HTTP transport layer dependencies.
 * - Wires core authentication, password-reset, registration, and policy controllers alongside
 *   global security and view-sharing middleware.
 *
 * Why / Why this design:
 * - Keeps core HTTP bindings strictly separated from application/module controllers (`App\*`, `Modules\*`),
 *   which resolve dynamically via Method Injection and register any custom dependencies in `AppServiceProvider`.
 *
 * Teaching notes:
 * - Core framework controllers (`/magma/controllers/`) use Constructor Injection (`AGENTS.md` §2 Dual DI),
 *   whereas downstream `/app/` and `/modules/` controllers use Method Injection.
 */
class HttpServiceProvider implements ServiceProviderInterface
{
    /**
     * Register Core HTTP Transport Layer Bindings.
     *
     * Execution Flow:
     * 1. Bind global HTTP middleware classes (`ViewShareMiddleware`, `CsrfMiddleware`, `SessionTimeoutMiddleware`, `RoleMiddleware`).
     * 2. Bind core framework authentication, registration, password-reset, and policy controllers.
     *
     * @param Container $container The dependency injection container.
     * @return void
     */
    public function register(Container $container): void
    {
        $container->set(ViewShareMiddleware::class, function (Container $c) {
            $engine = $c->get(TemplateEngine::class);
            assert($engine instanceof TemplateEngine);
            $tenantQuery = $c->get(TenantQueryInterface::class);
            assert($tenantQuery instanceof TenantQueryInterface);
            $session = $c->get(Session::class);
            assert($session instanceof Session);
            $tenantContext = $c->get(TenantContext::class);
            assert($tenantContext instanceof TenantContext);

            return new ViewShareMiddleware($engine, $tenantQuery, $session, $tenantContext);
        });

        $container->set(CsrfMiddleware::class, function (Container $c) {
            $csrf = $c->get(CsrfManager::class);
            assert($csrf instanceof CsrfManager);

            return new CsrfMiddleware($csrf);
        });

        $container->set(SessionTimeoutMiddleware::class, function (Container $c) {
            $session = $c->get(SessionInterface::class);
            assert($session instanceof SessionInterface);
            $clock = $c->get(ClockInterface::class);
            assert($clock instanceof ClockInterface);
            $rolePolicy = $c->get(RolePolicyInterface::class);
            assert($rolePolicy instanceof RolePolicyInterface);

            return new SessionTimeoutMiddleware($session, $clock, $rolePolicy);
        });

        $container->set(RoleMiddleware::class, function (Container $c) {
            $auth = $c->get(AuthenticationService::class);
            assert($auth instanceof AuthenticationService);
            $session = $c->get(SessionInterface::class);
            assert($session instanceof SessionInterface);

            return new RoleMiddleware($auth, $session);
        });

        $container->set(LoginController::class, function (Container $c) {
            $auth = $c->get(AuthenticationService::class);
            assert($auth instanceof AuthenticationService);
            $html = $c->get(HtmlResponseBuilderInterface::class);
            assert($html instanceof HtmlResponseBuilderInterface);
            $resolver = $c->get(PostLoginRedirectResolver::class);
            assert($resolver instanceof PostLoginRedirectResolver);

            return new LoginController($auth, $html, $resolver);
        });

        $container->set(RegisterController::class, function (Container $c) {
            $html = $c->get(HtmlResponseBuilderInterface::class);
            assert($html instanceof HtmlResponseBuilderInterface);
            $reg = $c->get(RegistrationService::class);
            assert($reg instanceof RegistrationService);
            $auth = $c->get(AuthenticationService::class);
            assert($auth instanceof AuthenticationService);
            $query = $c->get(UserQueryInterface::class);
            assert($query instanceof UserQueryInterface);
            $session = $c->get(SessionInterface::class);
            assert($session instanceof SessionInterface);

            return new RegisterController($html, $reg, $auth, $query, $session);
        });

        $container->set(LogoutController::class, function (Container $c) {
            $auth = $c->get(AuthenticationService::class);
            assert($auth instanceof AuthenticationService);

            return new LogoutController($auth);
        });

        $container->set(PasswordResetRequestController::class, function (Container $c) {
            $html = $c->get(HtmlResponseBuilderInterface::class);
            assert($html instanceof HtmlResponseBuilderInterface);
            $session = $c->get(SessionInterface::class);
            assert($session instanceof SessionInterface);
            $resetService = $c->get(PasswordResetRequestService::class);
            assert($resetService instanceof PasswordResetRequestService);

            return new PasswordResetRequestController($html, $session, $resetService);
        });

        $container->set(PasswordResetCompletionController::class, function (Container $c) {
            $html = $c->get(HtmlResponseBuilderInterface::class);
            assert($html instanceof HtmlResponseBuilderInterface);
            $session = $c->get(SessionInterface::class);
            assert($session instanceof SessionInterface);
            $completionService = $c->get(PasswordResetCompletionService::class);
            assert($completionService instanceof PasswordResetCompletionService);

            return new PasswordResetCompletionController($html, $session, $completionService);
        });

        $container->set(PolicyController::class, function (Container $c) {
            $html = $c->get(HtmlResponseBuilderInterface::class);
            assert($html instanceof HtmlResponseBuilderInterface);

            return new PolicyController($html);
        });
    }
}

<?php

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\config\Config;
use Magma\view\TemplateEngine;
use Magma\routing\UrlGenerator;
use Magma\http\Session;
use Magma\queue\QueueInterface;
use Magma\database\TransactionManagerInterface;

use Magma\interfaces\cqrs\UserQueryInterface;
use Magma\interfaces\cqrs\UserCommandInterface;
use Magma\repositories\UserTokenRepository;
use Magma\repositories\UserTokenRepositoryInterface;
use Modules\Reviews\interfaces\cqrs\SiteReviewCommandInterface;
use Magma\services\MailerService;
use Magma\services\AuthenticationService;
use Magma\services\RegistrationService;
use Magma\services\PasswordResetService;
use Magma\services\RememberMeService;
use Modules\Reviews\services\ReviewSubmissionService;
use Magma\services\PaginationService;


/**
 * Title: DomainServiceProvider — registers domain-specific business logic services.
 *
 * Purpose:
 * - Bootstraps the application's domain services (Authentication, Registration, Mail, etc.).
 * - Wires up required dependencies like Repositories and Queues into the Services.
 *
 * Why / Why this design:
 * - Isolating domain services into their own provider applies the Open/Closed Principle 
 *   to service registration. New domain logic can be added without modifying repository 
 *   or HTTP bindings.
 *
 * Teaching notes:
 * - Services should never be injected with HTTP Requests or Sessions directly if possible 
 *   (with the exception of AuthenticationService which inherently manages session state). 
 *   This keeps them purely focused on business rules.
 * - Always inject interfaces (e.g., QueueInterface) rather than concrete infrastructure 
 *   classes (e.g., MailerService) into domain services to maintain Dependency Inversion 
 *   and allow asynchronous processing.
 */
class DomainServiceProvider implements ServiceProviderInterface
{
    /**
     * Register Domain Service Bindings
     *
     * Execution Flow:
     * 1. Bind cross-cutting infrastructure services like MailerService.
     * 2. Bind core business logic services, injecting their required Repository Interfaces.
     *
     * Logic behind the logic:
     * - The `$c->get(UserRepositoryInterface::class)` calls rely on the `RepositoryServiceProvider` 
     *   having already registered those interfaces. Order of provider registration in `bootstrap.php` matters.
     *
     * @param Container $container The global dependency injection container.
     * @return void
     */
    public function register(Container $container): void
    {
        $container->set(\Magma\services\MailTransportInterface::class, function ($c) {
            return new \Magma\services\NativeMailTransport();
        });

        $container->set(MailerService::class, function ($c) {
            $config = Config::getMailerSettings();
            $stringConfig = [];
            foreach ($config as $k => $v) {
                $stringConfig[(string)$k] = (string)$v;
            }
            return new MailerService(
                $c->get(TemplateEngine::class),
                $c->get(\Magma\services\MailTransportInterface::class),
                $stringConfig
            );
        });

        $container->set(\Magma\services\PasswordResetRequestService::class, function ($c) {
            return new \Magma\services\PasswordResetRequestService(
                $c->get(UserQueryInterface::class),
                $c->get(\Magma\repositories\PasswordResetTokenRepository::class),
                $c->get(QueueInterface::class),
                $c->get(UrlGenerator::class),
                $c->get(TransactionManagerInterface::class)
            );
        });

        $container->set(\Magma\services\PasswordResetCompletionService::class, function ($c) {
            return new \Magma\services\PasswordResetCompletionService(
                $c->get(UserCommandInterface::class),
                $c->get(\Magma\repositories\PasswordResetTokenRepository::class),
                $c->get(\Magma\repositories\RememberTokenRepository::class),
                $c->get(TransactionManagerInterface::class)
            );
        });

        $container->set(AuthenticationService::class, function ($c) {
            return new AuthenticationService(
                $c->get(UserQueryInterface::class),
                $c->get(Session::class),
                $c->get(RememberMeService::class)
            );
        });

        $container->set(RememberMeService::class, function ($c) {
            return new RememberMeService(
                $c->get(\Magma\repositories\RememberTokenRepository::class)
            );
        });

        $container->set(RegistrationService::class, function ($c) {
            $cmd = $c->get(UserCommandInterface::class);
            assert($cmd instanceof UserCommandInterface);
            $evt = $c->get(\Magma\interfaces\EventDispatcherInterface::class);
            assert($evt instanceof \Magma\interfaces\EventDispatcherInterface);
            $tx = $c->get(TransactionManagerInterface::class);
            assert($tx instanceof TransactionManagerInterface);

            return new RegistrationService($cmd, $evt, $tx);
        });

        $container->set(ReviewSubmissionService::class, function ($c) {
            return new ReviewSubmissionService(
                $c->get(SiteReviewCommandInterface::class)
            );
        });

        $container->set(PaginationService::class, function ($c) {
            return new PaginationService();
        });

        $container->set(\Magma\queue\IdempotentProjectionGuard::class, function ($c) {
            $db = $c->get(\Magma\database\DatabaseConnectionManager::class);
            assert($db instanceof \Magma\database\DatabaseConnectionManager);
            return new \Magma\queue\IdempotentProjectionGuard($db);
        });
    }
}

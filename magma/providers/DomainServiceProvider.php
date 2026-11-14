<?php

declare(strict_types=1);

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
use Magma\services\MailerService;
use Magma\services\AuthenticationService;
use Magma\services\RegistrationService;
use Magma\services\RememberMeService;
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
        $container->set(\Magma\services\MailTransportInterface::class, function (Container $c) {
            $config = Config::getMailerSettings();
            $host = trim($config['host']);
            if ($host !== '' && strtolower($host) !== 'none') {
                $smtpConfig = array_merge($config, [
                    'verify_tls' => Config::getBool('MAIL_VERIFY_TLS', true),
                ]);
                $clock = $c->has(\Magma\contracts\ClockInterface::class)
                    ? $c->get(\Magma\contracts\ClockInterface::class)
                    : null;
                return new \Magma\services\SmtpMailTransport(
                    $smtpConfig,
                    $clock instanceof \Magma\contracts\ClockInterface ? $clock : null
                );
            }
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
                $c->get(TransactionManagerInterface::class),
                $c->get(\Magma\interfaces\EventDispatcherInterface::class),
                $c->get(\Magma\contracts\ClockInterface::class)
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

        $container->set(RememberMeService::class, function (Container $c) {
            $tokenRepo = $c->get(\Magma\interfaces\repositories\RememberTokenRepositoryInterface::class);
            assert($tokenRepo instanceof \Magma\interfaces\repositories\RememberTokenRepositoryInterface);
            $clock = $c->get(\Magma\contracts\ClockInterface::class);
            assert($clock instanceof \Magma\contracts\ClockInterface);
            $tx = $c->has(TransactionManagerInterface::class)
                ? $c->get(TransactionManagerInterface::class)
                : null;

            return new RememberMeService(
                $tokenRepo,
                $clock,
                $tx instanceof TransactionManagerInterface ? $tx : null
            );
        });

        $container->set(AuthenticationService::class, function (Container $c) {
            $userQuery = $c->get(UserQueryInterface::class);
            assert($userQuery instanceof UserQueryInterface);
            $userCommand = $c->get(UserCommandInterface::class);
            assert($userCommand instanceof UserCommandInterface);
            $rememberMe = $c->get(RememberMeService::class);
            assert($rememberMe instanceof RememberMeService);
            $session = $c->get(\Magma\http\SessionInterface::class);
            assert($session instanceof \Magma\http\SessionInterface);
            $clock = $c->get(\Magma\contracts\ClockInterface::class);
            assert($clock instanceof \Magma\contracts\ClockInterface);

            return new AuthenticationService($userQuery, $userCommand, $rememberMe, $session, $clock);
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

        $container->set(PaginationService::class, function () {
            return new PaginationService();
        });

        $container->set(\Magma\queue\IdempotentProjectionGuard::class, function ($c) {
            $db = $c->get(\Magma\database\DatabaseConnectionManager::class);
            $clock = $c->get(\Magma\contracts\ClockInterface::class);
            assert($db instanceof \Magma\database\DatabaseConnectionManager);
            return new \Magma\queue\IdempotentProjectionGuard($db, $clock);
        });
    }
}

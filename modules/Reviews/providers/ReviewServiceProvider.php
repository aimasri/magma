<?php

declare(strict_types=1);

namespace Modules\Reviews\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\database\DatabaseConnectionManager;
use Magma\security\TenantContext;
use Modules\Reviews\controllers\ReviewController;
use Modules\Reviews\domain\ReviewFactory;
use Modules\Reviews\interfaces\ReviewFactoryInterface;
use Modules\Reviews\interfaces\ReviewSubmissionServiceInterface;
use Modules\Reviews\interfaces\cqrs\SiteReviewCommandInterface;
use Modules\Reviews\interfaces\cqrs\SiteReviewQueryInterface;
use Modules\Reviews\repositories\SiteReviewCommandRepository;
use Modules\Reviews\repositories\SiteReviewQueryRepository;
use Modules\Reviews\services\ReviewSubmissionService;

/**
 * Title: Reviews Module Service Provider
 *
 * Purpose:
 * - Registers CQRS repositories, domain factories, services, and controllers belonging exclusively
 *   to the `Modules\Reviews` vertical slice.
 *
 * Why / Why this design:
 * - Domain Agnosticism in Core: Keeps `magma/providers/*` 100% free of module-specific imports so
 *   downstream applications that omit `Modules\Reviews` never carry dead or broken container bindings.
 *
 * Teaching notes:
 * - Module service providers are registered from `App\providers\AppServiceProvider`, never inside
 *   `magma/config/bootstrap.php` or core providers.
 */
class ReviewServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers all `Modules\Reviews` container bindings.
     *
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $container->set(SiteReviewQueryInterface::class, function (Container $c) {
            $db = $c->get(DatabaseConnectionManager::class);
            assert($db instanceof DatabaseConnectionManager);
            $tenantContext = $c->get(TenantContext::class);
            assert($tenantContext instanceof TenantContext);

            return new SiteReviewQueryRepository($db, $tenantContext);
        });

        $container->set(SiteReviewCommandInterface::class, function (Container $c) {
            $db = $c->get(DatabaseConnectionManager::class);
            assert($db instanceof DatabaseConnectionManager);
            $tenantContext = $c->get(TenantContext::class);
            assert($tenantContext instanceof TenantContext);

            return new SiteReviewCommandRepository($db, $tenantContext);
        });

        $container->set(ReviewFactoryInterface::class, function () {
            return new ReviewFactory();
        });

        $container->set(ReviewSubmissionServiceInterface::class, function (Container $c) {
            $commandRepo = $c->get(SiteReviewCommandInterface::class);
            assert($commandRepo instanceof SiteReviewCommandInterface);
            $factory = $c->get(ReviewFactoryInterface::class);
            assert($factory instanceof ReviewFactoryInterface);

            return new ReviewSubmissionService($commandRepo, $factory);
        });

        $container->set(ReviewSubmissionService::class, function (Container $c) {
            return $c->get(ReviewSubmissionServiceInterface::class);
        });

        $container->set(ReviewController::class, function () {
            return new ReviewController();
        });
    }
}

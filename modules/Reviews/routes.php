<?php

declare(strict_types=1);

/**
 * Title: Reviews Module Routes Configuration
 *
 * Purpose:
 * - Defines the routing endpoints specific to the Reviews module.
 *
 * Why / Why this design:
 * - Enforces strict module boundaries. Routes are self-contained within the module.
 *
 * Teaching notes:
 * - By encapsulating routes in the module directory, we allow modules to be dropped in or removed without touching the core routing registry.
 */

use Modules\Reviews\controllers\ReviewController;
use Magma\routing\RouteDefinition;

return [
    RouteDefinition::post('/reviews', [ReviewController::class, 'submitReview']),
];

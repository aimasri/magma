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
 */

use Modules\Reviews\controllers\ReviewController;
use Magma\routing\RouteDefinition;

return [
    RouteDefinition::post('/reviews', [ReviewController::class, 'submitReview']),
];

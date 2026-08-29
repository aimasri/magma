<?php

declare(strict_types=1);

/**
 * Title: Application Routes Configuration
 *
 * Purpose:
 * - Defines the mapping between HTTP methods, URIs, and their corresponding downstream controller actions.
 *
 * Why / Why this design:
 * - Separates application-specific routes from Magma core routes, maintaining core framework purity.
 */

use App\controllers\HomeController;
use Magma\routing\RouteDefinition;

return [
    RouteDefinition::get('/', [HomeController::class, 'index']),
    RouteDefinition::get('/syllabus', [HomeController::class, 'syllabus']),
];

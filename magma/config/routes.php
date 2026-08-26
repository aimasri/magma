<?php

/**
 * Title: Core HTTP Routes Configuration
 *
 * Purpose:
 * - Defines the mapping between HTTP methods, URIs, and their corresponding controller actions.
 * - Acts as the single source of truth for the application's URL surface area.
 *
 * Why / Why this design:
 * - Storing routes as a simple array promotes fast parsing and declarative routing.
 * - Decouples route definitions from the application code, making the API contract easily reviewable.
 *
 * Teaching notes:
 * - This array-based configuration is highly cacheable. In production environments, this can be serialized 
 *   or compiled to avoid runtime overhead.
 */
use App\controllers\HomeController;
use Modules\Reviews\controllers\ReviewController;

return [
    ['GET', '/', [HomeController::class, 'index']],
    ['GET', '/syllabus', [HomeController::class, 'syllabus']],
    ['POST', '/reviews', [ReviewController::class, 'submitReview']],
];

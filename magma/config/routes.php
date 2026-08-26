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
use Magma\controllers\PasswordResetRequestController;
use Magma\controllers\PasswordResetCompletionController;
use Magma\routing\RouteDefinition;

return [
    RouteDefinition::get('/', [HomeController::class, 'index']),
    RouteDefinition::get('/syllabus', [HomeController::class, 'syllabus']),
    RouteDefinition::post('/reviews', [ReviewController::class, 'submitReview']),
    RouteDefinition::get('/forgot-password', [PasswordResetRequestController::class, 'forgotPassword']),
    RouteDefinition::post('/forgot-password', [PasswordResetRequestController::class, 'sendResetLink']),
    RouteDefinition::get('/reset-password', [PasswordResetCompletionController::class, 'resetPasswordForm']),
    RouteDefinition::post('/reset-password', [PasswordResetCompletionController::class, 'resetPassword']),
];

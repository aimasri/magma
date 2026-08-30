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
use Magma\controllers\PasswordResetRequestController;
use Magma\controllers\PasswordResetCompletionController;
use Magma\controllers\LoginController;
use Magma\routing\RouteDefinition;

return [
    RouteDefinition::get('/login', [LoginController::class, 'login']),
    RouteDefinition::post('/login', [LoginController::class, 'authenticate']),
    RouteDefinition::get('/forgot-password', [PasswordResetRequestController::class, 'forgotPassword']),
    RouteDefinition::post('/forgot-password', [PasswordResetRequestController::class, 'sendResetLink']),
    RouteDefinition::get('/reset-password', [PasswordResetCompletionController::class, 'resetPasswordForm']),
    RouteDefinition::post('/reset-password', [PasswordResetCompletionController::class, 'resetPassword']),
    RouteDefinition::get('/logout', [LoginController::class, 'logout']),
];

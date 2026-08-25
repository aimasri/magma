<?php

namespace Magma\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\view\TemplateEngine;
use Modules\Reviews\interfaces\cqrs\SiteReviewQueryInterface;
use Magma\services\PaginationService;

/**
 * Title: Home Controller
 *
 * Purpose:
 * - Collects data from repositories and renders the homepage.
 *
 * Why this design:
 * - Serves as an aggregation root. It passes data to the view while remaining a thin controller.
 *
 * Teaching notes:
 * - Prefer composing repository results here and keep normalization logic inside repository classes to maintain single responsibility.
 */
class HomeController
{
    /**
     * Orchestrates the rendering of the landing page.
     * 
     * Execution Flow:
     * 1. Request review data from `SiteReviewQueryInterface`.
     * 2. Extract and clear any flashed success messages from the session.
     * 3. Pass the payload to the TemplateEngine to render `home.php`.
     */
    public function index(
        \Magma\view\HtmlResponseBuilderInterface $html,
        SiteReviewQueryInterface $siteReviewRepository,
        Request $request,
        PaginationService $paginationService,
        \Magma\http\SessionInterface $session
    ): Response {
        // Public homepage locks the limit to 20 to prevent excessive load
        $pagination = $paginationService->getPagination(
            $request, 
            defaultLimit: 20, 
            allowUserOverride: false
        );

        // Fetch consolidated reviews as a Generator and pass directly to the view to defer memory allocation
        $allReviews = $siteReviewRepository->getApprovedReviews(
            $pagination->limit, 
            $pagination->lastId
        );

        $successMessage = $session->flash('success_message');

        return $html->render('home', [
            'reviews' => $allReviews,
            'success_message' => $successMessage
        ]);
    }

    public function catchAll(): Response
    {
        return new RedirectResponse('/');
    }
}

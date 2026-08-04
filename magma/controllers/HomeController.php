<?php

namespace Magma\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\view\TemplateEngine;
use Magma\interfaces\cqrs\SiteReviewQueryInterface;
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
class HomeController extends BaseController
{
    private SiteReviewQueryInterface $siteReviewRepository;
    private Request $request;
    private PaginationService $paginationService;
    private \Magma\http\Session $session;

    public function __construct(
        TemplateEngine $templateEngine,
        \Magma\security\CsrfManager $csrfManager,
        \Magma\http\Session $session,
        SiteReviewQueryInterface $siteReviewRepository,
        Request $request,
        PaginationService $paginationService
    ) {
        parent::__construct($templateEngine, $csrfManager, $session);
        $this->siteReviewRepository = $siteReviewRepository;
        $this->request = $request;
        $this->paginationService = $paginationService;
        $this->session = $session;
    }

    /**
     * Orchestrates the rendering of the landing page.
     * 
     * Execution Flow:
     * 1. Request review data from `SiteReviewQueryInterface`.
     * 2. Extract and clear any flashed success messages from the session.
     * 3. Pass the payload to the TemplateEngine to render `home.php`.
     */
    public function index(): Response
    {
        // Public homepage locks the limit to 20 to prevent excessive load
        $pagination = $this->paginationService->getPagination(
            $this->request, 
            defaultLimit: 20, 
            allowUserOverride: false
        );

        // Fetch consolidated reviews as a Generator and pass directly to the view to defer memory allocation
        $allReviews = $this->siteReviewRepository->getApprovedReviews(
            $pagination->limit, 
            $pagination->lastId
        );

        $successMessage = $this->session->flash('success_message');

        return $this->render('home', [
            'reviews' => $allReviews,
            'success_message' => $successMessage
        ]);
    }

    public function catchAll(): Response
    {
        return new RedirectResponse('/');
    }
}

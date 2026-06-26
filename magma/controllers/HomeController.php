<?php

namespace Magma\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\services\ReviewSubmissionService;
use Magma\view\TemplateEngine;
use Magma\validation\Validator;
use Magma\requests\ReviewRequest;
use Magma\services\ReviewAggregatorService;
use Magma\services\PaginationService;

/**
 * HomeController — landing page aggregation and review submission.
 *
 * Purpose:
 * - Collect data from repositories (DB, XML, external APIs) and render the
 *   homepage. Also handles user-submitted reviews via a dedicated request.
 *
 * Why / Why this design:
 * - Serves as an aggregation root. It delegates the complex process of merging 
 *   multiple data sources to the `ReviewAggregatorService` to remain a thin controller.
 *
 * Teaching notes:
 * - Prefer composing repository results here and keep normalization logic
 *   inside repository classes to maintain single responsibility.
 */
class HomeController extends BaseController
{
    private ReviewAggregatorService $reviewAggregatorService;
    private ReviewSubmissionService $reviewSubmissionService;
    private Request $request;
    private Validator $validator;
    private PaginationService $paginationService;

    public function __construct(
        TemplateEngine $templateEngine,
        \Magma\security\CsrfManager $csrfManager,
        ReviewAggregatorService $reviewAggregatorService,
        ReviewSubmissionService $reviewSubmissionService,
        Request $request,
        Validator $validator,
        PaginationService $paginationService
    ) {
        parent::__construct($templateEngine, $csrfManager);
        $this->reviewAggregatorService = $reviewAggregatorService;
        $this->reviewSubmissionService = $reviewSubmissionService;
        $this->request = $request;
        $this->validator = $validator;
        $this->paginationService = $paginationService;
    }

    /**
     * Orchestrates the rendering of the landing page.
     * 
     * Execution Flow:
     * 1. Request aggregated review data from `ReviewAggregatorService`.
     * 2. Extract and clear any flashed success messages from the session.
     * 3. Pass the payload to the TemplateEngine to render `home.php`.
     * 
     * Logic behind the logic:
     * - Merging legacy XML reviews with modern DB reviews occurs at the service 
     *   layer so the controller doesn't need to know *how* the data was fetched.
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
        $allReviews = $this->reviewAggregatorService->getAggregatedReviews(
            $pagination->limit, 
            $pagination->lastId
        );

        $successMessage = $this->request->flash('success_message');

        return $this->render('home', [
            'reviews' => $allReviews,
            'success_message' => $successMessage
        ]);
    }

    /**
     * Processes new customer review submissions.
     * 
     * Execution Flow:
     * 1. Validate the incoming request payload using `ReviewRequest`.
     * 2. Delegate the validated data to the `ReviewSubmissionService`.
     * 3. Flash a success message to the session.
     * 4. Redirect the user back to the homepage via a `RedirectResponse`.
     * 
     * Logic behind the logic:
     * - The PRG (Post/Redirect/Get) pattern prevents the user from accidentally 
     *   submitting the review twice if they refresh the page.
     */
    public function submitReview(): Response
    {
        $this->validateOrRedirect(new ReviewRequest($this->request, $this->validator), '/');

        $data = $this->request->request();
        $this->reviewSubmissionService->submit($data);

        $this->request->setSession('success_message', 'Review Submitted! Thank you. Your review is now pending moderation.');
        return new RedirectResponse('/');
    }

    public function catchAll(): Response
    {
        return new RedirectResponse('/');
    }
}

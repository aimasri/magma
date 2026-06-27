<?php

namespace Magma\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\services\ReviewSubmissionService;
use Magma\view\TemplateEngine;
use Magma\validation\Validator;
use Magma\requests\ReviewRequest;
use Magma\models\SiteReviewRepositoryInterface;
use Magma\services\PaginationService;

/**
 * HomeController — landing page aggregation and review submission.
 *
 * Purpose:
 * - Collect data from repositories and render the
 *   homepage. Also handles user-submitted reviews via a dedicated request.
 *
 * Why / Why this design:
 * - Serves as an aggregation root. It passes data to the view while remaining a thin controller.
 *
 * Teaching notes:
 * - Prefer composing repository results here and keep normalization logic
 *   inside repository classes to maintain single responsibility.
 */
class HomeController extends BaseController
{
    private SiteReviewRepositoryInterface $siteReviewRepository;
    private ReviewSubmissionService $reviewSubmissionService;
    private Request $request;
    private Validator $validator;
    private PaginationService $paginationService;

    public function __construct(
        TemplateEngine $templateEngine,
        \Magma\security\CsrfManager $csrfManager,
        SiteReviewRepositoryInterface $siteReviewRepository,
        ReviewSubmissionService $reviewSubmissionService,
        Request $request,
        Validator $validator,
        PaginationService $paginationService
    ) {
        parent::__construct($templateEngine, $csrfManager);
        $this->siteReviewRepository = $siteReviewRepository;
        $this->reviewSubmissionService = $reviewSubmissionService;
        $this->request = $request;
        $this->validator = $validator;
        $this->paginationService = $paginationService;
    }

    /**
     * Orchestrates the rendering of the landing page.
     * 
     * Execution Flow:
     * 1. Request review data from `SiteReviewRepositoryInterface`.
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

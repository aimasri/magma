<?php

namespace Magma\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\services\ReviewSubmissionService;
use Magma\validation\Validator;
use Magma\requests\ReviewRequest;
use Magma\view\TemplateEngine;
use Magma\security\CsrfManager;

/**
 * Title: Review Controller
 *
 * Purpose:
 * - Handles the submission of user reviews.
 *
 * Why this design:
 * - Separates the concern of review submission from the generic HomeController,
 *   adhering to the Single Responsibility Principle.
 *
 * Teaching notes:
 * - Notice how validation is injected/encapsulated within a FormRequest-style object (ReviewRequest), keeping the controller thin.
 */
class ReviewController extends BaseController
{
    private ReviewSubmissionService $reviewSubmissionService;
    private Request $request;
    private Validator $validator;

    public function __construct(
        TemplateEngine $templateEngine,
        CsrfManager $csrfManager,
        ReviewSubmissionService $reviewSubmissionService,
        Request $request,
        Validator $validator
    ) {
        parent::__construct($templateEngine, $csrfManager);
        $this->reviewSubmissionService = $reviewSubmissionService;
        $this->request = $request;
        $this->validator = $validator;
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
}

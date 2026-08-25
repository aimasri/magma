<?php

declare(strict_types=1);

namespace Modules\Reviews\controllers;

use Magma\http\Request;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Modules\Reviews\services\ReviewSubmissionService;
use Modules\Reviews\interfaces\ReviewSubmissionServiceInterface;
use Modules\Reviews\dto\ReviewDTO;
use Modules\Reviews\requests\ReviewRequest;
use Magma\security\CsrfManager;

/**
 * Title: Review Controller
 *
 * Purpose:
 * - Handles the submission of user reviews.
 *
 * Why / Why this design:
 * - Separates the concern of review submission from the generic HomeController,
 *   adhering to the Single Responsibility Principle.
 *
 * Teaching notes:
 * - Notice how validation is injected/encapsulated within a FormRequest-style object (ReviewRequest), keeping the controller thin.
 */
class ReviewController
{
    public function __construct(
        private readonly ReviewSubmissionServiceInterface $reviewSubmissionService,
        private readonly \Magma\http\SessionInterface $session
    ) {}

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
    public function submitReview(ReviewRequest $reviewRequest): Response 
    {
        // Validation is automatically handled by RouteParameterResolver for the ReviewRequest parameter.

        $dto = $reviewRequest->toDTO();
        $this->reviewSubmissionService->submit($dto);

        $this->session->set('success', 'Your review has been submitted and is pending moderation.');
        return new RedirectResponse('/');
    }
}

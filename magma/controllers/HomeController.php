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
 * - Notice how controllers rarely perform business logic. They fetch, orchestrate, and respond.
 *
 *
 * [AI_AUDIT_EXCEPTION]
 * SRP_HEURISTIC_IGNORE: This class intentionally exceeds the 3-dependency limit rule (5 dependencies).
 * REASON: Gathering the pagination limit, extracting the tenant context, and querying the database is a single cohesive data-gathering workflow for rendering the homepage. We accept these 5 dependencies without unnecessary abstraction.
 */
class HomeController
{
    /**
     * Initializes the HomeController with required dependencies.
     *
     * Logic behind the logic:
     * - Collects all necessary services via constructor injection to handle the homepage rendering.
     *   While it has multiple dependencies, they are highly cohesive for the specific task of 
     *   aggregating data for the main landing page.
     */
    public function __construct(
        private readonly \Magma\view\HtmlResponseBuilderInterface $html,
        private readonly SiteReviewQueryInterface $siteReviewRepository,
        private readonly PaginationService $paginationService,
        private readonly \Magma\http\SessionInterface $session,
        private readonly \Magma\security\TenantContext $tenantContext
    ) {}

    /**
     * Orchestrates the rendering of the landing page.
     * 
     * Execution Flow:
     * 1. Request review data from `SiteReviewQueryInterface`.
     * 2. Extract and clear any flashed success messages from the session.
     * 3. Pass the payload to the TemplateEngine to render `home.php`.
     */
    public function index(Request $request): Response {
        // Public homepage locks the limit to 20 to prevent excessive load
        $lastIdParam = $request->query('last_id');
        $lastId = $lastIdParam !== null && is_scalar($lastIdParam) ? (int)$lastIdParam : null;
        
        $reqLimitParam = $request->query('limit');
        $reqLimit = $reqLimitParam !== null && is_scalar($reqLimitParam) ? (int)$reqLimitParam : null;

        $pagination = $this->paginationService->getPagination(
            lastId: $lastId,
            reqLimit: $reqLimit,
            defaultLimit: SiteReviewQueryInterface::DEFAULT_LIMIT, 
            allowUserOverride: false
        );

        $tenantId = $this->tenantContext->getTenantId();

        // Fetch consolidated reviews as a Generator and pass directly to the view to defer memory allocation
        $allReviews = $this->siteReviewRepository->getApprovedReviews(
            $tenantId,
            $pagination->limit, 
            $pagination->lastId
        );

        $successMessage = $this->session->flash('success_message');

        return $this->html->render('home', [
            'reviews' => $allReviews,
            'success_message' => $successMessage
        ]);
    }

    /**
     * Catches all unmatched routes intended for the home controller and redirects to the root path.
     *
     * Logic behind the logic:
     * - Acts as a failsafe mechanism to capture arbitrary or malformed requests aimed at the base 
     *   path, ensuring users are gracefully redirected to a valid destination instead of encountering 
     *   a raw 404 error page.
     *
     * @return Response
     */
    public function catchAll(): Response
    {
        return new RedirectResponse('/');
    }
}

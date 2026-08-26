<?php
declare(strict_types=1);

namespace App\controllers;

use Magma\http\RedirectResponse;
use Magma\security\CsrfManager;
use Magma\http\Session;
use App\services\SystemDiagnosticsServiceInterface;

/**
 * Title: Home Controller
 *
 * Purpose:
 * - Handles requests to the application's root landing page.
 * - Bridges the routing mechanism to the presentation layer.
 *
 * Why / Why this design:
 * - Follows the MVC (Model-View-Controller) paradigm, keeping routing simple and offloading 
 *   view assembly to dedicated classes.
 *
 * Teaching notes:
 * - Controllers should serve only as HTTP traffic directors. They collect input, invoke domain logic, 
 *   and return a response, but should not contain complex business rules.
 */
class HomeController
{
    /**
     * Renders the welcome page for incoming requests.
     *
     * 1. Defines template variables (e.g., the page title).
     * 2. Delegates the rendering process to the HtmlResponseBuilder.
     * 3. Returns the formulated HTTP response to the client.
     *
     * @return \Magma\http\Response
     */
    public function index(
        SystemDiagnosticsServiceInterface $diagnosticsService,
        \Magma\view\HtmlResponseBuilderInterface $html
    ): \Magma\http\Response {
        $diagnostics = $diagnosticsService->getDiagnostics();

        return $html->render('welcome', [
            'title'       => \App\constants\AppConstants::HOME_TITLE,
            'diagnostics' => $diagnostics,
        ], null);
    }

    /**
     * Renders the masterclass syllabus page.
     *
     * @return \Magma\http\Response
     */
    public function syllabus(
        \Magma\view\HtmlResponseBuilderInterface $html
    ): \Magma\http\Response {
        return $html->render('syllabus', [
            'title' => \App\constants\AppConstants::SYLLABUS_TITLE
        ], null);
    }
}

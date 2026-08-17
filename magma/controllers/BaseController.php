<?php

declare(strict_types=1);

namespace Magma\controllers;

use Magma\http\RedirectResponse;
use Magma\http\Request;
use Magma\http\Response;
use Magma\http\HttpResponseException;
use Magma\http\SessionInterface;
use Magma\security\CsrfManager;
use Magma\validation\FormRequest;
use Magma\validation\ValidationException;
use Magma\view\TemplateEngine;

/**
 * Title: Base Application Controller
 *
 * Purpose:
 * - Provides foundational helper methods (view rendering, validation redirect shortcuts, and standardized JSON error boundaries) shared across concrete controllers.
 * - Injects shared transport services (`TemplateEngine`, `CsrfManager`, `SessionInterface`).
 *
 * Why / Why this design:
 * - Layer Supertype Pattern: Consolidates common HTTP mechanics into a shared ancestor, enforcing DRY controllers and eliminating repetitive try/catch JSON serialization blocks.
 * - CSRF Header Synchronization: Automatically supplies `$csrfToken` and `$csrfField` to all rendered view templates to support modern `<meta name="csrf-token">` and `<input type="hidden">` synchronizations.
 *
 * Teaching notes:
 * - Business and domain rules must never reside in the `BaseController`; it is strictly a transport and presentation layer orchestrator.
 */
abstract class BaseController
{
    public function __construct(
        protected readonly TemplateEngine $templateEngine,
        protected readonly CsrfManager $csrfManager,
        protected readonly SessionInterface $session
    ) {}

    /**
     * Syntactic sugar for rendering a template view via the TemplateEngine.
     * Automatically binds CSRF helper tokens for meta tags and form inputs.
     *
     * @param string $template Template name or path
     * @param array $data Data array passed to view
     * @param string|null $layout Layout wrapper (default: 'default')
     * @return Response
     */
    protected function render(string $template, array $data = [], ?string $layout = 'default'): Response
    {
        $data['csrfField'] = $this->csrfManager->csrfField();
        $data['csrfToken'] = $this->csrfManager->getToken();

        return new Response($this->templateEngine->render($template, $data, $layout));
    }

    /**
     * High-level validation handler executing the "Validate or Redirect" pattern for HTML forms.
     *
     * Execution Flow:
     * 1. Calls `$formRequest->validate()`.
     * 2. If valid, returns null allowing the controller action to proceed.
     * 3. If validation fails, catches `ValidationException`, flashes error messages and old input to the session, and returns a `RedirectResponse`.
     *
     * @param FormRequest $formRequest
     * @param string $redirectPath
     * @return RedirectResponse|null
     */
    protected function validateOrRedirect(FormRequest $formRequest, string $redirectPath): ?RedirectResponse
    {
        try {
            $formRequest->validate();
            return null;
        } catch (ValidationException $e) {
            $request = $formRequest->getRequest();
            $this->session->set('errors', $e->getErrors());
            $this->session->set('old', $request->request());

            return new RedirectResponse($redirectPath);
        }
    }

}
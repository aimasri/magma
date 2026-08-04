<?php

namespace Magma\controllers;

use Magma\http\RedirectResponse;
use Magma\http\Request;
use Magma\http\HttpResponseException;
use Magma\validation\FormRequest;
use Magma\validation\ValidationException;
use Magma\view\TemplateEngine;

/**
 * Title: Base Application Controller
 *
 * Purpose:
 * - Provides foundational helper methods (like view rendering and validation shortcuts) that are shared across all concrete controllers.
 * - Centralizes dependencies (like the `TemplateEngine`) so child classes don't need to inject them repeatedly.
 *
 * Why this design:
 * - Implements the Layer Supertype pattern. By inheriting from a common base class, we eliminate boilerplate code in the child controllers, specifically around the repetitive "Validate or Redirect" HTTP flow.
 *
 * Teaching notes:
 * - Be cautious not to put domain-specific business logic in a BaseController. It should only contain generic HTTP or orchestration helpers. If a method is only used by one child controller, it belongs in that child, not the base.
 */
abstract class BaseController
{
    public function __construct(
        protected readonly TemplateEngine $templateEngine,
        protected readonly \Magma\security\CsrfManager $csrfManager,
        protected readonly \Magma\http\Session $session
    ) {}

    /**
     * Syntactic sugar for rendering a view via the TemplateEngine.
     * 
     * It delegates the heavy lifting to the engine and returns a fully 
     * formed Response object.
     */
    protected function render(string $template, array $data = [], ?string $layout = 'default'): \Magma\http\Response
    {
        $data['csrfField'] = $this->csrfManager->csrfField();
        return new \Magma\http\Response($this->templateEngine->render($template, $data, $layout));
    }

    /**
     * High-level validation handler.
     * 
     * This method streamlines the "Validate or Redirect" pattern common in 
     * web forms. If validation fails, it automatically flashes the errors 
     * and the 'old' input data into the session before performing a 
     * redirect. This prevents the user from losing their progress while 
     * keeping the controller code clean.
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
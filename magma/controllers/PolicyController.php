<?php

namespace Magma\controllers;

use Magma\http\Response;
use Magma\view\TemplateEngine;

/**
 * Title: Policy Controller
 *
 * Purpose:
 * - Renders static policy pages, injecting application metadata for branding.
 *
 * Why this design:
 * - Standardizes even static pages through the Controller/View pipeline, ensuring that global middleware (like `ViewShareMiddleware`) automatically applies to them.
 *
 * Teaching notes:
 * - Keep these controllers minimal; static content can often be served directly from views or a simple CMS in larger projects.
 */
class PolicyController
{
    /**
     * Displays the Privacy & Cookie Policy.
     * Merges current vendor metadata into the template for dynamic branding.
     */
    public function index(\Magma\view\HtmlResponseBuilderInterface $html): Response
    {
        return $html->render('policy', [
            'title'   => 'Privacy & Cookie Policy'
        ]);
    }
}
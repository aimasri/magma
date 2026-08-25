<?php

declare(strict_types=1);

namespace Magma\view;

use Magma\http\Response;

/**
 * Title: HtmlResponseBuilderInterface
 *
 * Purpose:
 * - Defines the contract for building HTTP Responses from HTML templates
 * - Ensures any implementing class automatically binds essential data (like CSRF tokens) to the view
 *
 * Why / Why this design:
 * - Interface Segregation Principle: Keeps response generation distinct from generic template rendering
 * - Contract-Driven Design: Allows swapping out the HTML rendering engine or response logic without affecting controllers
 *
 * Teaching notes:
 * - Useful in controllers where returning a standardized Response object is required rather than raw string output.
 */
interface HtmlResponseBuilderInterface
{
    /**
     * Renders an HTML template into an HTTP Response.
     * 
     * 1. Expects implementing classes to merge critical data (e.g., CSRF fields) into the provided data array.
     * 2. Transforms the rendered template string into a standard HTTP Response object.
     *
     * @param string $template Template name or path
     * @param array $data View variables
     * @param string|null $layout Layout wrapper
     * @return Response
     */
    public function render(string $template, array $data = [], ?string $layout = 'default'): Response;
}

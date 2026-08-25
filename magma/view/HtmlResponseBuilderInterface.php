<?php

declare(strict_types=1);

namespace Magma\view;

use Magma\http\Response;

interface HtmlResponseBuilderInterface
{
    /**
     * Renders an HTML template into an HTTP Response.
     * Automatically binds CSRF fields and tokens into the view data.
     *
     * @param string $template Template name or path
     * @param array $data View variables
     * @param string|null $layout Layout wrapper
     * @return Response
     */
    public function render(string $template, array $data = [], ?string $layout = 'default'): Response;
}

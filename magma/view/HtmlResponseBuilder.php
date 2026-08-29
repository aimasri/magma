<?php

declare(strict_types=1);

namespace Magma\view;

use Magma\http\Response;
use Magma\interfaces\ResponseFactoryInterface;
use Magma\security\CsrfManager;

/**
 * Title: HtmlResponseBuilder
 *
 * Purpose:
 * - Implements the HTML response building logic using a specified TemplateEngine
 * - Automatically injects CSRF fields and tokens into the view data for security
 * - Converts the rendered HTML string into an HTTP Response object via a ResponseFactory
 *
 * Why / Why this design:
 * - Facade/Adapter Pattern: Wraps the TemplateEngine and ResponseFactory into a cohesive response generation process
 * - Single Responsibility Principle (SRP): Focuses purely on formatting view data into an HTTP Response
 *
 * Teaching notes:
 * - By automatically injecting CSRF fields, this class ensures that forms rendered through it are protected by default, reducing developer error.
 */
class HtmlResponseBuilder implements HtmlResponseBuilderInterface
{
    /**
     * Initializes the response builder with necessary dependencies.
     *
     * @param TemplateEngine $templateEngine Engine to render views
     * @param CsrfManager $csrfManager Manager to fetch CSRF tokens
     * @param ResponseFactoryInterface $responseFactory Factory to generate Response objects
     */
    public function __construct(
        private TemplateEngine $templateEngine,
        private CsrfManager $csrfManager,
        private ResponseFactoryInterface $responseFactory
    ) {}

    /**
     * Renders a given template and wraps it in an HTTP Response.
     *
     * 1. Injects 'csrfField' and 'csrfToken' into the provided data array.
     * 2. Renders the template using the underlying TemplateEngine.
     * 3. Uses the ResponseFactory to create and return a Response object containing the output.
     *
     * @param string $template Template name or path
     * @param array<string, mixed> $data View variables
     * @param string|null $layout Layout wrapper
     * @return Response
     */
    public function render(string $template, array $data = [], ?string $layout = null): Response
    {
        $data['csrfField'] = $this->csrfManager->csrfField();
        $data['csrfToken'] = $this->csrfManager->getToken();

        $content = $this->templateEngine->render($template, $data, $layout);

        return $this->responseFactory->create($content);
    }
}

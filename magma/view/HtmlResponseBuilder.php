<?php

declare(strict_types=1);

namespace Magma\view;

use Magma\http\Response;
use Magma\interfaces\ResponseFactoryInterface;
use Magma\security\CsrfManager;

class HtmlResponseBuilder implements HtmlResponseBuilderInterface
{
    public function __construct(
        private TemplateEngine $templateEngine,
        private CsrfManager $csrfManager,
        private ResponseFactoryInterface $responseFactory
    ) {}

    public function render(string $template, array $data = [], ?string $layout = 'default'): Response
    {
        $data['csrfField'] = $this->csrfManager->csrfField();
        $data['csrfToken'] = $this->csrfManager->getToken();

        $content = $this->templateEngine->render($template, $data, $layout);

        return $this->responseFactory->create($content);
    }
}

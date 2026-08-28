<?php

declare(strict_types=1);

namespace Magma\error;

use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\validation\ValidationException;
use Magma\view\TemplateEngine;
use Magma\interfaces\JsonErrorPresenterInterface;
use Magma\interfaces\DebugErrorPresenterInterface;

/**
 * Title: Application Error Handler & Content-Negotiated Exception Boundary
 *
 * Purpose:
 * - Captures uncaught `\Throwable` instances at the kernel boundary.
 * - Inspects client content-negotiation headers (`Accept: application/json`, `X-Requested-With: XMLHttpRequest`, or `/api/*` URIs) to dynamically return structured JSON error payloads or styled HTML views.
 * - Prevents partial HTML output buffer leaks and system information disclosure.
 *
 * Why / Why this design:
 * - Content-Negotiated Error Boundary: APIs and SPA clients require predictable JSON payloads during 404s and 500s rather than HTML error pages that crash JSON parsers.
 * - Robust Output Buffer Recovery: Wiping all nested output buffers (`ob_end_clean`) ensures fatal crashes midway through template rendering never bleed corrupted markup.
 *
 * Teaching notes:
 * - Catching `\Throwable` instead of `\Exception` captures PHP 7/8 engine errors (`TypeError`, `DivisionByZeroError`, `ParseError`) ensuring they cannot bypass the error boundary.
 */
class ErrorHandler implements ErrorHandlerInterface
{
    private TemplateEngine $templateEngine;
    private JsonErrorPresenterInterface $jsonPresenter;
    private DebugErrorPresenterInterface $debugPresenter;
    private bool $debug;
    private ?\Magma\container\Container $container;

    public function __construct(
        TemplateEngine $templateEngine, 
        \Magma\config\ConfigInterface $config, 
        JsonErrorPresenterInterface $jsonPresenter,
        DebugErrorPresenterInterface $debugPresenter,
        ?\Magma\container\Container $container = null,
        ?bool $debug = null
    ) {
        $this->templateEngine = $templateEngine;
        $this->jsonPresenter = $jsonPresenter;
        $this->debugPresenter = $debugPresenter;
        $this->container = $container;
        
        if ($debug !== null) {
            $this->debug = $debug;
        } else {
            $appDebug = $config->get('APP_DEBUG');
            $appEnv = $config->get('APP_ENV');
            $this->debug = ($appDebug === 'true' || $appDebug === true || $appDebug === '1' || $appEnv === 'development' || (defined('ENVIRONMENT') && ENVIRONMENT === 'development'));
        }
    }

    /**
     * Renders a specialized HTML error view or hardcoded fallback markup.
     *
     * Execution Flow:
     * 1. Package error status and diagnostic details.
     * 2. Attempt rendering error template via TemplateEngine (e.g., `404.php`, `500.php`).
     * 3. If view rendering fails, fallback to hardcoded secure HTML.
     * 4. Return HTTP Response object.
     *
     * @param int $code
     * @param string $message
     * @param string|null $trace
     * @return Response
     */
    public function renderError(int $code, string $message, ?string $trace = null): Response
    {
        $theme = null;
        if ($this->container && $this->container->has(\Magma\security\TenantContext::class)) {
            try {
                $tenantContext = $this->container->get(\Magma\security\TenantContext::class);
                if ($tenantContext->hasTenantId()) {
                    $tenantRepo = $this->container->get(\Magma\interfaces\cqrs\TenantQueryInterface::class);
                    $tenant = $tenantRepo->find($tenantContext->getTenantId());
                    if ($tenant) {
                        $theme = $tenant->theme_settings;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore errors and fall back to default Magma colors
            }
        }

        $data = [
            'message' => $message,
            'code'    => $code,
            'trace'   => ($this->debug) ? $trace : null,
            'title'   => "Error {$code}",
            'debug'   => $this->debug,
            'theme'   => $theme,
        ];

        try {
            return new Response($this->templateEngine->render((string)$code, $data, null), $code);
        } catch (\Throwable $e) {
            $html = "<!DOCTYPE html><html lang='en'><head><meta charset='utf-8'><title>Error {$code}</title>";
            $html .= "<style>body{font-family:system-ui,-apple-system,sans-serif;padding:2rem;background:#0f172a;color:#f8fafc;}h1{color:#f43f5e;}pre{background:#090d16;color:#f8fafc;padding:1rem;border-radius:0.5rem;overflow-x:auto;}</style></head><body>";
            $html .= "<h1>Error {$code}</h1>";
            $html .= "<p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";

            if ($this->debug) {
                $html .= "<h2 style='margin-top:1.5rem;'>Diagnostics:</h2>";
                $html .= "<p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . " in " . htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8') . "</p>";

                $safeTrace = htmlspecialchars($trace ?? $e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
                $html .= "<h2 style='margin-top:1.5rem;'>Stack Trace:</h2><pre>{$safeTrace}</pre>";
            }

            $html .= "</body></html>";

            return new Response($html, $code, ['Content-Type' => 'text/html; charset=utf-8']);
        }
    }

    /**
     * Renders a 404 Not Found response, content-negotiating between JSON and HTML.
     *
     * @param RequestInterface|null $request
     * @return Response
     */
    public function renderNotFound(?RequestInterface $request = null, ?\Throwable $e = null): Response
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if ($request !== null && ($request->isJsonExpected() || $request->expectsJson())) {
            return $this->jsonPresenter->presentNotFound('The requested endpoint or resource was not found.');
        }

        if ($this->debug) {
            $routes = [];
            if ($e instanceof \Magma\routing\RouteNotFoundException && method_exists($e, 'getAvailableRoutes')) {
                $routes = $e->getAvailableRoutes();
            }
            return $this->debugPresenter->presentNotFound($request, $routes);
        }

        return $this->renderError(404, "Page Not Found");
    }

    /**
     * Handles an uncaught Throwable, logging diagnostics and returning a content-negotiated Response.
     *
     * Execution Flow:
     * 1. Clears all active output buffering layers.
     * 2. Inspects exception type: handles `ValidationException` (422 status).
     * 3. Logs detailed exception message and stack trace to server error logs.
     * 4. Normalizes HTTP status code (400-599, defaulting to 500).
     * 5. If client expects JSON or request is `/api/*`, delegates to `JsonErrorPresenter`.
     * 6. If in debug mode, presents the interactive Developer Debug screen (`DebugErrorPresenter`).
     * 7. Otherwise, renders the production HTML error view.
     *
     * @param \Throwable $e
     * @param RequestInterface|null $request
     * @return Response
     */
    public function handleException(\Throwable $e, ?RequestInterface $request = null): Response
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $isJson = $request !== null && ($request->isJsonExpected() || $request->expectsJson());

        // Handle Uncaught ValidationException
        if ($e instanceof ValidationException) {
            $code = 422;
            $message = "Validation failed for the request.";
            error_log(sprintf(
                "[%s] Unhandled ValidationException: %s in %s:%d\nErrors: %s",
                date('Y-m-d H:i:s'),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                json_encode($e->getErrors(), JSON_UNESCAPED_SLASHES)
            ));

            if ($isJson) {
                return $this->jsonPresenter->presentValidation($e->getErrors(), $message);
            }

            if ($this->debug) {
                return $this->debugPresenter->present($e, $request, $code);
            }

            return $this->renderError($code, $message, $e->getTraceAsString());
        }

        // Log general Throwable
        $logEntry = sprintf(
            "[%s] Exception [%s]: %s in %s:%d\nStack Trace:\n%s",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        error_log($logEntry);

        // Normalize HTTP status code
        $code = $e->getCode();
        if (!is_int($code) || $code < 400 || $code > 599) {
            $code = 500;
        }

        $safeMessage = $this->debug ? $e->getMessage() : 'An unexpected system error occurred.';

        if ($isJson) {
            return $this->jsonPresenter->present($code, $safeMessage, $e, $this->debug);
        }

        if ($this->debug) {
            return $this->debugPresenter->present($e, $request, $code);
        }

        return $this->renderError($code, $safeMessage, $e->getTraceAsString());
    }
}
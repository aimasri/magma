<?php

namespace Magma\error;

use Magma\http\RequestInterface;
use Magma\http\RedirectResponse;
use Magma\http\Response;
use Magma\validation\ValidationException;
use Magma\view\TemplateEngine;

/**
 * ErrorHandler — application-level exception normalization.
 *
 * Purpose:
 * - Capture uncaught exceptions, log diagnostics for developers, and
 *   present a safe, user-friendly HTTP response.
 *
 * Why / Why this design:
 * - Centralizes the application's failure mode. Catching all exceptions at the boundary 
 *   prevents partial HTML rendering and stops internal stack traces from leaking to end users.
 *
 * Teaching notes:
 * - In production, this handler should be expanded to integrate with monitoring 
 *   services (like Sentry or Datadog) to alert developers of critical failures.
 */
class ErrorHandler implements ErrorHandlerInterface
{
    private TemplateEngine $templateEngine;
    private bool $debug;

    /**
     * Initializes the handler with the view engine for rendering error pages.
     * Debug mode is determined by the global ENVIRONMENT constant.
     */
    public function __construct(TemplateEngine $templateEngine, ?bool $debug = null)
    {
        $this->templateEngine = $templateEngine;
        // Automatically enable debug mode if ENVIRONMENT is set to development
        $this->debug = $debug ?? (defined('ENVIRONMENT') && ENVIRONMENT === 'development');
    }

    /**
     * Renders a specialized error view or a raw HTML fallback.
     * 
     * Execution Flow:
     * 1. Package the error data (`code`, `message`, `trace`) into an array.
     * 2. Attempt to render the HTTP status code's specific view (e.g., `404.php`).
     * 3. If rendering fails (e.g., missing template), catch the new exception.
     * 4. Build a secure, hard-coded HTML string as a final fallback.
     * 5. Append stack traces if debug mode is active.
     * 6. Return the constructed `Response`.
     * 
     * Logic behind the logic:
     * - The nested `try/catch` is a critical safety net. If the error system itself 
     *   throws an error while trying to render an error, the application must not crash silently.
     */
    public function renderError(int $code, string $message, ?string $trace = null): Response
    {
        $data = [
            'message' => $message,
            'code'    => $code,
            'trace'   => ($this->debug) ? $trace : null,
            'title'   => "Error {$code}",
        ];

        try {
            return new Response($this->templateEngine->render((string)$code, $data, null), $code);
        } catch (\Throwable $e) {
            $html = "<h1>Error {$code}</h1>";
            $html .= "<p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";

            if ($this->debug) {
                $html .= "<h2>Render Error:</h2>";
                $html .= "<p>" . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . ":" . $e->getLine() . "</p>";
                
                $safeTrace = htmlspecialchars($trace ?? $e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
                $html .= "<h2>Stack Trace:</h2><pre>{$safeTrace}</pre>";
            }

            return new Response($html, $code);
        }
    }

    /**
     * Renders the standard 404 Not Found page.
     * 
     * Execution Flow:
     * 1. Hardcodes the 404 HTTP status code.
     * 2. Delegates to the core `renderError` method to handle template rendering or fallback HTML.
     * 
     * Logic behind the logic:
     * - This wrapper method exists so that the `Application` kernel can easily render a 404 
     *   without having to construct error messages manually. It encapsulates the specific 
     *   "Not Found" message standard.
     */
    public function renderNotFound(): Response
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        return $this->renderError(404, "Page Not Found");
    }

    /**
     * Handle an exception by logging it and returning a safe response.
     * 
     * Execution Flow:
     * 1. Clears any active output buffers to prevent partial view rendering.
     * 2. Categorizes the exception (e.g., Validation vs System Error).
     * 3. Logs the full stack trace to the server logs for developers.
     * 4. Normalizes the HTTP status code (enforcing valid 4xx or 5xx codes).
     * 5. Delegates to `renderError` to construct the final response.
     * 
     * Logic behind the logic:
     * - Clearing output buffers first is essential; without it, users might see a half-rendered HTML 
     *   page with an error appended to the bottom, which breaks CSS/JS and exposes system state.
     *
     * @param \Throwable $e The thrown exception.
     * @param RequestInterface|null $request The incoming HTTP request if resolved.
     * @return Response
     */
    public function handleException(\Throwable $e, ?RequestInterface $request = null): Response
    { 
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Validation exceptions are typically caught and handled within 
        // Controller logic to trigger redirects. If one reaches this handler, 
        // it is treated as an unhandled application error.
        if ($e instanceof ValidationException) {
            $code = 422; // Unprocessable Entity
            $message = "Validation failed for the request.";
            error_log(sprintf(
                "[%s] Unhandled ValidationException: %s in %s:%d\nErrors: %s",
                date('Y-m-d H:i:s'), $e->getMessage(), $e->getFile(), $e->getLine(), json_encode($e->getErrors())
            ));
            return $this->renderError($code, $message, $e->getTraceAsString());
        }

        $logEntry = sprintf(
            "[%s] Exception: %s in %s:%d\nStack Trace:\n%s",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        error_log($logEntry);

        // Determine the HTTP status code. 
        // We only use the exception code if it's a valid client (4xx) or server (5xx) error.
        $code = $e->getCode();
        if (!is_int($code) || $code < 400 || $code > 599) {
            $code = 500;
        }

        $safeMessage = $this->debug ? $e->getMessage() : 'An unexpected system error occurred.';

        return $this->renderError($code, $safeMessage, $e->getTraceAsString());
    }
}
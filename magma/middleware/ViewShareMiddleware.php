<?php

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;
use Magma\view\TemplateEngine;
use Magma\models\VendorQueryInterface;

/**
 * ViewShareMiddleware — injects global layout variables.
 *
 * Purpose:
 * - Automatically inject `$vendor`, `$tagline`, `$user`, CSRF tokens, and flash 
 *   variables (`errors`, `old` input) into the global template context on every request.
 * 
 * Why / Why this design:
 * - Implements the View Composer pattern. By intercepting the request pipeline, 
 *   we offload presentation data gathering from the controllers. This ensures 
 *   that controllers remain strictly focused on processing HTTP business logic.
 * 
 * Teaching notes:
 * - This approach is exceptionally common in mature frameworks. It prevents 
 *   redundant repository calls and parameter passing across multiple endpoints.
 */
class ViewShareMiddleware implements MiddlewareInterface
{
    private TemplateEngine $templateEngine;
    private VendorQueryInterface $vendorRepository;

    public function __construct(TemplateEngine $templateEngine, VendorQueryInterface $vendorRepository)
    {
        $this->templateEngine = $templateEngine;
        $this->vendorRepository = $vendorRepository;
    }

    /**
     * Executes the middleware layer.
     * 
     * Execution Flow:
     * 1. Retrieve the primary vendor from the repository.
     * 2. Share the vendor array and its tagline globally with the TemplateEngine.
     * 3. Share the current authenticated user session globally.
     * 4. Inject the CSRF token and flash session variables (`errors`, `old`) into the TemplateEngine.
     * 5. Pass the request to the next middleware or controller.
     * 
     * Logic behind the logic:
     * - By calling `share()`, these variables become implicitly available to every 
     *   `render()` or `partial()` call, eliminating boilerplate in the controllers.
     */
    public function process(Request $request, callable $next): Response
    {
        $vendor = $this->vendorRepository->getPrimaryVendor();

        $this->templateEngine->share('vendor', $vendor);
        $this->templateEngine->share('tagline', $vendor ? $vendor->tagline : '');
        $this->templateEngine->share('user', $request->session('user'));

        // Inject flash data prior to rendering

        $errors = $request->session('errors', []);
        $request->setSession('errors', null);
        $this->templateEngine->share('errors', $errors);

        $old = $request->session('old', []);
        $request->setSession('old', null);
        $this->templateEngine->share('old', $old);

        return $next($request);
    }
}

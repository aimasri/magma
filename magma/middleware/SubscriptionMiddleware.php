<?php

namespace Magma\middleware;

use Magma\http\RequestInterface;
use Magma\http\Response;

/**
 * Title: Subscription Feature Gating Middleware
 *
 * Purpose:
 * - Abstracts tenant feature flags and tier checks into standard HTTP pipeline guards.
 * - Validates subscription plans before routing the request to the controllers.
 *
 * Why / Why this design:
 * - Decouples conditional access checks from the core Business Logic layers.
 * - Allows plug-and-play gating mechanisms for enterprise features without modifying controllers.
 *
 * Teaching notes:
 * - In enterprise architectures, this is comparable to a Policy Enforcement Point (PEP).
 * - Consider expanding this to utilize an external authorization service (like OPA) for highly scaled applications.
 */
class SubscriptionMiddleware implements MiddlewareInterface
{
    private array $requiredFeatures;

    /**
     * @param array $requiredFeatures Features required to access the route.
     */
    public function __construct(array $requiredFeatures = [])
    {
        $this->requiredFeatures = $requiredFeatures;
    }

    /**
     * Executes the middleware intercept logic for feature gating.
     *
     * 1. Extracts the current tenant context from the incoming HTTP request.
     * 2. Iterates over all required features needed for the requested route.
     * 3. Validates the tenant's active subscription limits against the requested features.
     * 4. Aborts with a 402 Payment Required response if constraints are unmet, or proceeds to the next middleware.
     *
     * Logic behind the logic:
     * - The use of a loop across required features ensures explicit, strict authorization.
     *   Failing fast on the first missing feature avoids unnecessary database or external API calls for subsequent checks.
     *
     * @param RequestInterface $request
     * @param callable $next
     * @return Response
     */
    public function process(RequestInterface $request, callable $next): Response
    {
        throw new \RuntimeException("Subscription gating is not yet implemented.");
    }
}

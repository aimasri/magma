<?php

namespace Magma\middleware;

use Magma\http\RequestInterface;
use Magma\http\Response;

/**
 * Subscription Feature Gating Middleware
 *
 * Purpose:
 * - Abstracts tenant feature flags and tier checks into standard HTTP pipeline guards.
 *
 * Why / Why this design:
 * - Decouples conditional access checks from the core Business Logic layers. 
 *   Allows plug-and-play gating mechanisms for enterprise features without modifying controllers.
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

    public function process(RequestInterface $request, callable $next): Response
    {
        // Example implementation placeholder
        // In a real application, you would resolve the Tenant or User from the Request
        // and check if their active subscription plan includes $this->requiredFeatures.

        /*
        $tenant = $request->getAttribute('tenant');
        foreach ($this->requiredFeatures as $feature) {
            if (!$tenant->hasFeature($feature)) {
                return new Response('Payment Required: Please upgrade your subscription to access this feature.', 402);
            }
        }
        */

        return $next($request);
    }
}

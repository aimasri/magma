<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\middleware\TenantSecurityMiddleware;
use Magma\security\TenantContext;
use Magma\security\TenantContextProviderInterface;

class TenantSecurityMiddlewareTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // We will provide a custom provider that returns tenant ID 42
        $provider = new class implements TenantContextProviderInterface {
            public function resolveTenantId(RequestInterface $request): ?int
            {
                return 42;
            }

            public function resolveVenueId(RequestInterface $request): ?int
            {
                return 10;
            }
        };

        // Register the middleware with our custom provider
        $tenantContext = $this->container->get(TenantContext::class);
        $this->container->set(TenantSecurityMiddleware::class, function() use ($tenantContext, $provider) {
            return new TenantSecurityMiddleware($tenantContext, null, $provider);
        });
    }

    public function testMiddlewareBindsTenantToRequestAndContext(): void
    {
        $this->addRoute('GET', '/test-tenant', function (RequestInterface $request) {
            $tenantId = $request->getAttribute('tenant_id');
            $venueId = $request->getAttribute('venue_id');
            
            return new Response(json_encode([
                'tenant_id' => $tenantId,
                'venue_id' => $venueId
            ]), 200, ['Content-Type' => 'application/json']);
        }, [TenantSecurityMiddleware::class]);

        $response = $this->get('/test-tenant');
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode($response->getContent(), true);
        $this->assertEquals(42, $body['tenant_id']);
        $this->assertEquals(10, $body['venue_id']);
        
        // Also verify the global TenantContext was updated
        $tenantContext = $this->container->get(TenantContext::class);
        $this->assertEquals(42, $tenantContext->getTenantId());
        $this->assertEquals(10, $tenantContext->getVenueId());
    }
}

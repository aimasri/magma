<?php

declare(strict_types=1);

namespace Magma\http;

use Magma\interfaces\ResponseFactoryInterface;

/**
 * Title: HTTP Response Factory
 *
 * Purpose:
 * - Provides a mechanism for instantiating new HTTP Response objects.
 * - Conforms to the ResponseFactoryInterface.
 *
 * Why / Why this design:
 * - Factory Pattern: Decouples the creation of Response objects from the components that need them. This is especially useful in routing and middleware where a default response needs to be generated.
 * - Dependency Inversion Principle (DIP): By programming to an interface, it allows easy swapping of the underlying response implementation (e.g., for testing or extending functionality).
 *
 * Teaching notes:
 * - While it currently just returns a standard `Response`, encapsulating instantiation allows future flexibility (like injecting default headers or tracking response creation).
 */
class ResponseFactory implements ResponseFactoryInterface
{
    /**
     * @param string $content
     * @param int $status
     * @param array<string, string> $headers
     * @return Response
     */
    public function create(string $content = '', int $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers);
    }
}

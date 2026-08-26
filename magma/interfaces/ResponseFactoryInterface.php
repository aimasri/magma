<?php

declare(strict_types=1);

namespace Magma\interfaces;

use Magma\http\Response;

/**
 * Title: Response Factory Interface
 *
 * Purpose:
 * - Defines the contract for creating HTTP Response objects
 * - Responsible for standardizing response creation across different parts of the framework
 *
 * Why / Why this design:
 * - Factory Pattern
 * - Decouples controllers and middleware from the specific Response implementation
 *
 * Teaching notes:
 * - Useful for swapping out HTTP implementations (e.g. moving to PSR-7) without changing consumer logic
 */
interface ResponseFactoryInterface
{
    /**
     * Creates a new HTTP Response object.
     *
     * 1. Instantiates a new Response with the given content, status, and headers.
     * 2. Provides a consistent boundary for response generation.
     *
     * @param string $content
     * @param int $status
     * @param array<string, string> $headers
     * @return Response
     */
    public function create(string $content = '', int $status = 200, array $headers = []): Response;
}

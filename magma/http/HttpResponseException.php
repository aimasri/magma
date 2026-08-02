<?php

namespace Magma\http;

/**
 * Title: HTTP Response Exception
 *
 * Purpose:
 * - Allows deep application layers (like BaseController validation) to cleanly halt execution.
 * - Immediately returns an HTTP response (like a Redirect) without calling `exit()`, ensuring the middleware pipeline is not bypassed.
 *
 * Why this design:
 * - Employs Exception-Driven Control Flow. This prevents the need to pass return values backwards through multiple abstraction layers when a critical termination (like a validation failure) occurs.
 *
 * Teaching notes:
 * - This exception acts as a transport container. Catching it in the Router allows the response to bubble backwards naturally through all middleware.
 */
class HttpResponseException extends \RuntimeException
{
    private Response $response;

    public function __construct(Response $response)
    {
        parent::__construct("HTTP Response Exception");
        $this->response = $response;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}

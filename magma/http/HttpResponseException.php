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

    /**
     * Constructs an HTTP Response Exception containing a pre-configured Response object.
     *
     * Logic behind the logic:
     * - Binds the prepared HTTP Response directly to the exception payload, circumventing standard execution flow while preserving proper middleware traversal.
     */
    public function __construct(Response $response)
    {
        parent::__construct("HTTP Response Exception");
        $this->response = $response;
    }

    /**
     * Retrieves the embedded HTTP Response.
     *
     * Logic behind the logic:
     * - Allows the Router's exception catcher to safely unwrap and emit the aborted response.
     */
    public function getResponse(): Response
    {
        return $this->response;
    }
}

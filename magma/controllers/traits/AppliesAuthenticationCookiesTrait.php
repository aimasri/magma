<?php

namespace Magma\controllers\traits;

use Magma\http\Response;
use Magma\http\Request;
use Magma\services\AuthenticationResult;

trait AppliesAuthenticationCookiesTrait
{
    /**
     * Applies cookies from the authentication result to the HTTP response.
     * 1. Sets new cookies required by the result.
     * 2. Clears expired or removed cookies.
     */
    private function applyAuthResult(AuthenticationResult $result, Response $response, Request $request): Response
    {
        foreach ($result->getCookiesToSet() as $cookie) {
            $response->withCookie($cookie['name'], $cookie['value'], $cookie['expiry'], "/", "", $request->isSecure(), true);
        }
        foreach ($result->getCookiesToClear() as $name) {
            $response->withCookie($name, '', time() - 3600, "/", "", $request->isSecure(), true);
        }
        return $response;
    }
}

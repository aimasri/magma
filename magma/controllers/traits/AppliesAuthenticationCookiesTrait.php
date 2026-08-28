<?php

namespace Magma\controllers\traits;

use Magma\http\Response;
use Magma\http\Request;
use Magma\services\AuthenticationResult;

/**
 * Title: Applies Authentication Cookies Trait
 *
 * Purpose:
 * - Coordinates the extraction of authentication cookies from an AuthenticationResult and applies them to an HTTP Response.
 *
 * Why this design:
 * - Uses a Trait (horizontal reuse) because multiple unrelated controllers (e.g., LoginController, RegistrationController) need this exact functionality to finalize authentication states. It keeps the logic DRY without forcing a deep class inheritance hierarchy.
 *
 * Teaching notes:
 * - Traits are ideal for cross-cutting controller utilities that manipulate HTTP responses, but should be kept small and focused.
 */
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

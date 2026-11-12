<?php

declare(strict_types=1);

namespace Magma\security;

use Magma\contracts\RolePolicyInterface;
use Magma\domain\AuthUser;
use Magma\http\RedirectResponse;
use Magma\http\RequestInterface;
use Magma\http\SessionInterface;
use Magma\middleware\AuthMiddleware;

/**
 * Title: Post-Login Redirect Resolver
 *
 * Purpose:
 * - Resolves safe, validated post-authentication redirection destinations.
 * - Extracts redirect decision logic and HTTP session flash management out of authentication controllers into a single-responsibility security service.
 *
 * Why / Why this design:
 * - Single Responsibility Principle (SRP): Isolates post-authentication URL resolution and redirect response construction from transport controller mechanics.
 * - Defense-in-Depth Open-Redirect Protection: Validates candidate URIs through RFC 3986 `RedirectSanitizer` before returning any redirect.
 * - Strict Dependency Ceiling: Requires exactly 2 dependencies (`SessionInterface` and `RolePolicyInterface`), well within Magma's 3-dependency limit.
 *
 * Teaching notes:
 * - Priority order for post-login destination:
 *   1. Explicit POST body `redirect` parameter (form override).
 *   2. Query string `redirect` or `return_to` parameter.
 *   3. Session-stored `intended_url` captured during unauthenticated deep-link interception (consumed on read).
 *   4. Role dashboard fallback resolved via `RolePolicyInterface`.
 */
class PostLoginRedirectResolver
{
    /**
     * Initializes the resolver with required session and role policy dependencies.
     *
     * @param SessionInterface $session Application session interface for deep-link and flash management.
     * @param RolePolicyInterface $rolePolicy Policy resolving role-specific landing paths.
     */
    public function __construct(
        private readonly SessionInterface $session,
        private readonly RolePolicyInterface $rolePolicy
    ) {}

    /**
     * Resolves the sanitized destination URI following successful authentication.
     *
     * Execution Flow:
     * 1. Inspects explicit POST body `redirect` parameter.
     * 2. If absent, inspects query string parameters `redirect` and `return_to`.
     * 3. Inspects and consumes session-stored `intended_url` (clearing it to prevent stale loops).
     * 4. Sanitizes candidate URL via `RedirectSanitizer::sanitize()`. If valid and non-null, returns the sanitized URI.
     * 5. Otherwise resolves fallback path via `$this->rolePolicy->resolveDashboardPath($user?->getRole())`, sanitizes via `RedirectSanitizer`, and returns it.
     *
     * Logic behind the logic:
     * - Consuming `intended_url` immediately upon read prevents subsequent logins from reusing stale deep-links, ensuring users land on their intended target once and subsequently on their normal dashboard.
     *
     * @param AuthUser|null $user The authenticated user entity, or null if resolving unauthenticated destination.
     * @param RequestInterface $request Incoming HTTP request.
     * @return string Validated, path-absolute destination URI.
     */
    public function resolveDestination(?AuthUser $user, RequestInterface $request): string
    {
        // 1. Inspect POST body parameter
        $postRedirect = $request->request('redirect');
        $candidate = is_string($postRedirect) && trim($postRedirect) !== '' ? trim($postRedirect) : null;

        // 2. Inspect Query string parameter (redirect or return_to)
        if ($candidate === null) {
            $queryRedirect = $request->query('redirect', $request->query('return_to'));
            $candidate = is_string($queryRedirect) && trim($queryRedirect) !== '' ? trim($queryRedirect) : null;
        }

        // 3. Inspect and consume session-captured deep link
        $sessionIntended = $this->session->get(AuthMiddleware::INTENDED_URL_SESSION_KEY);
        if ($this->session->has(AuthMiddleware::INTENDED_URL_SESSION_KEY)) {
            $this->session->remove(AuthMiddleware::INTENDED_URL_SESSION_KEY);
        }

        if ($candidate === null && is_string($sessionIntended) && trim($sessionIntended) !== '') {
            $candidate = trim($sessionIntended);
        }

        // 4. Sanitize candidate target with RFC 3986 validation
        $sanitizedUrl = RedirectSanitizer::sanitize($candidate);
        if ($sanitizedUrl !== null) {
            return $sanitizedUrl;
        }

        // 5. Fall back to role-configured dashboard path
        $role = $user?->getRole();
        $fallback = $this->rolePolicy->resolveDashboardPath($role);

        return RedirectSanitizer::sanitize($fallback) ?? '/';
    }

    /**
     * Constructs a RedirectResponse to the post-authentication destination.
     *
     * Execution Flow:
     * 1. Resolves destination via `resolveDestination($user, $request)`.
     * 2. Wraps destination in a standard HTTP RedirectResponse.
     *
     * @param AuthUser|null $user The authenticated user entity.
     * @param RequestInterface $request Incoming HTTP request.
     * @return RedirectResponse HTTP redirect response to the sanitized destination.
     */
    public function redirectAfterAuthentication(?AuthUser $user, RequestInterface $request): RedirectResponse
    {
        return new RedirectResponse($this->resolveDestination($user, $request));
    }

    /**
     * Constructs a RedirectResponse for a failed login attempt, flashing old input and errors.
     *
     * Execution Flow:
     * 1. Flashes old email to session storage for form re-population.
     * 2. Flashes auth error message to session storage.
     * 3. Preserves any existing `intended_url` in session so the user's deep link remains intact across retries.
     * 4. Returns a RedirectResponse back to `/login`.
     *
     * Logic behind the logic:
     * - Preserving session `intended_url` on failed attempts allows users to mistype credentials without losing their original target destination.
     *
     * @param string $email The submitted email address to flash.
     * @param string $message The failure error message (defaults to 'Invalid credentials').
     * @return RedirectResponse HTTP redirect back to the login form.
     */
    public function redirectAfterFailedLogin(string $email, string $message = 'Invalid credentials'): RedirectResponse
    {
        $this->session->set('old', ['email' => $email]);
        $this->session->set('errors', ['auth' => $message]);

        return new RedirectResponse('/login');
    }
}

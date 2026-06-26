<?php

namespace Magma\services;

use Magma\models\UserRepositoryInterface;
use Magma\http\Session;
use Magma\services\RememberMeService;
use Magma\services\AuthenticationResult;

/**
 * User Identity & Session Service
 *
 * Purpose:
 * - Manage primary authentication (verifying email/password pairs).
 * - Control the server-side session lifecycle for user state.
 *
 * Why / Why this design:
 * - By separating authentication logic into a dedicated Service class, we keep the Controller 
 *   layer thin and strictly focused on HTTP transport. This allows the same authentication logic 
 *   to be reused for web logins, API token issuance, or automated testing.
 *
 * Teaching notes:
 * - Storing the user's ID and Role in the session array allows authorization middleware to 
 *   function securely without needing to query the database on every single HTTP request.
 */
class AuthenticationService
{
    protected UserRepositoryInterface $userRepository;
    protected Session $session;
    protected RememberMeService $rememberMeService;

    public function __construct(
        UserRepositoryInterface $userRepository, 
        Session $session,
        RememberMeService $rememberMeService
    ) {
        $this->userRepository = $userRepository;
        $this->session = $session;
        $this->rememberMeService = $rememberMeService;
    }

    /**
     * Validates user credentials.
     * 
     * Execution Flow:
     * 1. Query the UserRepositoryInterface using `findForAuth` to securely retrieve the user record including the password hash.
     * 2. If no user is found, immediately return null.
     * 3. If a user is found, verify the plain-text password against the stored bcrypt hash.
     * 4. Call `login()` to establish the active session.
     * 5. Generate persistent tokens if `$remember` is true.
     * 6. Return an `AuthenticationResult` payload.
     * 
     * Logic behind the logic:
     * - We use `password_verify()` because PHP's native password hashing API automatically 
     *   handles extracting the salt from the stored hash and mitigating timing attacks.
     * - Returning a Result object decouples the domain from HTTP mechanisms.
     */
    public function attempt(string $email, string $password, bool $remember = false): AuthenticationResult
    {
        $user = $this->userRepository->findForAuth($email);

        if (!$user || !password_verify($password, $user['password'])) {
            return AuthenticationResult::failure();
        }

        $this->login($user);
        $result = AuthenticationResult::success($user);

        if ($remember) {
            $tokenData = $this->rememberMeService->generateToken($user['id']);
            $result->withCookie('remember_user', $tokenData['token'], $tokenData['expiry']);
        }

        return $result;
    }

    /**
     * Attempts to automatically log the user in using a persistent token.
     * 
     * Execution Flow:
     * 1. Validate the token and fetch the corresponding user.
     * 2. Log the user in to the active session.
     * 3. Securely rotate the token to prevent replay attacks.
     * 4. Return an AuthenticationResult with the new cookie attached.
     * 
     * @param string $token
     * @return AuthenticationResult
     */
    public function attemptAutoLogin(string $token): AuthenticationResult
    {
        $userId = $this->rememberMeService->validateToken($token);
        if (!$userId) {
            return AuthenticationResult::failure()->clearCookie('remember_user');
        }

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return AuthenticationResult::failure()->clearCookie('remember_user');
        }

        $this->login($user);

        $tokenData = $this->rememberMeService->rotateToken($token, $userId);
        
        return AuthenticationResult::success($user)
            ->withCookie('remember_user', $tokenData['token'], $tokenData['expiry']);
    }

    /**
     * Initializes the user session upon successful authentication.
     * 
     * Execution Flow:
     * 1. Instantiate the AuthUser domain entity to safely map the raw database array.
     * 2. Regenerate the session ID immediately upon login.
     * 3. Store non-sensitive user metadata into the session array via the entity.
     * 
     * Logic behind the logic:
     * - Regenerating the session ID prevents Session Fixation attacks by ensuring attackers 
     *   cannot hijack the newly authenticated state using a known, pre-seeded cookie.
     * - By storing the user's role and ID in the session, we avoid redundant database 
     *   lookups on subsequent requests.
     */
    public function login(array $userData): void
    {
        $authUser = new \Magma\domain\AuthUser($userData);

        // Regenerate the session ID to prevent Session Fixation attacks.
        // This invalidates the pre-login session ID, ensuring attackers cannot hijack 
        // the newly authenticated state using a known, pre-seeded cookie.
        $this->session->regenerate(true);

        $this->session->set('user', $authUser->toSessionArray());
    }

    /**
     * Destroys the active session and invalidates any persistent token.
     * 
     * @param string|null $token Optional persistent token to invalidate.
     * @return AuthenticationResult
     */
    public function logout(?string $token = null): AuthenticationResult
    {
        if ($token) {
            $this->rememberMeService->invalidateToken($token);
        }
        
        $this->session->destroy();

        return AuthenticationResult::success([])->clearCookie('remember_user');
    }
}
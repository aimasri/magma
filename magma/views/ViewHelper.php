<?php

declare(strict_types=1);

namespace Magma\views;

use Magma\assets\AssetVersionManager;

/**
 * Title: Declarative View Helper Facade
 *
 * Purpose:
 * - Provides static convenience methods for templates: cache-busted asset URLs, CSRF inputs,
 *   REST method spoofing, HTML escaping, and session flash data retrieval.
 *
 * Why / Why this design:
 * - Static Facade for Views: Allows clean, readable syntax in PHP templates (e.g. `<?= ViewHelper::asset('/css/app.css') ?>`)
 *   without requiring global variable pollution or complex dependency chains inside view files.
 * - Single Point of Truth: Centralizes security escaping and HTML token generation.
 *
 * Teaching notes:
 * - Call `ViewHelper::asset()` for all `<link>` and `<script>` elements.
 * - Call `ViewHelper::csrf()` inside all `<form method="POST">` elements.
 */
class ViewHelper
{
    /** @var AssetVersionManager|null Injected asset version manager instance. */
    private static ?AssetVersionManager $assetManager = null;

    /** @var string|null Globally registered CSRF token string. */
    private static ?string $csrfToken = null;

    /** @var array<string, mixed> Session flashed old input cache. */
    private static array $oldInput = [];

    /** @var array<string, string> Session flashed error messages cache. */
    private static array $errors = [];

    /**
     * Injects the AssetVersionManager instance.
     *
     * @param AssetVersionManager $manager
     * @return void
     */
    public static function setAssetManager(AssetVersionManager $manager): void
    {
        self::$assetManager = $manager;
    }

    /**
     * Sets the active CSRF token for view helper generation.
     *
     * @param string $token
     * @return void
     */
    public static function setCsrfToken(string $token): void
    {
        self::$csrfToken = $token;
    }

    /**
     * Sets the flashed session state (old inputs and validation errors).
     *
     * @param array<string, mixed> $oldInput
     * @param array<string, string> $errors
     * @return void
     */
    public static function setFlashState(array $oldInput = [], array $errors = []): void
    {
        self::$oldInput = $oldInput;
        self::$errors = $errors;
    }

    /**
     * Resolves a static asset path into a cache-busted URL.
     *
     * @param string $path Relative asset path (e.g. '/css/app.css').
     * @return string Cache-busted URL string.
     */
    public static function asset(string $path): string
    {
        $manager = self::$assetManager ?? AssetVersionManager::getInstance();
        return $manager->getAssetUrl($path);
    }

    /**
     * Generates a hidden HTML `<input type="hidden" name="csrf_token" value="...">` element.
     *
     * @param string|null $token Optional explicit token (falls back to registered token or session).
     * @return string HTML markup string.
     */
    public static function csrf(?string $token = null): string
    {
        $resolvedToken = $token ?? self::$csrfToken;
        
        if (empty($resolvedToken) && isset($_SESSION['csrf_token'])) {
            $resolvedToken = (string) $_SESSION['csrf_token'];
        }

        if (empty($resolvedToken)) {
            return '';
        }

        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($resolvedToken, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Retrieves the raw CSRF token string.
     *
     * @return string
     */
    public static function csrfToken(): string
    {
        if (!empty(self::$csrfToken)) {
            return self::$csrfToken;
        }

        if (isset($_SESSION['csrf_token'])) {
            return (string) $_SESSION['csrf_token'];
        }

        return '';
    }

    /**
     * Generates a hidden HTML `<input type="hidden" name="_method" value="...">` element for REST verbs.
     *
     * @param string $httpMethod HTTP verb (e.g. 'PUT', 'PATCH', 'DELETE').
     * @return string HTML input string.
     */
    public static function method(string $httpMethod): string
    {
        $verb = strtoupper($httpMethod);
        if (in_array($verb, ['GET', 'POST'], true)) {
            return '';
        }

        return '<input type="hidden" name="_method" value="' . htmlspecialchars($verb, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Safely escapes strings for browser output, preventing XSS.
     *
     * @param string|null $value
     * @return string
     */
    public static function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Retrieves repopulated old input value from session flash.
     *
     * @param string $key Field name.
     * @param mixed $default Fallback value.
     * @return mixed
     */
    public static function old(string $key, mixed $default = null): mixed
    {
        if (isset(self::$oldInput[$key])) {
            return self::$oldInput[$key];
        }

        if (isset($_SESSION['_flash']['old'][$key])) {
            return $_SESSION['_flash']['old'][$key];
        }

        return $default;
    }

    /**
     * Checks if a field has an associated validation error.
     *
     * @param string $key Field name.
     * @return bool
     */
    public static function hasError(string $key): bool
    {
        return isset(self::$errors[$key]) || isset($_SESSION['_flash']['errors'][$key]);
    }

    /**
     * Retrieves the validation error message for a specific field.
     *
     * @param string $key Field name.
     * @return string|null
     */
    public static function error(string $key): ?string
    {
        if (isset(self::$errors[$key])) {
            return self::$errors[$key];
        }

        if (isset($_SESSION['_flash']['errors'][$key])) {
            return (string) $_SESSION['_flash']['errors'][$key];
        }

        return null;
    }
}

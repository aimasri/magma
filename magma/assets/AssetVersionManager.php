<?php

declare(strict_types=1);

namespace Magma\assets;

use Magma\config\Config;

/**
 * Title: Deterministic Asset Version Manager
 *
 * Purpose:
 * - Provides deterministic HTTP cache-busting for static CSS, JavaScript, font, and media assets
 *   using either an explicit release version (`APP_ASSET_VERSION`) or an 8-character content hash
 *   computed once per process and cached in memory.
 *
 * Why / Why this design:
 * - HTTP Cache Efficiency: Replaces unstable `time()` query strings so browsers can cache static
 *   assets long-term until file contents actually change.
 * - Minimalism: Avoids speculative bundler manifest ceremony while cleanly normalizing paths that
 *   may already carry legacy `?v=...` suffixes in downstream views.
 *
 * Teaching notes:
 * - Reference static assets via `\Magma\views\ViewHelper::asset('/css/app.css')` inside templates.
 */
class AssetVersionManager
{
    private static ?self $instance = null;

    private string $publicDir;

    private ?string $releaseVersion = null;

    /** @var array<string, string> In-memory cache of resolved asset URLs. */
    private array $urlCache = [];

    /**
     * Initializes the asset version manager.
     *
     * @param string|null $publicDir Public web root directory (defaults to `PUBLIC_DIR` or `www/`).
     * @param string|null $releaseVersion Optional fixed release version override.
     */
    public function __construct(?string $publicDir = null, ?string $releaseVersion = null)
    {
        $defaultDir = defined('PUBLIC_DIR') ? (string) PUBLIC_DIR : dirname(__DIR__, 2) . '/www';
        $this->publicDir = rtrim($publicDir ?? $defaultDir, '/\\');

        if ($releaseVersion !== null) {
            $this->releaseVersion = $releaseVersion;
        } elseif (class_exists(Config::class)) {
            $cfgVersion = Config::get('APP_ASSET_VERSION', Config::get('APP_VERSION', null));
            if (is_scalar($cfgVersion) && (string) $cfgVersion !== '') {
                $this->releaseVersion = (string) $cfgVersion;
            }
        }
    }

    /**
     * Retrieves the shared singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Overrides the shared singleton instance (useful in isolated tests).
     */
    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Updates the base public directory and clears the in-memory URL cache.
     */
    public function setBasePublicDir(string $publicDir): void
    {
        $this->publicDir = rtrim($publicDir, '/\\');
        $this->urlCache = [];
    }

    /**
     * Sets an explicit release version tag and clears the in-memory URL cache.
     */
    public function setReleaseVersion(string $version): void
    {
        $this->releaseVersion = $version;
        $this->urlCache = [];
    }

    /**
     * Resolves a relative public asset path into a deterministic cache-busted URL.
     */
    public function getAssetUrl(string $path): string
    {
        $normalizedInput = '/' . ltrim($path, '/');
        if (isset($this->urlCache[$normalizedInput])) {
            return $this->urlCache[$normalizedInput];
        }

        $parts = explode('?', $normalizedInput, 2);
        $cleanPath = $parts[0];
        $existingQuery = $parts[1] ?? null;

        if ($this->releaseVersion !== null && $this->releaseVersion !== '') {
            $url = $this->appendVersion($cleanPath, $existingQuery, urlencode($this->releaseVersion));
            $this->urlCache[$normalizedInput] = $url;
            return $url;
        }

        $fullPath = $this->publicDir . $cleanPath;
        if (is_file($fullPath)) {
            $hash = substr((string) md5_file($fullPath), 0, 8);
            $url = $this->appendVersion($cleanPath, $existingQuery, $hash);
        } else {
            $url = $normalizedInput;
        }

        $this->urlCache[$normalizedInput] = $url;
        return $url;
    }

    /**
     * Returns the resolved version tag or 8-char content hash for an asset path.
     */
    public function getVersion(string $path): string
    {
        if ($this->releaseVersion !== null && $this->releaseVersion !== '') {
            return $this->releaseVersion;
        }

        [$cleanPath] = explode('?', '/' . ltrim($path, '/'), 2);
        $fullPath = $this->publicDir . $cleanPath;

        if (is_file($fullPath)) {
            return substr((string) md5_file($fullPath), 0, 8);
        }

        return '1.0.0';
    }

    /**
     * Clears the in-memory URL cache.
     */
    public function clearCache(): void
    {
        $this->urlCache = [];
    }

    /**
     * Appends `?v=<version>` cleanly, replacing standalone legacy `?v=...` suffixes while preserving other query args.
     */
    private function appendVersion(string $cleanPath, ?string $existingQuery, string $version): string
    {
        if ($existingQuery === null || $existingQuery === '' || preg_match('/^v=[^&]*$/', $existingQuery) === 1) {
            return "{$cleanPath}?v={$version}";
        }

        return "{$cleanPath}?{$existingQuery}&v={$version}";
    }
}

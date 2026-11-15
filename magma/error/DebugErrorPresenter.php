<?php

declare(strict_types=1);

namespace Magma\error;

use DateTimeImmutable;
use DateTimeZone;
use Magma\container\Container;
use Magma\contracts\ClockInterface;
use Magma\dto\TenantDTO;
use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\interfaces\DebugErrorPresenterInterface;
use Magma\interfaces\cqrs\TenantQueryInterface;
use Magma\routing\Route;
use Magma\security\DomainTenantContextProvider;
use Magma\security\TenantContext;
use Throwable;

/**
 * Title: Magma Framework Developer Debug Error Presenter
 *
 * Purpose:
 * - Prepares structured, sanitized exception diagnostics and 404 route tables when `APP_DEBUG=true`,
 *   delegating HTML markup rendering to the standalone offline template `magma/error/templates/debug_error.php`.
 *
 * Why / Why this design:
 * - Separation of Concerns (SoC): Keeps PHP stack-frame extraction, payload redaction, and genuine
 *   runtime introspection cleanly separated from HTML/CSS/JS presentation markup.
 * - Zero External Dependencies: Renders via an isolated static output buffer without `TemplateEngine`
 *   or external CDNs, guaranteeing safe rendering even during early-boot or view-layer failures.
 *
 * Teaching notes:
 * - Never report fabricated telemetry in developer diagnostics; every metric in `gatherEnvironmentMetrics()`
 *   is inspected directly from the live PHP runtime and active `TenantContext`.
 */
class DebugErrorPresenter implements DebugErrorPresenterInterface
{
    /**
     * Initializes the Debug Error Presenter with an optional dependency container and deterministic clock.
     *
     * @param Container|null $container Nullable container supporting early-boot exception rendering.
     * @param ClockInterface|null $clock Optional deterministic clock for UTC diagnostic timestamps.
     */
    public function __construct(
        private readonly ?Container $container = null,
        private readonly ?ClockInterface $clock = null
    ) {}

    /**
     * Renders an interactive HTML debug page for an unhandled Throwable.
     *
     * @param Throwable $e
     * @param RequestInterface|null $request
     * @param int $statusCode
     * @return Response
     */
    public function present(Throwable $e, ?RequestInterface $request = null, int $statusCode = 500): Response
    {
        $tenant = $this->resolveTenant($request);
        $html = self::renderTemplate([
            'mode'            => 'exception',
            'statusCode'      => $statusCode,
            'exceptionClass'  => get_class($e),
            'message'         => $e->getMessage() !== '' ? $e->getMessage() : '(No message provided)',
            'file'            => $e->getFile(),
            'line'            => $e->getLine(),
            'codeSnippet'     => self::extractCodeSnippet($e->getFile(), $e->getLine()),
            'frames'          => self::formatTraceFrames($e),
            'rawTrace'        => $e->getTraceAsString(),
            'availableRoutes' => [],
            'requestData'     => self::gatherRequestContext($request),
            'environmentData' => $this->gatherEnvironmentMetrics($tenant),
            'activeTenant'    => $tenant,
        ]);

        return new Response($html, $statusCode, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Renders an interactive HTML 404 Route Not Found debug page with the registered route explorer.
     *
     * @param RequestInterface|null $request
     * @param array<int|string, mixed> $availableRoutes
     * @param Throwable|null $e
     * @return Response
     */
    public function presentNotFound(?RequestInterface $request = null, array $availableRoutes = [], ?Throwable $e = null): Response
    {
        $path = $request !== null ? $request->getPath() : '/';
        $tenant = $this->resolveTenant($request);

        $html = self::renderTemplate([
            'mode'            => 'not_found',
            'statusCode'      => 404,
            'exceptionClass'  => $e !== null ? basename(str_replace('\\', '/', get_class($e))) : 'RouteNotFoundException',
            'message'         => $e !== null ? $e->getMessage() : "Route not found for path: {$path}",
            'file'            => $e !== null ? $e->getFile() : 'magma/routing/Router.php',
            'line'            => $e !== null ? $e->getLine() : 0,
            'codeSnippet'     => $e !== null ? self::extractCodeSnippet($e->getFile(), $e->getLine()) : [],
            'frames'          => $e !== null ? self::formatTraceFrames($e) : [],
            'rawTrace'        => $e !== null ? $e->getTraceAsString() : '',
            'availableRoutes' => self::formatRoutesList($availableRoutes),
            'requestData'     => self::gatherRequestContext($request),
            'environmentData' => $this->gatherEnvironmentMetrics($tenant),
            'activeTenant'    => $tenant,
        ]);

        return new Response($html, 404, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Extracts source code lines around a target line from a readable local file.
     *
     * @param string $filePath
     * @param int $targetLine
     * @param int $padding
     * @return array<int, array{line: int, code: string, isTarget: bool}>
     */
    public static function extractCodeSnippet(string $filePath, int $targetLine, int $padding = 8): array
    {
        if ($targetLine <= 0 || !is_file($filePath) || !is_readable($filePath)) {
            return [];
        }

        $lines = @file($filePath);
        if ($lines === false) {
            return [];
        }

        $totalLines = count($lines);
        $startLine = max(1, $targetLine - $padding);
        $endLine = min($totalLines, $targetLine + $padding);

        $snippet = [];
        for ($i = $startLine; $i <= $endLine; $i++) {
            $snippet[] = [
                'line'     => $i,
                'code'     => rtrim($lines[$i - 1] ?? '', "\r\n"),
                'isTarget' => ($i === $targetLine),
            ];
        }

        return $snippet;
    }

    /**
     * Safely resolves the active `TenantDTO` for diagnostic branding without risking secondary crashes.
     */
    private function resolveTenant(?RequestInterface $request): ?TenantDTO
    {
        if ($this->container === null) {
            return null;
        }

        try {
            $tenantContext = $this->container->get(TenantContext::class);
            $tenantId = $tenantContext instanceof TenantContext && $tenantContext->hasTenantId()
                ? $tenantContext->getTenantId()
                : null;

            if ($tenantId === null && $request !== null && $this->container->has(DomainTenantContextProvider::class)) {
                $domainProvider = $this->container->get(DomainTenantContextProvider::class);
                if ($domainProvider instanceof DomainTenantContextProvider) {
                    $tenantId = $domainProvider->resolveTenantId($request);
                }
            }

            if ($tenantId !== null && $this->container->has(TenantQueryInterface::class)) {
                $tenantQuery = $this->container->get(TenantQueryInterface::class);
                if ($tenantQuery instanceof TenantQueryInterface) {
                    return $tenantQuery->find($tenantId);
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Formats stack frames with relative paths, safe argument summaries, and 5-line code previews.
     *
     * @return array<int, array{index: int, file: string, line: int, call: string, snippet: array<int, array{line: int, code: string, isTarget: bool}>, args: array<int|string, string>}>
     */
    private static function formatTraceFrames(Throwable $e): array
    {
        $frames = [];
        foreach ($e->getTrace() as $index => $frame) {
            $file = $frame['file'] ?? '[internal function]';
            $line = $frame['line'] ?? 0;
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $function = $frame['function'];
            $call = $class !== '' ? "{$class}{$type}{$function}()" : "{$function}()";

            $args = [];
            if (!empty($frame['args'])) {
                foreach ($frame['args'] as $argKey => $argVal) {
                    $args[$argKey] = self::formatArgument($argVal);
                }
            }

            $frames[] = [
                'index'   => $index + 1,
                'file'    => self::relativizePath($file),
                'line'    => $line,
                'call'    => $call,
                'snippet' => ($file !== '[internal function]' && $line > 0) ? self::extractCodeSnippet($file, $line, 5) : [],
                'args'    => $args,
            ];
        }

        return $frames;
    }

    /**
     * Converts an argument value into an escaped, bounded summary string.
     */
    private static function formatArgument(mixed $arg): string
    {
        if ($arg === null) {
            return 'null';
        }
        if (is_bool($arg)) {
            return $arg ? 'true' : 'false';
        }
        if (is_int($arg) || is_float($arg)) {
            return (string) $arg;
        }
        if (is_string($arg)) {
            return "'" . htmlspecialchars(mb_strimwidth($arg, 0, 40, '...'), ENT_QUOTES, 'UTF-8') . "'";
        }
        if (is_array($arg)) {
            return 'Array(' . count($arg) . ')';
        }
        if (is_object($arg)) {
            return get_class($arg);
        }
        if (is_resource($arg)) {
            return 'Resource(' . get_resource_type($arg) . ')';
        }

        return gettype($arg);
    }

    /**
     * Gathers HTTP request diagnostics strictly via `RequestInterface` when provided.
     *
     * @return array<string, mixed>
     */
    private static function gatherRequestContext(?RequestInterface $request): array
    {
        if ($request === null) {
            return ['Request' => '(Unavailable — early-boot context)'];
        }

        $query = $request->query();
        $post = $request->request();
        $cookies = $request->cookie();
        $server = $request->server();
        $remoteAddr = $request->server('REMOTE_ADDR', '127.0.0.1');

        $headers = [];
        if (is_array($server)) {
            foreach ($server as $srvKey => $srvVal) {
                if (is_string($srvKey) && (str_starts_with($srvKey, 'HTTP_') || in_array($srvKey, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true))) {
                    $headers[$srvKey] = $srvVal;
                }
            }
        }

        return [
            'Method'       => $request->getMethod(),
            'Path'         => $request->getPath(),
            'Client IP'    => is_scalar($remoteAddr) ? (string) $remoteAddr : '127.0.0.1',
            'User Agent'   => $request->header('User-Agent', 'Unknown') ?? 'Unknown',
            'Query Params' => is_array($query) && $query !== [] ? self::sanitizePayload($query) : '(Empty)',
            'Body Params'  => is_array($post) && $post !== [] ? self::sanitizePayload($post) : '(Empty)',
            'Cookies'      => is_array($cookies) && $cookies !== [] ? self::sanitizePayload($cookies) : '(Empty)',
            'HTTP Headers' => $headers !== [] ? self::sanitizePayload($headers) : '(Empty)',
        ];
    }

    /**
     * Redacts credentials, secrets, CSRF tokens, cookies, and authorization headers recursively.
     *
     * @param array<string|int, mixed> $payload
     * @return array<string|int, mixed>
     */
    private static function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'secret',
            'token',
            'csrf_token',
            '_token',
            'api_key',
            'x_api_key',
            'authorization',
            'cookie',
            'remember_user',
        ];
        $sanitized = [];

        foreach ($payload as $k => $v) {
            $normalizedKey = str_replace('-', '_', strtolower((string) $k));
            if (str_starts_with($normalizedKey, 'http_')) {
                $normalizedKey = substr($normalizedKey, 5);
            }

            if (in_array($normalizedKey, $sensitiveKeys, true)) {
                $sanitized[$k] = '******** [REDACTED]';
            } elseif (is_array($v)) {
                $sanitized[$k] = self::sanitizePayload($v);
            } else {
                $sanitized[$k] = $v;
            }
        }

        return $sanitized;
    }

    /**
     * Collects genuine runtime, memory, extension, OPcache, and tenant metrics without mock placeholders.
     *
     * @return array<string, string>
     */
    private function gatherEnvironmentMetrics(?TenantDTO $tenant): array
    {
        $peakMb = round(memory_get_peak_usage(true) / (1024 * 1024), 2);
        $currMb = round(memory_get_usage(true) / (1024 * 1024), 2);

        $opcacheState = 'Disabled / Not Loaded';
        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            if (is_array($status) && !empty($status['opcache_enabled'])) {
                $opcacheState = 'Enabled';
            }
        }

        $extensions = [];
        foreach (['pdo_pgsql', 'redis', 'mbstring', 'openssl'] as $ext) {
            $extensions[] = $ext . ':' . (extension_loaded($ext) ? 'loaded' : 'missing');
        }

        $nowUtc = $this->resolveUtcNow();

        $metrics = [
            'Framework'         => 'Magma Core',
            'App Environment'   => defined('ENVIRONMENT') ? (string) ENVIRONMENT : (getenv('APP_ENV') ?: 'development'),
            'PHP Version'       => PHP_VERSION . ' (' . PHP_SAPI . ')',
            'Operating System'  => php_uname('s') . ' (' . php_uname('m') . ')',
            'Server Time (UTC)' => $nowUtc->format('Y-m-d H:i:s') . ' UTC',
            'Current Memory'    => "{$currMb} MB",
            'Peak Memory Usage' => "{$peakMb} MB",
            'Memory Limit'      => (string) (ini_get('memory_limit') ?: 'N/A'),
            'Max Execution'     => (string) ini_get('max_execution_time') . 's',
            'OPcache'           => $opcacheState,
            'Core Extensions'   => implode(', ', $extensions),
            'Active Tenant'     => $tenant !== null ? "{$tenant->name} (#{$tenant->id}, status: {$tenant->subscription_status})" : 'Unbound / System Context',
        ];

        return $metrics;
    }

    /**
     * Resolves the current UTC timestamp via `ClockInterface` (with a pre-boot `DateTimeImmutable` fallback).
     */
    private function resolveUtcNow(): DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');
        $clock = $this->clock;

        if ($clock === null && $this->container !== null) {
            try {
                if ($this->container->has(ClockInterface::class)) {
                    $resolved = $this->container->get(ClockInterface::class);
                    if ($resolved instanceof ClockInterface) {
                        $clock = $resolved;
                    }
                }
            } catch (Throwable) {
                $clock = null;
            }
        }

        if ($clock !== null) {
            return $clock->now()->setTimezone($utc);
        }

        return new DateTimeImmutable('now', $utc);
    }

    /**
     * Formats registered `Route` objects or legacy route tuples for the 404 Route Explorer.
     *
     * @param array<int|string, mixed> $routes
     * @return array<int, array{method: string, uri: string, handler: string, name: string, middleware: string}>
     */
    private static function formatRoutesList(array $routes): array
    {
        $formatted = [];
        foreach ($routes as $route) {
            if ($route instanceof Route) {
                $method = $route->getMethod();
                $uri = $route->getUri();
                $handler = $route->getHandler();
                $name = $route->getName() ?? '';
                $rawMiddleware = $route->getMiddleware();
            } elseif (is_array($route)) {
                $method = $route[0] ?? 'GET';
                $uri = $route[1] ?? '/';
                $handler = $route[2] ?? '';
                $rawMiddleware = isset($route[5]) && is_array($route[5]) ? $route[5] : [];
                $name = $route[6] ?? '';
            } else {
                continue;
            }

            $middlewareList = [];
            foreach ($rawMiddleware as $m) {
                if (is_array($m)) {
                    $mClass = is_scalar($m[0] ?? '') ? basename(str_replace('\\', '/', (string) ($m[0] ?? ''))) : 'Middleware';
                    $middlewareList[] = $mClass;
                } elseif (is_string($m)) {
                    $middlewareList[] = basename(str_replace('\\', '/', $m));
                }
            }

            $handlerStr = '';
            if (is_array($handler)) {
                $class = is_object($handler[0] ?? null) ? get_class($handler[0]) : (is_scalar($handler[0] ?? '') ? (string) ($handler[0] ?? '') : '');
                $methodName = is_scalar($handler[1] ?? 'index') ? (string) ($handler[1] ?? 'index') : 'index';
                $handlerStr = "{$class}@{$methodName}";
            } elseif (is_string($handler)) {
                $handlerStr = $handler;
            } elseif ($handler instanceof \Closure) {
                $handlerStr = 'Closure()';
            }

            $formatted[] = [
                'method'     => is_scalar($method) ? strtoupper((string) $method) : 'UNKNOWN',
                'uri'        => is_scalar($uri) ? (string) $uri : '/',
                'handler'    => $handlerStr,
                'name'       => is_scalar($name) ? (string) $name : '',
                'middleware' => $middlewareList !== [] ? implode(', ', $middlewareList) : 'None',
            ];
        }

        return $formatted;
    }

    /**
     * Strips the workspace root prefix from absolute paths to avoid leaking host filesystem layout.
     */
    private static function relativizePath(string $path): string
    {
        $basePath = dirname(__DIR__, 2);
        if ($path !== '[internal function]' && str_starts_with($path, $basePath)) {
            return substr($path, strlen($basePath) + 1);
        }

        return $path;
    }

    /**
     * Renders `templates/debug_error.php` inside an isolated output buffer.
     *
     * @param array<string, mixed> $context
     */
    private static function renderTemplate(array $context): string
    {
        /** @var TenantDTO|null $tenant */
        $tenant = $context['activeTenant'] instanceof TenantDTO ? $context['activeTenant'] : null;

        $appName = $tenant !== null && $tenant->name !== '' ? $tenant->name : (string) (getenv('APP_NAME') ?: 'Magma Core');
        $logoRaw = $tenant?->setting('logo_path', getenv('APP_LOGO_PATH') ?: '');
        $logoPath = is_string($logoRaw) ? $logoRaw : '';

        $palette = [
            'bgCanvas'       => self::sanitizeCssColor($tenant?->setting('diagnostic_bg_color') ?? $tenant?->setting('primary_color') ?? getenv('APP_COLOR_BG_CANVAS'), '#380404'),
            'logoBg'         => self::sanitizeCssColor($tenant?->setting('logo_bg_color') ?? getenv('APP_COLOR_LOGO_BG'), 'transparent'),
            'primary'        => self::sanitizeCssColor($tenant?->setting('primary_color') ?? getenv('APP_COLOR_PRIMARY'), '#622E00'),
            'primaryHover'   => self::sanitizeCssColor(getenv('APP_COLOR_PRIMARY_HOVER'), '#4a2200'),
            'secondary'      => self::sanitizeCssColor($tenant?->setting('secondary_color') ?? getenv('APP_COLOR_SECONDARY'), '#ebb33a'),
            'secondaryLight' => self::sanitizeCssColor($tenant?->setting('secondary_light_color') ?? getenv('APP_COLOR_SECONDARY_LIGHT'), '#f2c86b'),
            'dark'           => self::sanitizeCssColor(getenv('APP_COLOR_DARK'), '#1a1a1a'),
            'textDark'       => self::sanitizeCssColor(getenv('APP_COLOR_TEXT_DARK'), '#333333'),
            'borderSubtle'   => self::sanitizeCssColor(getenv('APP_COLOR_BORDER_SUBTLE'), '#e2e8f0'),
        ];

        $statusCode = isset($context['statusCode']) && is_int($context['statusCode']) ? $context['statusCode'] : 500;
        $badgeText = match ($statusCode) {
            404     => '404 • ROUTE NOT FOUND',
            422     => '422 • VALIDATION EXCEPTION',
            default => "{$statusCode} • INTERNAL SERVER ERROR",
        };

        $mode = isset($context['mode']) && is_string($context['mode']) ? $context['mode'] : 'exception';
        $exceptionClass = isset($context['exceptionClass']) && is_string($context['exceptionClass']) ? $context['exceptionClass'] : 'Throwable';
        $message = isset($context['message']) && is_string($context['message']) ? $context['message'] : '';
        $rawFile = isset($context['file']) && is_string($context['file']) ? $context['file'] : '';
        $displayFile = self::relativizePath($rawFile);
        $line = isset($context['line']) && is_int($context['line']) ? $context['line'] : 0;

        /** @var array<int, array{line: int, code: string, isTarget: bool}> $codeSnippet */
        $codeSnippet = is_array($context['codeSnippet'] ?? null) ? $context['codeSnippet'] : [];
        /** @var array<int, array{index: int, file: string, line: int, call: string, snippet: array<int, array{line: int, code: string, isTarget: bool}>, args: array<int|string, string>}> $frames */
        $frames = is_array($context['frames'] ?? null) ? $context['frames'] : [];
        /** @var array<int, array{method: string, uri: string, handler: string, name: string, middleware: string}> $availableRoutes */
        $availableRoutes = is_array($context['availableRoutes'] ?? null) ? $context['availableRoutes'] : [];
        /** @var array<string, mixed> $requestData */
        $requestData = is_array($context['requestData'] ?? null) ? $context['requestData'] : [];
        /** @var array<string, string> $environmentData */
        $environmentData = is_array($context['environmentData'] ?? null) ? $context['environmentData'] : [];

        $payloadJson = json_encode([
            'rawTrace'        => is_string($context['rawTrace'] ?? null) ? $context['rawTrace'] : '',
            'fullDiagnostics' => [
                'status'      => $statusCode,
                'exception'   => $exceptionClass,
                'message'     => $message,
                'location'    => "{$displayFile}:{$line}",
                'request'     => $requestData,
                'environment' => $environmentData,
            ],
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?: '{"rawTrace":"","fullDiagnostics":{}}';

        $stylesheetPath = __DIR__ . '/assets/debug_error.css';
        $clientScriptPath = __DIR__ . '/assets/debug_error.js';
        $stylesheetCss = is_file($stylesheetPath) && is_readable($stylesheetPath)
            ? (file_get_contents($stylesheetPath) ?: '')
            : '';
        $clientScriptJs = is_file($clientScriptPath) && is_readable($clientScriptPath)
            ? (file_get_contents($clientScriptPath) ?: '')
            : '';

        $templateFile = __DIR__ . '/templates/debug_error.php';
        $render = static function () use (
            $templateFile,
            $mode,
            $statusCode,
            $badgeText,
            $appName,
            $logoPath,
            $palette,
            $exceptionClass,
            $message,
            $displayFile,
            $line,
            $codeSnippet,
            $frames,
            $availableRoutes,
            $requestData,
            $environmentData,
            $stylesheetCss,
            $clientScriptJs,
            $payloadJson
        ): string {
            $level = ob_get_level();
            ob_start();
            try {
                require $templateFile;
                return (string) ob_get_clean();
            } catch (Throwable $renderError) {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
                throw $renderError;
            }
        };

        return $render();
    }

    /**
     * Whitelists safe CSS color tokens (hex, rgb/rgba, hsl/hsla, or `transparent`) to prevent style-block injection.
     */
    private static function sanitizeCssColor(mixed $candidate, string $fallback): string
    {
        if (!is_string($candidate) || $candidate === '') {
            return $fallback;
        }
        $trimmed = trim($candidate);
        if ($trimmed === 'transparent' || preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9,\s.%\/]+\)|hsla?\([0-9,\s.%deg\/]+\))$/', $trimmed) === 1) {
            return $trimmed;
        }

        return $fallback;
    }
}

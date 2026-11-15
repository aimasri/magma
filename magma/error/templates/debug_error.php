<?php

declare(strict_types=1);

/**
 * Purpose:
 * - Standalone, zero-dependency HTML diagnostic template rendered by `Magma\error\DebugErrorPresenter`
 *   when `APP_DEBUG=true` (development mode only).
 * - Displays exception class/message, highlighted source snippet, interactive stack trace frames,
 *   404 registered route explorer, sanitized HTTP request context, and real PHP runtime metrics.
 *
 * Teaching notes:
 * - Executed inside an isolated static closure buffer without relying on `TemplateEngine` or external
 *   CDNs/Google Fonts so diagnostics render offline even if the view engine or network fails.
 * - Tenant branding tokens are injected exclusively through `:root` CSS custom properties.
 *
 * @var string $mode Diagnostic mode ('exception' or 'not_found').
 * @var int $statusCode HTTP status code (e.g. 500, 404, 422).
 * @var string $badgeText Status badge label.
 * @var string $appName Escaped application or active tenant display name.
 * @var string $logoPath Optional tenant/platform logo URL or empty string.
 * @var array<string, string> $palette Validated CSS color variables keyed by token name.
 * @var string $exceptionClass Raw exception class name.
 * @var string $message Raw exception message string.
 * @var string $displayFile Relative file path where the error originated.
 * @var int $line Source line number where the error originated.
 * @var array<int, array{line: int, code: string, isTarget: bool}> $codeSnippet Extracted source lines around the failure.
 * @var array<int, array{index: int, file: string, line: int, call: string, snippet: array<int, array{line: int, code: string, isTarget: bool}>, args: array<int|string, string>}> $frames Structured stack frames.
 * @var array<int, array{method: string, uri: string, handler: string, name: string, middleware: string}> $availableRoutes Registered routes for 404 exploration.
 * @var array<string, mixed> $requestData Sanitized HTTP request context map.
 * @var array<string, string> $environmentData Real PHP runtime and tenant environment metrics.
 * @var string $stylesheetCss Standalone CSS rules loaded from `magma/error/assets/debug_error.css`.
 * @var string $clientScriptJs Standalone ES6 client script loaded from `magma/error/assets/debug_error.js`.
 * @var string $payloadJson JSON-encoded `{rawTrace, fullDiagnostics}` payload embedded for client clipboard actions.
 */
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$renderSnippet = static function (array $snippet) use ($esc): string {
    if ($snippet === []) {
        return '<div class="empty-state-text">(Source code unavailable or file unreadable)</div>';
    }
    $out = '';
    foreach ($snippet as $row) {
        $rowLine = (int) $row['line'];
        $code = $esc((string) $row['code']);
        $targetClass = $row['isTarget'] ? ' target-line' : '';
        $marker = $row['isTarget'] ? '► ' : '';
        $out .= "<div class=\"code-row{$targetClass}\"><span class=\"line-num\">{$marker}{$rowLine}</span><span class=\"code-text\">{$code}</span></div>";
    }
    return $out;
};

$renderProps = static function (array $props) use ($esc): string {
    $out = '<table class="props-table">';
    foreach ($props as $key => $val) {
        $label = $esc((string) $key);
        if (is_array($val)) {
            $json = json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $content = '<pre class="dump-pre">' . $esc($json !== false ? $json : '[]') . '</pre>';
        } else {
            $content = $esc(is_scalar($val) ? (string) $val : '');
        }
        $out .= "<tr><th>{$label}</th><td>{$content}</td></tr>";
    }
    $out .= '</table>';
    return $out;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $statusCode ?> | <?= $esc($exceptionClass) ?> — <?= $esc($appName) ?></title>
    <style><?= $stylesheetCss ?>
        :root {
            --color-primary: <?= $esc($palette['primary']) ?>;
            --color-primary-hover: <?= $esc($palette['primaryHover']) ?>;
            --color-secondary: <?= $esc($palette['secondary']) ?>;
            --color-secondary-light: <?= $esc($palette['secondaryLight']) ?>;
            --color-dark: <?= $esc($palette['dark']) ?>;
            --color-text-dark: <?= $esc($palette['textDark']) ?>;
            --color-bg-canvas: <?= $esc($palette['bgCanvas']) ?>;
            --color-logo-bg: <?= $esc($palette['logoBg']) ?>;
            --color-border-subtle: <?= $esc($palette['borderSubtle']) ?>;
        }
    </style>
</head>
<body>
    <header class="page-header">
        <a href="/" class="header-logo-container" title="Back to Home">
            <?php if ($logoPath !== ''): ?>
                <img src="<?= $esc($logoPath) ?>" alt="<?= $esc($appName) ?> Logo" class="header-logo">
            <?php else: ?>
                <div class="header-logo-wrapper">
                    <span class="header-brand-text"><?= $esc($appName) ?></span>
                </div>
            <?php endif; ?>
        </a>
        <div class="header-status-badge">DEVELOPER DIAGNOSTICS</div>
    </header>

    <div class="container">
        <section class="hero-card">
            <div class="hero-top">
                <span class="status-pill"><?= $esc($badgeText) ?></span>
                <div class="hero-actions">
                    <button type="button" class="btn-action" data-debug-action="copy-json">Copy JSON</button>
                    <button type="button" class="btn-action" data-debug-action="copy-trace">Copy Stack Trace</button>
                    <button type="button" class="btn-action" data-debug-action="reload">Reload</button>
                </div>
            </div>
            <div class="exception-title"><?= $esc($exceptionClass) ?></div>
            <div class="exception-message-box"><?= nl2br($esc($message)) ?></div>
            <div class="location-tag">Triggered in <strong><?= $esc($displayFile) ?></strong> on line <strong><?= $line ?></strong></div>
        </section>

        <?php if ($codeSnippet !== []): ?>
            <div class="code-card">
                <div class="code-card-header">
                    <span><strong>Primary Exception Source Code Context</strong> &mdash; <?= $esc($displayFile) ?></span>
                    <span class="section-pill pill-highlight">Line <?= $line ?></span>
                </div>
                <div class="code-table"><?= $renderSnippet($codeSnippet) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($frames !== []): ?>
            <div class="routes-box">
                <div class="info-card-title">
                    <span>Execution Stack Trace</span>
                    <span class="section-pill pill-dark"><?= count($frames) ?> Frames</span>
                </div>
                <div class="frames-list">
                    <?php foreach ($frames as $frame): ?>
                        <div class="frame-item">
                            <div class="frame-header" data-frame-toggle="<?= (int) $frame['index'] ?>">
                                <div class="frame-call">#<?= (int) $frame['index'] ?> <?= $esc($frame['call']) ?></div>
                                <div class="frame-meta"><?= $esc($frame['file']) ?>:<?= (int) $frame['line'] ?></div>
                            </div>
                            <div class="frame-content" id="frame-content-<?= (int) $frame['index'] ?>">
                                <?php if ($frame['args'] !== []): ?>
                                    <div class="frame-args"><strong>Arguments:</strong> <?= implode(', ', $frame['args']) ?></div>
                                <?php endif; ?>
                                <div class="code-table"><?= $renderSnippet($frame['snippet']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($mode === 'not_found' && $availableRoutes !== []): ?>
            <div class="routes-box">
                <div class="info-card-title">
                    <span>Registered Application Routes Explorer</span>
                    <span class="section-pill pill-dark"><?= count($availableRoutes) ?> Routes</span>
                </div>
                <input type="text" class="routes-search-input" id="routesSearch" placeholder="Filter routes by URI pattern, HTTP method, or controller name...">
                <div class="routes-table-wrap">
                    <table class="routes-table">
                        <thead>
                            <tr><th>Method</th><th>URI Pattern</th><th>Handler / Action</th><th>Route Name</th><th>Middleware</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($availableRoutes as $route): ?>
                                <tr>
                                    <td><span class="method-pill method-<?= $esc(strtolower($route['method'])) ?>"><?= $esc($route['method']) ?></span></td>
                                    <td><strong><?= $esc($route['uri']) ?></strong></td>
                                    <td><?= $esc($route['handler']) ?></td>
                                    <td><?= $esc($route['name'] !== '' ? $route['name'] : '—') ?></td>
                                    <td><?= $esc($route['middleware']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="info-grid">
            <div class="info-card">
                <div class="info-card-title"><span>HTTP Request Context</span></div>
                <?= $renderProps($requestData) ?>
            </div>
            <div class="info-card">
                <div class="info-card-title"><span>Environment &amp; Runtime Metrics</span></div>
                <?= $renderProps($environmentData) ?>
            </div>
        </div>
    </div>

    <footer class="page-footer">Magma Framework Developer Diagnostics</footer>
    <div class="toast" id="toast">Copied to clipboard!</div>

    <script type="application/json" id="magma-debug-payload"><?= $payloadJson ?></script>
    <script><?= $clientScriptJs ?></script>
</body>
</html>

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
 * @var string $rawTraceJson JSON-encoded raw stack trace string for clipboard copy.
 * @var string $fullDiagnosticsJson JSON-encoded structured diagnostic bundle for clipboard copy.
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
    <style>
        :root {
            --color-primary: <?= $esc($palette['primary']) ?>;
            --color-primary-hover: <?= $esc($palette['primaryHover']) ?>;
            --color-secondary: <?= $esc($palette['secondary']) ?>;
            --color-secondary-light: <?= $esc($palette['secondaryLight']) ?>;
            --color-dark: <?= $esc($palette['dark']) ?>;
            --color-text-dark: <?= $esc($palette['textDark']) ?>;
            --color-white: #ffffff;
            --color-bg-canvas: <?= $esc($palette['bgCanvas']) ?>;
            --color-logo-bg: <?= $esc($palette['logoBg']) ?>;
            --color-border-subtle: <?= $esc($palette['borderSubtle']) ?>;
            --font-main: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-heading: Georgia, 'Times New Roman', serif;
            --font-mono: 'JetBrains Mono', 'Fira Code', Menlo, Consolas, Monaco, monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            overflow-x: hidden;
            background-color: var(--color-bg-canvas);
            color: var(--color-text-dark);
            font-family: var(--font-main);
            line-height: 1.6;
        }
        .page-header {
            background: var(--color-secondary);
            border-bottom: 6px solid var(--color-secondary-light);
            padding: 0 24px;
            min-height: 54px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            position: relative;
            z-index: 1000;
        }
        .header-logo-container {
            position: absolute;
            top: 12px;
            left: 24px;
            z-index: 1001;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        .header-logo {
            height: 72px;
            width: auto;
            display: block;
            border-radius: 10px;
            background: var(--color-logo-bg);
            padding: 6px;
        }
        .header-logo-wrapper { display: flex; align-items: center; gap: 0.75rem; }
        .header-brand-text {
            font-family: var(--font-heading);
            font-size: 1.35rem;
            color: var(--color-bg-canvas);
            font-weight: 700;
            letter-spacing: 0.04em;
        }
        .header-status-badge {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            background: var(--color-primary);
            color: #ffffff;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.06em;
        }
        .container {
            max-width: 1280px;
            width: 100%;
            margin: 4.5rem auto 0 auto;
            padding: 0 1.5rem 3rem 1.5rem;
            flex: 1;
        }
        .hero-card, .routes-box, .info-card {
            background: var(--color-white);
            border: 1px solid var(--color-border-subtle);
            border-radius: 12px;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
        }
        .hero-card { border-left: 6px solid var(--color-secondary); }
        .hero-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .status-pill {
            background: var(--color-primary);
            color: #ffffff;
            font-family: var(--font-mono);
            font-size: 0.78rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
        }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 6px;
            font-family: var(--font-main);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            background: #ffffff;
            border: 1px solid #d1d5db;
            color: var(--color-dark);
        }
        .btn-action:hover {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: #ffffff;
        }
        .exception-title {
            font-family: var(--font-heading);
            font-size: clamp(1.3rem, 3vw, 1.8rem);
            font-weight: 700;
            color: var(--color-primary);
            margin-bottom: 0.5rem;
            overflow-wrap: break-word;
        }
        .exception-message-box {
            background: #fff5f5;
            border-left: 4px solid #e05260;
            color: #8b0000;
            padding: 1rem 1.25rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 1.1rem;
        }
        .location-tag {
            font-family: var(--font-mono);
            font-size: 0.85rem;
            color: #555555;
            background: #f4f3ef;
            border: 1px solid var(--color-border-subtle);
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-block;
            word-break: break-all;
        }
        .section-pill {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        .pill-highlight { background: rgba(255, 255, 255, 0.12); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.2); }
        .pill-dark { background: var(--color-bg-canvas); color: #ffffff; }
        .code-card {
            background: #23252b;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #363942;
            margin-bottom: 1.75rem;
        }
        .code-card-header {
            background: #1a1b1f;
            padding: 8px 16px;
            font-size: 0.8rem;
            color: #9aa0ac;
            border-bottom: 1px solid #363942;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .code-table { width: 100%; font-family: var(--font-mono); font-size: 0.86rem; }
        .code-row { display: flex; width: 100%; }
        .code-row.target-line { background-color: rgba(224, 82, 96, 0.25); border-left: 4px solid #e05260; }
        .line-num {
            width: 65px;
            padding: 4px 12px;
            text-align: right;
            color: #6b7280;
            user-select: none;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            flex-shrink: 0;
        }
        .code-row.target-line .line-num { color: #ffffff; font-weight: 700; background: rgba(224, 82, 96, 0.4); }
        .code-text { padding: 4px 16px; white-space: pre; overflow-x: auto; flex: 1; color: #e2e8f0; }
        .frames-list { display: flex; flex-direction: column; gap: 8px; }
        .frame-item { border: 1px solid var(--color-border-subtle); border-radius: 8px; overflow: hidden; }
        .frame-header {
            padding: 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            background: #ffffff;
        }
        .frame-header:hover { background: #faf9f6; }
        .frame-call { font-family: var(--font-mono); font-size: 0.9rem; font-weight: 600; color: var(--color-dark); }
        .frame-meta { font-family: var(--font-mono); font-size: 0.8rem; color: #6b7280; }
        .frame-content { padding: 1rem; border-top: 1px solid var(--color-border-subtle); background: #23252b; display: none; }
        .frame-content.open { display: block; }
        .frame-args { margin-bottom: 0.6rem; font-size: 0.82rem; color: #9aa0ac; font-family: var(--font-mono); }
        .routes-search-input {
            width: 100%;
            padding: 10px 14px;
            margin-bottom: 1rem;
            background: #faf9f6;
            border: 1px solid var(--color-border-subtle);
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .routes-table-wrap { max-height: 460px; overflow-y: auto; border: 1px solid var(--color-border-subtle); border-radius: 8px; }
        .routes-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left; }
        .routes-table th {
            background: #f4f2eb;
            padding: 10px 14px;
            color: #555555;
            font-weight: 700;
            border-bottom: 1px solid var(--color-border-subtle);
            position: sticky;
            top: 0;
        }
        .routes-table td { padding: 9px 14px; border-bottom: 1px solid #f0ece1; font-family: var(--font-mono); }
        .method-pill { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.72rem; font-weight: 700; min-width: 52px; text-align: center; }
        .method-get { background: #e8f3d6; color: #436320; }
        .method-post { background: #e0f2fe; color: #0369a1; }
        .method-put, .method-patch { background: #fef3c7; color: #92400e; }
        .method-delete { background: #fee2e2; color: #991b1b; }
        .info-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 1.75rem; }
        .info-card-title {
            font-family: var(--font-heading);
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--color-primary);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--color-border-subtle);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .props-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
        .props-table tr { border-bottom: 1px solid #f0ede6; }
        .props-table th { text-align: left; padding: 8px 8px 8px 0; color: #666666; width: 26%; font-weight: 600; vertical-align: top; }
        .props-table td { padding: 8px 0; color: var(--color-dark); font-family: var(--font-mono); word-break: break-word; }
        .dump-pre { white-space: pre-wrap; font-size: 0.8rem; color: #1e2024; background: #f4f2eb; padding: 0.5rem; border-radius: 4px; border: 1px solid #e6e2d8; }
        .empty-state-text { padding: 1rem; color: #9ca3af; }
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--color-secondary);
            color: var(--color-dark);
            padding: 0.8rem 1.25rem;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 700;
            z-index: 9999;
            display: none;
        }
        .toast.visible { display: block; }
        .route-row-hidden { display: none; }
        .page-footer {
            background: var(--color-secondary);
            color: var(--color-primary);
            text-align: center;
            padding: 10px;
            font-family: var(--font-mono);
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: auto;
            border-top: 6px solid var(--color-secondary-light);
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
                    <button type="button" class="btn-action" onclick="copyDiagnosticsJson()">Copy JSON</button>
                    <button type="button" class="btn-action" onclick="copyStackTrace()">Copy Stack Trace</button>
                    <button type="button" class="btn-action" onclick="window.location.reload()">Reload</button>
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
                            <div class="frame-header" onclick="toggleFrame(<?= (int) $frame['index'] ?>)">
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
                <input type="text" class="routes-search-input" id="routesSearch" placeholder="Filter routes by URI pattern, HTTP method, or controller name..." onkeyup="filterRoutes()">
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

    <script>
        const rawTrace = <?= $rawTraceJson ?>;
        const fullDiagnostics = <?= $fullDiagnosticsJson ?>;

        function toggleFrame(index) {
            const el = document.getElementById('frame-content-' + index);
            if (el) { el.classList.toggle('open'); }
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.textContent = msg;
                toast.classList.add('visible');
                setTimeout(() => { toast.classList.remove('visible'); }, 2500);
            }
        }

        function copyToClipboard(payload, successMessage) {
            const content = (typeof payload === 'string') ? payload : JSON.stringify(payload, null, 2);
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(content).then(() => showToast(successMessage));
            }
        }

        function copyStackTrace() { copyToClipboard(rawTrace, 'Stack trace copied to clipboard'); }
        function copyDiagnosticsJson() { copyToClipboard(fullDiagnostics, 'Diagnostics JSON copied to clipboard'); }

        function filterRoutes() {
            const input = document.getElementById('routesSearch');
            const q = input ? input.value.toLowerCase() : '';
            document.querySelectorAll('.routes-table tbody tr').forEach((row) => {
                const match = row.innerText.toLowerCase().includes(q);
                row.classList.toggle('route-row-hidden', !match);
            });
        }
    </script>
</body>
</html>

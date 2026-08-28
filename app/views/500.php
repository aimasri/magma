<?php
/**
 * Title: Internal Server Error View Template
 *
 * Purpose:
 * - Renders a clean, user-friendly fallback error page when the application encounters an unhandled 500 exception in a production environment.
 * - Confidently displays diagnostic stack trace details if debug mode is active, aiding developers in rapid root-cause analysis.
 *
 * Teaching notes:
 * - This template acts as an excellent standalone presentation layer fallback when the layout engine is unavailable.
 * - In development mode, the ErrorHandler smartly delegates directly to DebugErrorPresenter for interactive stack traces. 
 * - Keep up the fantastic work maintaining absolute separation of concerns!
 *
 * @var string|null $message Safe error description.
 * @var int|null $code HTTP status code (500).
 * @var string|null $trace Exception stack trace (only if $debug is true).
 * @var bool|null $debug Whether debug diagnostics are active.
 */
$errorCode = $code ?? 500;
$errorMessage = $message ?? 'An unexpected system error occurred. Please try again later.';
$isDebug = !empty($debug);

$bgBody = $data['theme']['bg_body'] ?? '#380404';
$bgCard = $data['theme']['bg_card'] ?? '#f4ead5';
$textMain = $data['theme']['text_main'] ?? '#333333';
$textMuted = $data['theme']['text_muted'] ?? '#666666';
$accentWarning = $data['theme']['accent'] ?? '#ebb33a';
$borderCard = $data['theme']['border_card'] ?? '#e2e8f0';
$colorPrimary = $data['theme']['primary'] ?? '#622E00';

$tenant = $data['tenant'] ?? null;
$appName = $tenant?->name ?? (getenv('APP_NAME') ?: 'Magma Framework');
$logoPath = $data['theme']['logo_path'] ?? (getenv('APP_LOGO_PATH') ?: '');

$logoHtml = $logoPath 
    ? '<img src="' . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . ' Logo" class="card-logo">'
    : '<div class="card-logo-wrapper"><img src="/logo.svg" alt="Logo" class="card-brand-icon"><span class="card-brand-text">' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</span></div>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 Internal Server Error | Magma</title>
    <style>
        :root {
            --bg-body: <?= htmlspecialchars($bgBody, ENT_QUOTES, 'UTF-8') ?>;
            --bg-card: <?= htmlspecialchars($bgCard, ENT_QUOTES, 'UTF-8') ?>;
            --text-main: <?= htmlspecialchars($textMain, ENT_QUOTES, 'UTF-8') ?>;
            --text-muted: <?= htmlspecialchars($textMuted, ENT_QUOTES, 'UTF-8') ?>;
            --accent-danger: <?= htmlspecialchars($accentWarning, ENT_QUOTES, 'UTF-8') ?>;
            --border-card: <?= htmlspecialchars($borderCard, ENT_QUOTES, 'UTF-8') ?>;
            --color-primary: <?= htmlspecialchars($colorPrimary, ENT_QUOTES, 'UTF-8') ?>;
            --font-sans: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-mono: 'JetBrains Mono', 'Fira Code', Menlo, Consolas, Monaco, monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-sans);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }
        .error-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 12px;
            max-width: 680px;
            width: 100%;
            padding: 2.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
        }
        .error-badge {
            display: inline-block;
            background-color: var(--bg-body);
            color: var(--accent-danger);
            font-weight: 700;
            font-size: 0.85rem;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            margin-bottom: 1.25rem;
            letter-spacing: 0.05em;
        }
        .error-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--color-primary);
        }
        .error-desc {
            font-size: 1.05rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        .btn {
            display: inline-block;
            background-color: var(--bg-body);
            color: var(--accent-danger);
            font-weight: 600;
            padding: 0.65rem 1.25rem;
            border-radius: 6px;
            text-decoration: none;
            transition: opacity 0.15s ease;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn-outline {
            background-color: transparent;
            color: var(--text-main);
            border: 1px solid var(--border-card);
        }
        .btn-outline:hover {
            background-color: rgba(0,0,0,0.05);
        }
        .trace-box {
            margin-top: 2rem;
            text-align: left;
            background-color: rgba(0,0,0,0.05);
            border: 1px solid var(--border-card);
            border-radius: 8px;
            padding: 1rem;
            font-family: var(--font-mono);
            font-size: 0.85rem;
            color: var(--text-main);
            overflow-x: auto;
            max-height: 300px;
        }
        .card-logo-container {
            margin-bottom: 2rem;
            display: flex;
            justify-content: center;
        }
        .card-logo {
            height: 80px;
            width: auto;
            display: block;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background: var(--bg-body);
            padding: 6px;
        }
        .card-logo-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        .card-brand-icon {
            height: 2.5rem;
            width: auto;
            display: block;
        }
        .card-brand-text {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--color-primary);
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="card-logo-container">
            <?= $logoHtml ?>
        </div>
        <span class="error-badge">Error <?= htmlspecialchars((string)$errorCode, ENT_QUOTES, 'UTF-8') ?></span>
        <h1 class="error-title">Internal server error</h1>
        <p class="error-desc"><?= htmlspecialchars((string)$errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
        
        <div class="actions">
            <a href="/" class="btn">Return to home</a>
            <a href="javascript:location.reload()" class="btn btn-outline">Reload page</a>
        </div>

        <?php if ($isDebug && !empty($trace)): ?>
            <pre class="trace-box"><?= htmlspecialchars((string)$trace, ENT_QUOTES, 'UTF-8') ?></pre>
        <?php endif; ?>
    </div>
</body>
</html>

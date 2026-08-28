<?php
/**
 * Title: Not Found Error View Template
 *
 * Purpose:
 * - Renders a clean, user-friendly fallback error page when a requested URL or resource is not found (404).
 *
 * Teaching notes:
 * - This template acts as an excellent standalone presentation layer fallback.
 *
 * @var string|null $message Safe error description.
 * @var int|null $code HTTP status code (404).
 * @var string|null $trace Exception stack trace (optional/unused for 404 usually).
 * @var bool|null $debug Whether debug diagnostics are active.
 */
$errorCode = $code ?? 404;
$errorMessage = $message ?? 'The page or resource you are looking for does not exist or has been moved.';

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
$logoBgColor = $data['theme']['logo_bg_color'] ?? (getenv('APP_COLOR_LOGO_BG') ?: 'transparent');

$logoHtml = $logoPath 
    ? '<img src="' . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . ' Logo" class="card-logo">'
    : '<div class="card-logo-wrapper"><img src="/logo.svg" alt="Logo" class="card-brand-icon"><span class="card-brand-text">' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</span></div>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found | Magma</title>
    <style>
        :root {
            --bg-body: <?= htmlspecialchars($bgBody, ENT_QUOTES, 'UTF-8') ?>;
            --bg-card: <?= htmlspecialchars($bgCard, ENT_QUOTES, 'UTF-8') ?>;
            --text-main: <?= htmlspecialchars($textMain, ENT_QUOTES, 'UTF-8') ?>;
            --text-muted: <?= htmlspecialchars($textMuted, ENT_QUOTES, 'UTF-8') ?>;
            --accent-warning: <?= htmlspecialchars($accentWarning, ENT_QUOTES, 'UTF-8') ?>;
            --border-card: <?= htmlspecialchars($borderCard, ENT_QUOTES, 'UTF-8') ?>;
            --color-primary: <?= htmlspecialchars($colorPrimary, ENT_QUOTES, 'UTF-8') ?>;
            --color-logo-bg: <?= htmlspecialchars($logoBgColor, ENT_QUOTES, 'UTF-8') ?>;
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
            padding: 3rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
        }
        .error-badge {
            display: inline-block;
            background-color: var(--bg-body);
            color: var(--accent-warning);
            font-weight: 700;
            font-size: 0.85rem;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            margin-bottom: 1.25rem;
            letter-spacing: 0.05em;
        }
        .error-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--color-primary);
        }
        .error-desc {
            font-size: 1.15rem;
            color: var(--text-muted);
            margin-bottom: 2.5rem;
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
            color: var(--accent-warning);
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
            background: var(--color-logo-bg);
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
        <h1 class="error-title">Page not found</h1>
        <p class="error-desc"><?= htmlspecialchars((string)$errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
        
        <div class="actions">
            <a href="/" class="btn">Return to homepage</a>
            <a href="javascript:history.back()" class="btn btn-outline">Go back</a>
        </div>
    </div>
</body>
</html>

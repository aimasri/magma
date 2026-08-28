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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found | Magma</title>
    <style>
        :root {
            --bg-body: #380404;
            --bg-card: #f4ead5;
            --text-main: #333333;
            --text-muted: #666666;
            --accent-warning: #ebb33a;
            --border-card: #e2e8f0;
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
            background-color: #380404;
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
            color: #622E00;
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
            background-color: #380404;
            color: #ebb33a;
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
    </style>
</head>
<body>
    <div class="error-card">
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

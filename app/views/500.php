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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 Internal Server Error | Magma</title>
    <style>
        :root {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-danger: #f43f5e;
            --border-card: #334155;
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
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            text-align: center;
        }
        .error-badge {
            display: inline-block;
            background-color: rgba(244, 63, 94, 0.15);
            color: var(--accent-danger);
            border: 1px solid rgba(244, 63, 94, 0.3);
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
            color: #fff;
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
            background-color: #38bdf8;
            color: #0f172a;
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
            background-color: rgba(255,255,255,0.05);
        }
        .trace-box {
            margin-top: 2rem;
            text-align: left;
            background-color: #090d16;
            border: 1px solid var(--border-card);
            border-radius: 8px;
            padding: 1rem;
            font-family: var(--font-mono);
            font-size: 0.85rem;
            color: #e2e8f0;
            overflow-x: auto;
            max-height: 300px;
        }
    </style>
</head>
<body>
    <div class="error-card">
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

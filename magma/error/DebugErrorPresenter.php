<?php

declare(strict_types=1);

namespace Magma\error;

use Throwable;
use Magma\http\RequestInterface;
use Magma\http\Response;

/**
 * Title: Developer Debug Error Presenter & Interactive Stack Trace Viewer
 *
 * Purpose:
 * - Renders a rich, interactive developer diagnostic interface when an unhandled exception occurs in development mode.
 * - Extracts and highlights code snippets from the filesystem around the exact line of failure.
 * - Displays complete structured stack traces, execution frames, argument dumps, request context, headers, and PHP runtime metrics.
 *
 * Why / Why this design:
 * - Developer Ergonomics & Rapid Diagnostics: Generic 500 pages force developers to parse raw terminal error logs. This presenter displays the exact source code context, file, line, and state directly in the browser during local development.
 * - Zero Dependency / Self-Contained: Contains all CSS and JS inline without relying on external CDNs or template engines, ensuring it renders flawlessly even if the view engine or filesystem assets fail.
 *
 * Teaching notes:
 * - This presenter is strictly gated behind `$debug === true` (or `APP_DEBUG=true`). In production environments, it is disabled to prevent sensitive information disclosure.
 */
class DebugErrorPresenter implements \Magma\interfaces\DebugErrorPresenterInterface
{
    /**
     * Renders an interactive HTML debug page for the given Throwable.
     *
     * Execution Flow:
     * 1. Extracts exception metadata (class, message, file, line, code).
     * 2. Extracts source code snippet around the error line (±10 lines).
     * 3. Formats all stack trace frames with file references and code previews.
     * 4. Gathers request parameters, HTTP headers, session attributes, and environment metrics.
     * 5. Assembles and returns the complete HTML Response.
     *
     * @param Throwable $e
     * @param RequestInterface|null $request
     * @param int $statusCode
     * @return Response
     */
    public function present(Throwable $e, ?RequestInterface $request = null, int $statusCode = 500): Response
    {
        $exceptionClass = get_class($e);
        $message = $e->getMessage() ?: '(No message provided)';
        $file = $e->getFile();
        $line = $e->getLine();
        $codeSnippet = $this->extractCodeSnippet($file, $line);
        $frames = $this->formatTraceFrames($e);
        $requestData = $this->gatherRequestContext($request);
        $environmentData = $this->gatherEnvironmentMetrics();

        $html = $this->renderHtml([
            'statusCode'      => $statusCode,
            'exceptionClass'  => $exceptionClass,
            'message'         => $message,
            'file'            => $file,
            'line'            => $line,
            'codeSnippet'     => $codeSnippet,
            'frames'          => $frames,
            'requestData'     => $requestData,
            'environmentData' => $environmentData,
        ]);

        return new Response($html, $statusCode, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Extracts source code lines around a target line from a file.
     *
     * @param string $filePath
     * @param int $targetLine
     * @param int $padding
     * @return array<int, array{line: int, code: string, isTarget: bool}>
     */
    public function extractCodeSnippet(string $filePath, int $targetLine, int $padding = 8): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
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
     * Formats the exception stack trace into structured frames with code previews.
     *
     * @param Throwable $e
     * @return array<int, array{file: string, line: int, call: string, snippet: array, args: array}>
     */
    private function formatTraceFrames(Throwable $e): array
    {
        $trace = $e->getTrace();
        $frames = [];

        foreach ($trace as $index => $frame) {
            $file = $frame['file'] ?? '[internal function]';
            $line = $frame['line'] ?? 0;
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $function = $frame['function'] ?? '';
            $call = $class ? "{$class}{$type}{$function}()" : "{$function}()";

            $args = [];
            if (!empty($frame['args'])) {
                foreach ($frame['args'] as $argKey => $argVal) {
                    $args[$argKey] = $this->formatArgument($argVal);
                }
            }

            $snippet = ($file !== '[internal function]' && $line > 0) 
                ? $this->extractCodeSnippet($file, $line, 5) 
                : [];

            $frames[] = [
                'index'   => $index + 1,
                'file'    => $file,
                'line'    => $line,
                'call'    => $call,
                'snippet' => $snippet,
                'args'    => $args,
            ];
        }

        return $frames;
    }

    /**
     * Formats a function argument into a safe human-readable representation.
     *
     * @param mixed $arg
     * @return string
     */
    private function formatArgument(mixed $arg): string
    {
        if (is_null($arg)) return 'null';
        if (is_bool($arg)) return $arg ? 'true' : 'false';
        if (is_int($arg) || is_float($arg)) return (string)$arg;
        if (is_string($arg)) {
            $truncated = mb_substr($arg, 0, 80);
            return '"' . htmlspecialchars($truncated, ENT_QUOTES, 'UTF-8') . (mb_strlen($arg) > 80 ? '...' : '') . '"';
        }
        if (is_array($arg)) return 'Array(' . count($arg) . ')';
        if (is_object($arg)) return get_class($arg);
        if (is_resource($arg)) return 'Resource(' . get_resource_type($arg) . ')';
        return gettype($arg);
    }

    /**
     * Gathers diagnostic request attributes.
     *
     * @param RequestInterface|null $request
     * @return array<string, mixed>
     */
    private function gatherRequestContext(?RequestInterface $request): array
    {
        if ($request === null) {
            return [
                'Method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI / Unknown',
                'URI'    => $_SERVER['REQUEST_URI'] ?? 'N/A',
                'IP'     => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ];
        }

        return [
            'HTTP Method'   => $request->getMethod(),
            'Request URI'   => $request->getUri(),
            'Path'          => $request->getPath(),
            'Query Params'  => $request->query() ?: '(empty)',
            'Body / Payload'=> $request->request() ?: '(empty)',
            'Client IP'     => $request->server('REMOTE_ADDR') ?? '127.0.0.1',
        ];
    }

    /**
     * Gathers PHP runtime and memory metrics.
     *
     * @return array<string, string>
     */
    private function gatherEnvironmentMetrics(): array
    {
        $memoryBytes = memory_get_peak_usage(true);
        $memoryMb = round($memoryBytes / (1024 * 1024), 2);

        return [
            'PHP Version'       => PHP_VERSION . ' (' . PHP_SAPI . ')',
            'Operating System'  => PHP_OS . ' (' . php_uname('m') . ')',
            'Memory Peak Usage' => "{$memoryMb} MB",
            'Server Time (UTC)' => gmdate('Y-m-d H:i:s') . ' UTC',
            'Framework'         => 'Magma Framework (Core Engine)',
        ];
    }

    /**
     * Assembles the complete HTML and CSS debug template.
     *
     * @param array<string, mixed> $data
     * @return string
     */
    private function renderHtml(array $data): string
    {
        $statusCode = (int)$data['statusCode'];
        $exceptionClass = htmlspecialchars((string)$data['exceptionClass'], ENT_QUOTES, 'UTF-8');
        $message = nl2br(htmlspecialchars((string)$data['message'], ENT_QUOTES, 'UTF-8'));
        $file = htmlspecialchars((string)$data['file'], ENT_QUOTES, 'UTF-8');
        $line = (int)$data['line'];

        $snippetHtml = $this->renderSnippetRows($data['codeSnippet']);
        $framesHtml = $this->renderFramesList($data['frames']);
        $requestHtml = $this->renderPropertiesTable($data['requestData']);
        $envHtml = $this->renderPropertiesTable($data['environmentData']);
        $frameCount = count((array)$data['frames']);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$statusCode} | {$exceptionClass}</title>
    <style>
        :root {
            --bg-canvas: #0f172a;
            --bg-surface: #1e293b;
            --bg-card: #334155;
            --border-color: #475569;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-danger: #f43f5e;
            --accent-highlight: rgba(244, 63, 94, 0.25);
            --accent-target-border: #f43f5e;
            --accent-info: #38bdf8;
            --font-sans: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-mono: 'JetBrains Mono', 'Fira Code', Menlo, Consolas, Monaco, monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-canvas);
            color: var(--text-main);
            font-family: var(--font-sans);
            line-height: 1.6;
            padding: 2rem 1.5rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header-card {
            background-color: var(--bg-surface);
            border-left: 6px solid var(--accent-danger);
            border-radius: 8px;
            padding: 1.75rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        .badge-status {
            display: inline-block;
            background-color: var(--accent-danger);
            color: #fff;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }
        .exception-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            word-break: break-all;
            margin-bottom: 0.5rem;
            font-family: var(--font-mono);
        }
        .exception-message {
            font-size: 1.15rem;
            color: #fecdd3;
            margin-bottom: 1rem;
        }
        .file-location {
            font-family: var(--font-mono);
            font-size: 0.9rem;
            color: var(--text-muted);
            background: rgba(0,0,0,0.25);
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            display: inline-block;
            word-break: break-all;
        }
        .file-location strong {
            color: var(--accent-info);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 2rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #fff;
        }

        .code-window {
            background-color: #090d16;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            font-family: var(--font-mono);
            font-size: 0.88rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        }
        .code-window-header {
            background-color: var(--bg-surface);
            padding: 0.6rem 1rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
        }
        .code-table {
            width: 100%;
            border-collapse: collapse;
        }
        .code-row {
            display: flex;
            width: 100%;
        }
        .code-row.target-line {
            background-color: var(--accent-highlight);
            border-left: 4px solid var(--accent-target-border);
        }
        .line-number {
            width: 60px;
            padding: 0.25rem 0.75rem;
            text-align: right;
            color: #64748b;
            user-select: none;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .code-row.target-line .line-number {
            color: #fff;
            font-weight: bold;
        }
        .code-content {
            padding: 0.25rem 1rem;
            white-space: pre;
            overflow-x: auto;
            flex: 1;
            color: #e2e8f0;
        }

        .frames-container {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }
        .frame-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
        }
        .frame-header {
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
            background: rgba(255,255,255,0.02);
        }
        .frame-header:hover {
            background: rgba(255,255,255,0.05);
        }
        .frame-title {
            font-family: var(--font-mono);
            font-size: 0.9rem;
            color: var(--text-main);
        }
        .frame-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-family: var(--font-mono);
        }
        .frame-body {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            background-color: #090d16;
            display: none;
        }
        .frame-body.open {
            display: block;
        }

        .grid-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 768px) {
            .grid-two-col { grid-template-columns: 1fr; }
        }
        .info-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.25rem;
        }
        .props-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        .props-table tr {
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .props-table th {
            text-align: left;
            padding: 0.5rem 0.5rem 0.5rem 0;
            color: var(--text-muted);
            width: 35%;
            font-weight: 500;
        }
        .props-table td {
            padding: 0.5rem 0;
            color: #e2e8f0;
            font-family: var(--font-mono);
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-card">
            <span class="badge-status">HTTP {$statusCode} Diagnostic</span>
            <h1 class="exception-title">{$exceptionClass}</h1>
            <p class="exception-message">{$message}</p>
            <div class="file-location">
                {$file}:<strong>{$line}</strong>
            </div>
        </div>

        <div class="section-title">
            <span>Source Code Context</span>
        </div>
        <div class="code-window">
            <div class="code-window-header">{$file}:{$line}</div>
            <div class="code-table">
                {$snippetHtml}
            </div>
        </div>

        <div class="section-title">
            <span>Call Stack Trace ({$frameCount} frames)</span>
        </div>
        <div class="frames-container">
            {$framesHtml}
        </div>

        <div class="grid-two-col">
            <div class="info-card">
                <h3 style="margin-bottom: 1rem; color: var(--accent-info);">Request Context</h3>
                {$requestHtml}
            </div>
            <div class="info-card">
                <h3 style="margin-bottom: 1rem; color: var(--accent-info);">Runtime Environment</h3>
                {$envHtml}
            </div>
        </div>
    </div>

    <script>
        function toggleFrame(index) {
            const body = document.getElementById('frame-body-' + index);
            if (body) {
                body.classList.toggle('open');
            }
        }
    </script>
</body>
</html>
HTML;
    }

    private function renderSnippetRows(array $snippet): string
    {
        if (empty($snippet)) {
            return '<div style="padding: 1rem; color: #64748b;">(Source code unavailable or file unreadable)</div>';
        }

        $html = '';
        foreach ($snippet as $row) {
            $line = (int)$row['line'];
            $code = htmlspecialchars((string)$row['code'], ENT_QUOTES, 'UTF-8');
            $targetClass = !empty($row['isTarget']) ? ' target-line' : '';

            $html .= "<div class=\"code-row{$targetClass}\">";
            $html .= "<span class=\"line-number\">{$line}</span>";
            $html .= "<span class=\"code-content\">{$code}</span>";
            $html .= "</div>";
        }

        return $html;
    }

    private function renderFramesList(array $frames): string
    {
        if (empty($frames)) {
            return '<div style="padding: 1rem; color: #64748b;">(No stack trace available)</div>';
        }

        $html = '';
        foreach ($frames as $frame) {
            $index = (int)$frame['index'];
            $file = htmlspecialchars((string)$frame['file'], ENT_QUOTES, 'UTF-8');
            $line = (int)$frame['line'];
            $call = htmlspecialchars((string)$frame['call'], ENT_QUOTES, 'UTF-8');
            $snippetHtml = $this->renderSnippetRows($frame['snippet'] ?? []);

            $argsHtml = '';
            if (!empty($frame['args'])) {
                $argsHtml .= '<div style="margin-bottom: 0.5rem; font-size: 0.8rem; color: #94a3b8;"><strong>Arguments:</strong> ';
                $argsHtml .= implode(', ', $frame['args']);
                $argsHtml .= '</div>';
            }

            $html .= <<<HTML
            <div class="frame-card">
                <div class="frame-header" onclick="toggleFrame({$index})">
                    <div class="frame-title">#{$index} {$call}</div>
                    <div class="frame-meta">{$file}:{$line}</div>
                </div>
                <div class="frame-body" id="frame-body-{$index}">
                    {$argsHtml}
                    <div class="code-table">
                        {$snippetHtml}
                    </div>
                </div>
            </div>
HTML;
        }

        return $html;
    }

    private function renderPropertiesTable(array $data): string
    {
        $html = '<table class="props-table">';
        foreach ($data as $key => $val) {
            $label = htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8');
            if (is_array($val)) {
                $content = '<pre style="white-space: pre-wrap; font-size: 0.8rem; color: #cbd5e1;">' . htmlspecialchars(json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . '</pre>';
            } else {
                $content = htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
            }
            $html .= "<tr><th>{$label}</th><td>{$content}</td></tr>";
        }
        $html .= '</table>';
        return $html;
    }
}

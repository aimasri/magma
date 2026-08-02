<?php
/**
 * Title: Internal Server Error View Template
 *
 * Purpose:
 * - Renders a static, fallback error page when the application encounters an unhandled exception.
 * - Provides a safe, generic message to the user without leaking stack traces or sensitive data.
 *
 * Teaching notes:
 * - Since this page is triggered during catastrophic failures (e.g., database down, syntax errors), 
 *   it should rely on zero external dependencies, minimal PHP processing, and inline CSS.
 *
 * Variables:
 * (None expected - purely static fallback)
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Error 500</title>
</head>
<body>
    <h1>500 - Internal Server Error</h1>
    <p>Something went wrong.</p>
</body>
</html>

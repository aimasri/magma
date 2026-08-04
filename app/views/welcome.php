<?php
/**
 * Title: Welcome View Template
 *
 * Purpose:
 * - Renders the default landing page for the Magma framework.
 * - Displays a basic HTML structure welcoming the user.
 *
 * Teaching notes:
 * - Views should remain completely logic-less (or as close to it as possible). All conditional
 *   formatting or data transformation should occur in the controller or a presenter layer before reaching here.
 *
 * Variables:
 * @var array $data An array containing data passed from the controller.
 * @var string $data['title'] The title of the page.
 * @var \Magma\view\TemplateEngine $data['engine'] The templating engine used to escape output.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($data['title']) ? $data['engine']->escape($data['title']) : 'Magma Framework' ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="magma-theme-root">
    <main class="welcome-card">
        <h1 class="welcome-card__title">Welcome to Magma!</h1>
        <p class="welcome-card__text">A solid, explicit, "no magic" PHP framework.</p>
    </main>
</body>
</html>

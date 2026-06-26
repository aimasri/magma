<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($data['title']) ? $data['engine']->escape($data['title']) : 'Magma Framework' ?></title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; color: #333; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .welcome-container { background: white; padding: 3rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; }
        h1 { margin-top: 0; color: #e74c3c; }
        p { margin-bottom: 0; }
    </style>
</head>
<body>
    <main class="welcome-container">
        <h1>Welcome to Magma!</h1>
        <p>A solid, explicit, "no magic" PHP framework.</p>
    </main>
</body>
</html>

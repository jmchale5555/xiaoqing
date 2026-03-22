<?php

$viteDevServer = getenv('VITE_DEV_SERVER');

if (!empty($viteDevServer))
{
?><!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP + React</title>
    <script type="module" src="<?= htmlspecialchars($viteDevServer) ?>/@vite/client"></script>
    <script type="module" src="<?= htmlspecialchars($viteDevServer) ?>/src/main.jsx"></script>
</head>
<body>
    <div id="root"></div>
</body>
</html>
<?php
    return;
}

$spaIndex = __DIR__ . '/spa/index.html';

if (file_exists($spaIndex))
{
    readfile($spaIndex);
    return;
}

http_response_code(503);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frontend Not Built</title>
</head>
<body>
    <h1>Frontend is not available yet.</h1>
    <p>Run npm install and npm run build, or set VITE_DEV_SERVER for development.</p>
</body>
</html>

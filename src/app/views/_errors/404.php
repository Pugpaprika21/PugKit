<?php

/** @var Exception $e */ ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #fdfdfd;
            color: #333;
            text-align: center;
            padding: 50px;
        }

        h1 {
            font-size: 5em;
            color: #e74c3c;
        }

        p {
            font-size: 1.2em;
        }

        .debug {
            margin-top: 30px;
            background-color: #f3f3f3;
            padding: 20px;
            border-radius: 10px;
            display: inline-block;
            text-align: left;
            font-size: 0.95em;
            color: #555;
        }

        a {
            color: #3498db;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <h1>404</h1>
    <p>Sorry! The page you requested could not be found.</p>
    <p><a href="/">← Back to Home</a></p>

    <?php if (isset($e)): ?>
        <div class="debug">
            <strong>Error Details (Debug):</strong><br>
            Message: <?= htmlspecialchars($e->getMessage()) ?><br>
            File: <?= htmlspecialchars($e->getFile()) ?><br>
            Line: <?= $e->getLine() ?><br>
            Code: <?= $e->getCode() ?>
        </div>
    <?php endif; ?>
</body>

</html>
<?php

declare(strict_types=1);

use PugKit\Singleton\Application;

require_once __DIR__ . "/../app/boostrap/framework/singleton.php";

$app = Application::concreate();

/** @var Application&RouterInterface $app */
$app->get("/", function () {
    return "Hello index";
});

$route = filter_var(!empty($_GET["route"]) ? trim($_GET["route"]) : "/", FILTER_SANITIZE_URL);
$app->dispatch($route);

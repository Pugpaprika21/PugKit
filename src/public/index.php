<?php

declare(strict_types=1);

use PugKit\Builder\Application;
use PugKit\DotENV\DotEnvEnvironment;
use PugKit\Router\RouteGroupInterface;

require_once __DIR__ . "/../app/boostrap/framework/Pugkit.php";

(new DotEnvEnvironment())->load(__DIR__ . "/../configs/dev_.env");

$app = Application::concreate();

$container = $app->useContianer();

$container->set("database", function () {
    /*  */
});

$container->set("services", function () {
    /*  */
});

$router = $app->useRouterCore($container);

$router->group("/api/v1", function (RouteGroupInterface $group) {
    $group->get("/get/{userId}", function ($userId) {
        echo $userId;
    });
});

$route = filter_var(!empty($_GET["route"]) ? trim($_GET["route"]) : "/", FILTER_SANITIZE_URL);
$router->dispatch($route);

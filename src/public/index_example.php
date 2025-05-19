<?php

declare(strict_types=1);

use App\Core\Controllers\HomeController;
use App\Core\Controllers\User\UserController;
use PugKit\Builder\Application;
use PugKit\DotENV\DotEnvEnvironment;
use PugKit\Router\RouteGroupInterface;

use function PugKit\Helper\dd;

require_once __DIR__ . "/loadclasses.php";
require_once __DIR__ . "/../app/boostrap/framework/Pugkit.php";

(new DotEnvEnvironment())->load(__DIR__ . "/../configs/dev_.env");

$app = Application::concreate();

################################### Example Contianer ###################################

$container = $app->useContianer();

$container->set(PDO::class, function () {
    try {
        $pdo = new PDO("mysql:host={$_ENV["DB_HOST"]};dbname={$_ENV["DB_NAME"]}", $_ENV["DB_USERNAME"], $_ENV["DB_PASSWORD"]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return $pdo;
    } catch (PDOException $err) {
        die($err->getMessage());
    }
});

################################### Example Router ######################################

$router = $app->useRouterCore();

$router->get("/", [HomeController::class, "index"]);

$router->group("/api/v1", function (RouteGroupInterface $group) {
    $group->get("/user/get/{userId}", [UserController::class, "get"]);
    $group->get("/user/getlist", [UserController::class, "getlist"]);
});

$router->group("/api/v1", function (RouteGroupInterface $group) {
    $group->get("/get/{userId}", function ($userId) {
        global $container;

        if ($userId) {
            $db = $container->get(PDO::class);
            dd($db);
        }
    });
});

$route = filter_var(!empty($_GET["route"]) ? trim($_GET["route"]) : "/", FILTER_SANITIZE_URL);
$router->dispatch($route);

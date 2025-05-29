<?php

declare(strict_types=1);

use App\Core\Controllers\HomeController;
use App\Core\Controllers\User\UserController;
use PugKit\DotENV\Environment;
use PugKit\Http\Request\RequestInterface;
use PugKit\RouterCore\RouterGroupInterface;
use PugKit\Singleton\Application;

require_once __DIR__ . "/../public/loadclasses.php";
require_once __DIR__ . "/../app/boostrap/framework/PugKit.php";

Environment::load(__DIR__ . "/../configs/dev_.env");

$app = Application::concreate();

/** @var Application&ContainerInterface $app */
$app->bind(PDO::class, function () {
    try {
        $pdo = new PDO("mysql:host={$_ENV["DB_HOST"]};dbname={$_ENV["DB_NAME"]}", $_ENV["DB_USERNAME"], $_ENV["DB_PASSWORD"]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return $pdo;
    } catch (PDOException $err) {
        throw new Exception($err->getMessage(), 500);
    }
});

/** @var Application&RouterInterface $app */

$router = $app->getServerRouter();

$router->get("/", [HomeController::class, "index"]);

$router->get("/user/{id}", function (RequestInterface $request) {
    $params = $request->params();
    return $params->id;
});

$router->group("/api/v1", function (RouterGroupInterface $group) {
    $group->get("/user/get/{userId}", [UserController::class, "getUser"]);
    $group->get("/user/getlist", [UserController::class, "getUsers"]);
});

$router->view("/html", "index.php");

$route = filter_var($_GET["route"] ?? "/", FILTER_SANITIZE_URL);
$router->dispatch($route);

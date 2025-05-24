<?php

namespace App\Core\Controllers\User;

use App\Core\Controllers\BaseController;
use PDO;
use PugKit\DI\ContainerInterface;
use PugKit\Http\Request\RequestInterface;
use PugKit\Http\Response\JsonResponse;

class UserController extends BaseController
{
    public function __construct(private ContainerInterface $container) {}

    public function get(RequestInterface $request, string $userId): JsonResponse
    {
        $db = $this->container->using(PDO::class);

        $sql = "SELECT * FROM users WHERE user_id = :user_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new JsonResponse($rows, "Success", 200);
    }

    public function getlist(RequestInterface $request): JsonResponse
    {
        $db = $this->container->using(PDO::class);

        $sql = "SELECT * FROM users";
        $stmt = $db->prepare($sql);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new JsonResponse($rows, "Success", 200);
    }
}

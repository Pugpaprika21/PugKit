<?php

namespace App\Core\Controllers\User;

use App\Core\Controllers\BaseController;
use PDO;
use PugKit\DI\ContinerIneterface;
use PugKit\Response\JsonResponse;

class UserController extends BaseController
{
    private ContinerIneterface $container;

    public function __construct(ContinerIneterface $container)
    {
        $this->container = $container;
    }

    public function get(string $userId): JsonResponse
    {
        $db = $this->container->get(PDO::class);

        $sql = "SELECT * FROM users WHERE user_id = :user_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new JsonResponse($rows, "Success", 200);
    }

    public function getlist(): JsonResponse
    {
        $db = $this->container->get(PDO::class);

        $sql = "SELECT * FROM users";
        $stmt = $db->prepare($sql);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new JsonResponse($rows, "Success", 200);
    }
}

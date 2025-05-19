<?php

namespace App\Core\Controllers\User;

use App\Core\Controllers\BaseController;
use PDO;
use PugKit\DI\ContainerIneterface;
use PugKit\Request\RequestInterface;
use PugKit\Response\JsonResponse;
use PugKit\Response\ResponseEnums;

class UserController extends BaseController
{
    private ContainerIneterface $container;

    public function __construct(ContainerIneterface $container)
    {
        $this->container = $container;
    }

    public function get(RequestInterface $request, string $userId): JsonResponse
    {
        $db = $this->container->get(PDO::class);

        $sql = "SELECT * FROM users WHERE user_id = :user_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new JsonResponse($rows, "Success", ResponseEnums::OK);
    }

    public function getlist(RequestInterface $request): JsonResponse
    {
        $db = $this->container->get(PDO::class);

        $sql = "SELECT * FROM users";
        $stmt = $db->prepare($sql);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new JsonResponse($rows, "Success", ResponseEnums::OK);
    }
}

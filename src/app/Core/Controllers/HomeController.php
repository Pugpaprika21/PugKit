<?php

namespace App\Core\Controllers;

use PugKit\ViewFactory\View;

class HomeController extends BaseController
{
    public function index()
    {
        $data = [
            "title" => "Home",
            "description" => "Welcome to the home page.",
        ];

        $view = new View("/user/pages/index.php", $data);
        $view->setLayoutHeader("/user/layouts/header.php");
        $view->setLayoutContent("/user/pages/content.php");
        $view->setLayoutFooter("/user/layouts/footer.php");
        return $view->render();
    }
}

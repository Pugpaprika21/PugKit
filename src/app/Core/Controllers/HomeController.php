<?php

namespace App\Core\Controllers;

use PugKit\Request\RequestInterface;

class HomeController extends BaseController
{
    public function index(RequestInterface $request)
    {
        $this->view->layoutHeader("home/layouts/header.php");
        $this->view->layoutContent("home/pages/home.php");
        $this->view->layoutFooter("home/layouts/footer.php");
        return $this->view->render();
    }
}

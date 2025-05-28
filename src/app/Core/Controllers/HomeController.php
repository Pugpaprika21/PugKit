<?php

namespace App\Core\Controllers;

use PugKit\Http\Request\RequestInterface;
use PugKit\Web\Display\ViewDisplayInterface;

class HomeController extends BaseController
{
    public function index(RequestInterface $request): ViewDisplayInterface
    {
        return $this->view("index.php");
    }
}

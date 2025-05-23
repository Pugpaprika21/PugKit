<?php

namespace App\Core\Controllers;

use PugKit\ViewFactory\View;
use PugKit\ViewFactory\ViewInterface;

abstract class BaseController
{
    protected ViewInterface $view;

    public function __construct()
    {
        $this->view = new View();
    }
}

<?php

namespace App\Core\Controllers;

use PugKit\Singleton\Application;
use PugKit\Web\Display\ViewDisplayInterface;

abstract class BaseController extends Application
{
    protected ?ViewDisplayInterface $template;

    public function __construct()
    {
        $this->template = $this->getView();
    }
}

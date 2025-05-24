<?php

spl_autoload_register(function (string $classname) {
    $classParts = explode("\\", $classname);
    $classFilename = end($classParts);

    $classConfPath = include __DIR__ . "/../app/configs/_namespace.php";

    foreach ($classConfPath["app"] as $folder) {
        $classRealPart = sprintf("%s/../%s%s.php", __DIR__, $folder, $classFilename);
        if (file_exists($classRealPart)) {
            include_once $classRealPart;
            return;
        }
    }
});
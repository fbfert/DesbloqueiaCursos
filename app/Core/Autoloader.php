<?php

namespace App\Core;

class Autoloader
{
    public static function register($basePath)
    {
        spl_autoload_register(function ($class) use ($basePath) {
            $prefix = 'App\\';

            if (strpos($class, $prefix) !== 0) {
                return;
            }

            $relativeClass = substr($class, strlen($prefix));
            $file = $basePath . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }
}

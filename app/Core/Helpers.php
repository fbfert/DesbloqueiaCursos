<?php

namespace App\Core;

class Helpers
{
    public static function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function path($relativePath = '')
    {
        return BASE_PATH . ($relativePath ? '/' . ltrim($relativePath, '/\\') : '');
    }

    public static function url($path = '')
    {
        $config = require BASE_PATH . '/config/app.php';

        return rtrim($config['url'], '/') . '/' . ltrim($path, '/');
    }
}

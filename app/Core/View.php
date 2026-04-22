<?php

namespace App\Core;

class View
{
    public static function render($template, array $data = array())
    {
        $viewFile = BASE_PATH . '/resources/views/' . $template . '.php';

        if (!is_file($viewFile)) {
            return 'View not found: ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
        }

        extract($data);

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        ob_start();
        require BASE_PATH . '/resources/views/layout.php';
        return ob_get_clean();
    }
}

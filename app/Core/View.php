<?php

namespace App\Core;

class View
{
    public static function render($template, array $data = array(), $useLayout = true, $baseDirectory = 'views')
    {
        $viewFile = BASE_PATH . '/resources/' . trim($baseDirectory, '/\\') . '/' . $template . '.php';

        if (!is_file($viewFile)) {
            return 'View not found: ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
        }

        if (trim((string) $baseDirectory, '/\\') === 'views') {
            $data = array_merge($data, array(
                'csrfToken' => Csrf::token(),
                'csrfField' => Csrf::field(),
                'oldInput' => Session::pullFlash('old_input', array()),
            ));
        }

        extract($data);

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if (!$useLayout) {
            return Csrf::injectIntoHtml($content);
        }

        ob_start();
        require BASE_PATH . '/resources/views/layout.php';
        return Csrf::injectIntoHtml(ob_get_clean());
    }
}

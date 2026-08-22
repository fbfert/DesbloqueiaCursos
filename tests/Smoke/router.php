<?php

/**
 * Router para o servidor embutido do PHP (`php -S`).
 *
 * O `php -S` não lê .htaccess. Sem isto, um caminho COM extensão que não exista
 * em disco (ex.: /sitemap.xml, servido por rota) volta 404 antes de chegar ao
 * front controller — e o smoke acusa uma falha que só existe no servidor local.
 *
 * Uso:
 *   php -S 127.0.0.1:8000 -t . tests/Smoke/router.php
 *   php tests/Smoke/smoke.php http://127.0.0.1:8000
 */

$raiz = dirname(__DIR__, 2);
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$caminho = $caminho === false || $caminho === null ? '/' : $caminho;

// Impede que o servidor local exponha o que o .htaccess bloqueia em produção.
if (preg_match('#^/(app|backups|config|docs|resources|routes|scripts|specs|sql|storage|tests)(/|$)#', $caminho)) {
    http_response_code(403);
    echo 'Acesso negado.';
    return true;
}

// Arquivo estático existente (assets, uploads, favicon): deixa o servidor servir.
$arquivo = $raiz . '/' . ltrim($caminho, '/');
if ($caminho !== '/' && is_file($arquivo) && substr($caminho, -4) !== '.php') {
    return false;
}

require $raiz . '/index.php';

<?php
// layout-dados.php reescreve os links de navegação a partir do V2Nav; os links
// desta tela (com ?redirect= preservado) vêm do controller e são guardados antes.
$authLinks = array(
    'register' => isset($registerHref) ? (string) $registerHref : '/cadastro',
    'forgot' => isset($forgotHref) ? (string) $forgotHref : '/recuperar-senha',
);
$contentView = BASE_PATH . '/resources/views/caderno/pages/login.php';
$paginaTema = 'login';
require BASE_PATH . '/resources/views/caderno/auth-layout.php';

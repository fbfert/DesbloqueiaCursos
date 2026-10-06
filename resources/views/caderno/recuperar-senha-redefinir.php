<?php
// layout-dados.php reescreve os links de navegação; o "voltar ao login" vem do controller.
$authLinks = array(
    'login' => isset($loginHref) ? (string) $loginHref : '/v2/login',
);
$contentView = BASE_PATH . '/resources/views/caderno/pages/recuperar-senha-redefinir.php';
$paginaTema = 'recuperar-senha-redefinir';
require BASE_PATH . '/resources/views/caderno/auth-layout.php';

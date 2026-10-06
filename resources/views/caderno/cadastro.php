<?php
// layout-dados.php reescreve os links de navegação a partir do V2Nav; os links
// desta tela (login com ?redirect=, termos e privacidade) vêm do controller.
$authLinks = array(
    'login' => isset($loginHref) ? (string) $loginHref : '/v2/login',
    'termos' => isset($termosHref) ? (string) $termosHref : '/termos-de-uso',
    'privacidade' => isset($privacidadeHref) ? (string) $privacidadeHref : '/politica-de-privacidade',
);
$contentView = BASE_PATH . '/resources/views/caderno/pages/cadastro.php';
$paginaTema = 'cadastro';
require BASE_PATH . '/resources/views/caderno/auth-layout.php';

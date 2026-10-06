<?php
// Como na V2, a escolha pós-login usa o layout principal (usuário já autenticado).
// layout-dados.php reescreve $catalogoHref; o destino do controller é guardado antes.
$authLinks = array(
    'aluno' => isset($alunoHref) ? (string) $alunoHref : '',
    'catalogo' => isset($catalogoHref) ? (string) $catalogoHref : '',
);
$contentView = BASE_PATH . '/resources/views/caderno/pages/pos-login.php';
$paginaTema = 'pos-login';
require BASE_PATH . '/resources/views/caderno/layout.php';

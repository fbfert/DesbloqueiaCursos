<?php
// Confirmação de saída (GET /logout) no tema caderno: folha de autenticação,
// com POST em /v2/logout (CSRF injetado pelo View::render).
$contentView = BASE_PATH . '/resources/views/caderno/pages/sair.php';
$paginaTema = 'sair';
require BASE_PATH . '/resources/views/caderno/auth-layout.php';

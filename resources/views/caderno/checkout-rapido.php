<?php
// Checkout rápido no tema caderno: mesmas variáveis de checkout-rapido/comprar.php.
$contentView = BASE_PATH . '/resources/views/caderno/pages/checkout-rapido.php';
$paginaTema = 'checkout';
$cadernoRobots = 'noindex, nofollow';
$pageTitle = isset($pageTitle) && (string) $pageTitle !== '' ? (string) $pageTitle : 'Garantir minha vaga · ' . (string) ($curso['nome'] ?? 'Curso') . ' — Desbloqueia Cursos';
require BASE_PATH . '/resources/views/caderno/layout.php';

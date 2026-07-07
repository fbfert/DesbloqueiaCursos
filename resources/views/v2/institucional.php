<?php
// Casca V2 para páginas institucionais editáveis (Fase 2.13B). O conteúdo real
// vem do backend (tabela `paginas`) e é preparado/sanitizado no controller.
$contentView = BASE_PATH . '/resources/views/v2/pages/institucional.php';
$disableV2AutoRenderHome = true;
require BASE_PATH . '/resources/views/v2/layout.php';

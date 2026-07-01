<?php
// Bootstrap híbrido do Catálogo V2 (Fase 2.2).
// Tem prioridade sobre index.html (ver DirectoryIndex em v2/.htaccess).
// Encaminha a requisição /v2/catalogo/ para o front controller principal,
// que resolve a rota dinâmica GET /v2/catalogo no roteador do sistema.
require dirname(__DIR__, 2) . '/index.php';

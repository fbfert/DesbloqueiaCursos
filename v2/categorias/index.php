<?php
// Bootstrap híbrido de Categorias V2.
// Tem prioridade sobre index.html (ver DirectoryIndex em v2/.htaccess).
// Encaminha a requisição /v2/categorias/ para o front controller principal,
// que resolve a rota dinâmica GET /v2/categorias no roteador do sistema.
require dirname(__DIR__, 2) . '/index.php';

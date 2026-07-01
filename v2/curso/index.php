<?php
// Bootstrap híbrido da Ficha de Curso V2 (Fase 2.3).
// Tem prioridade sobre index.html (ver DirectoryIndex em v2/.htaccess).
// Encaminha /v2/curso/ ao front controller principal, que resolve a rota
// dinâmica GET /v2/curso no roteador do sistema. Sem SQL no arquivo público.
require dirname(__DIR__, 2) . '/index.php';

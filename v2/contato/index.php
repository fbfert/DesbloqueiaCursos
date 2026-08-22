<?php
// Bootstrap híbrido do Contato V2 (correção pós-lançamento).
// Tem prioridade sobre index.html (ver DirectoryIndex em v2/.htaccess).
// Encaminha /v2/contato/ ao front controller principal, que resolve a rota
// dinâmica GET /v2/contato no roteador do sistema. Sem SQL, sem lógica de
// negócio e sem credenciais neste arquivo público.
require dirname(__DIR__, 2) . '/index.php';

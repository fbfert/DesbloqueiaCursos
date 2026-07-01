<?php
// Bootstrap híbrido do Cadastro V2 (Fase 2.5).
// Tem prioridade sobre index.html (ver DirectoryIndex em v2/.htaccess).
// Encaminha /v2/cadastro/ ao front controller principal, que resolve a rota
// dinâmica GET /v2/cadastro. Sem SQL, sem lógica de cadastro, sem credenciais
// e sem sessão própria neste arquivo público.
require dirname(__DIR__, 2) . '/index.php';

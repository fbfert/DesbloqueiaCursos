<?php
// Bootstrap híbrido do Login V2 (Fase 2.4).
// Tem prioridade sobre index.html (ver DirectoryIndex em v2/.htaccess).
// Encaminha /v2/login/ ao front controller principal, que resolve a rota
// dinâmica GET /v2/login. Sem SQL, sem lógica de autenticação, sem sessão
// própria e sem credenciais neste arquivo público.
require dirname(__DIR__, 2) . '/index.php';

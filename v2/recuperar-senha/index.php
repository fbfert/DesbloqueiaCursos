<?php
// Bootstrap híbrido da Recuperação de Senha V2 (Fase 2.5).
// Tem prioridade sobre index.html (ver DirectoryIndex em v2/.htaccess).
// Encaminha /v2/recuperar-senha/ ao front controller principal, que resolve a
// rota dinâmica GET /v2/recuperar-senha. Sem SQL, sem lógica de recuperação,
// sem token, sem credenciais e sem sessão própria neste arquivo público.
require dirname(__DIR__, 2) . '/index.php';

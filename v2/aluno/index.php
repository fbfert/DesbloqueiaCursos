<?php
// Bootstrap híbrido da Área do Aluno V2 (Fase 2.6).
// Tem prioridade sobre index.html (ver DirectoryIndex em v2/.htaccess).
// Encaminha /v2/aluno/ ao front controller principal, que resolve a rota
// dinâmica GET /v2/aluno. A autenticação real é verificada no controller V2
// (mesma sessão do sistema). Sem SQL, sem lógica de autenticação, sem
// credenciais e sem sessão própria neste arquivo público.
require dirname(__DIR__, 2) . '/index.php';

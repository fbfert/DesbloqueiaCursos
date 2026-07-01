<?php
// Bootstrap híbrido do LMS V2 (Fase 2.7) — leitura e navegação.
// Tem prioridade sobre index.html (ver DirectoryIndex em v2/.htaccess).
// Encaminha /v2/aula/ ao front controller principal, que resolve a rota
// dinâmica GET /v2/aula. A autenticação e a posse da inscrição são verificadas
// no controller V2 reutilizando os serviços reais. Sem SQL, sem sessão própria,
// sem credenciais, sem dados de curso e sem regras de permissão neste arquivo.
require dirname(__DIR__, 2) . '/index.php';

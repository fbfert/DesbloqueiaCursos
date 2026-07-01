<?php
// Bootstrap híbrido do Quiz V2 (Fase 2.9) — quizzes objetivos reais.
// Tem prioridade sobre eventual index.html (ver DirectoryIndex em v2/.htaccess).
// Encaminha /v2/quiz/ ao front controller principal, que resolve a rota
// dinâmica GET /v2/quiz. A autenticação, a posse da inscrição e todas as regras
// de tentativa/correção/nota são verificadas no controller V2 reutilizando os
// serviços reais. Sem SQL, sem sessão própria, sem autenticação própria, sem
// regras de correção e sem dados de quiz neste arquivo.
require dirname(__DIR__, 2) . '/index.php';

<?php
// Bootstrap híbrido da Atividade discursiva V2 (Fase 2.10).
// Tem prioridade sobre eventual index.html (ver DirectoryIndex em v2/.htaccess).
// Encaminha /v2/atividade/ ao front controller principal, que resolve a rota
// dinâmica GET /v2/atividade. A autenticação, a posse da inscrição e todas as
// regras de envio/reenvio/correção/nota são verificadas no controller V2
// reutilizando os serviços reais. Sem SQL, sem sessão/autenticação própria, sem
// regras de correção, sem dados da atividade e sem lógica de nota neste arquivo.
require dirname(__DIR__, 2) . '/index.php';

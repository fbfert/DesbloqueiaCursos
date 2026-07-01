<?php
// Bootstrap híbrido da Validação Pública de Certificados V2 (Fase 2.11).
// Tem prioridade sobre o index.html estático (ver DirectoryIndex em v2/.htaccess).
// Encaminha /v2/certificados/validar/ ao front controller principal, que resolve
// as rotas dinâmicas GET/POST /v2/certificados/validar. Toda a validação real
// (código, CPF, status, revogação, elegibilidade de PDF) é decidida pelo serviço
// público já existente, reutilizado no controller V2. Sem SQL, sem sessão/token,
// sem lógica de certificado e sem regras de autorização neste arquivo.
require dirname(__DIR__, 3) . '/index.php';

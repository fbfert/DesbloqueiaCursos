<?php
// Bootstrap híbrido do Checkout V2 (Fase 2.12A) — etapas de pré-pagamento.
// Tem prioridade sobre o index.html estático (DirectoryIndex em v2/.htaccess).
// Encaminha /v2/checkout/ ao front controller, que resolve a rota dinâmica
// GET /v2/checkout (redireciona para a inscrição V2). Toda a regra real
// (pedido, validações, participantes, totalização, propriedade, sessão) vive no
// CheckoutController/serviços existentes, reutilizados pela casca V2. Sem SQL,
// sessão própria, credenciais ou regras neste arquivo. As subetapas
// (/v2/checkout/inscricao|participantes|resumo) NÃO têm diretório próprio: são
// resolvidas pelo rewrite do front controller, evitando o DirectorySlash do
// Apache que converteria POST em GET nas rotas de envio.
require dirname(__DIR__, 2) . '/index.php';

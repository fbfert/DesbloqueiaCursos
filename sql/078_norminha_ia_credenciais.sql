-- 078 — Credencial, modelo e teto de gasto da IA, configuráveis pelo admin
--
-- Até aqui a chave da OpenAI e o modelo só existiam no `.env`, o que obriga
-- acesso ao servidor para mudar qualquer um dos dois. Esta migration cria as
-- chaves que a tela nova administra.
--
-- ONDE A CHAVE FICA GUARDADA, E POR QUÊ
--
-- Cifrada, em `tutor_configuracoes.valor`, com App\Support\Crypto (AES-256-CBC
-- + HMAC), cuja chave de cifragem vive no `.env` (APP_KEY). É o mesmo padrão
-- que o projeto já usa para as credenciais de gateway de pagamento.
--
-- A separação importa: um dump do banco — que é exatamente o que vazou em
-- 2026 — não entrega a chave da OpenAI, porque o APP_KEY não está no banco.
-- Guardar em texto puro aqui seria repetir o erro que acabou de custar caro.
--
-- O `.env` continua valendo como origem alternativa: se OPENAI_API_KEY estiver
-- definida lá, ela tem precedência sobre o banco. Assim quem preferir
-- administrar por arquivo não é forçado a migrar.
--
-- Idempotente: cada chave só entra se ainda não existir.

INSERT INTO tutor_configuracoes (chave, valor, atualizado_em)
SELECT 'tutor_ia_openai_key', '', NOW()
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tutor_configuracoes WHERE chave = 'tutor_ia_openai_key');

-- Vazio de propósito: sem modelo escolhido o OpenAIService recusa o pedido
-- antes de tocar a rede. Nenhum padrão é chutado aqui.
INSERT INTO tutor_configuracoes (chave, valor, atualizado_em)
SELECT 'tutor_ia_modelo', '', NOW()
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tutor_configuracoes WHERE chave = 'tutor_ia_modelo');

-- Teto mensal em dólares. Entra em 20 e não em 0 de propósito: se ninguém
-- reparar neste campo, o pior que acontece é a IA parar de responder ao chegar
-- em US$ 20 e o chat voltar ao modo determinístico. O contrário — teto zerado
-- lido como "sem limite" — deixaria uma conta aberta sem vigia.
INSERT INTO tutor_configuracoes (chave, valor, atualizado_em)
SELECT 'tutor_ia_teto_mensal_usd', '20', NOW()
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tutor_configuracoes WHERE chave = 'tutor_ia_teto_mensal_usd');

-- =====================================================================
-- 076 — Norminha: configurações da camada de IA (Etapa 14)
-- Docs: docs/norminha/CONTEXTO-EXECUCAO.md
--
-- `tutor_ia_ativo` entra com valor 0 DE PROPÓSITO. O plano exige rollout
-- controlado: mesmo com a chave configurada e o provedor habilitado no .env,
-- a Norminha só usa IA quando alguém liga conscientemente nesta tela.
--
-- São duas chaves independentes, e isso é intencional:
--   OPENAI_ENABLED (.env)  — a integração existe? Decisão de infraestrutura.
--   tutor_ia_ativo (banco) — a Norminha usa? Decisão de produto, reversível
--                            em um clique, sem deploy.
--
-- Aditiva e idempotente, no padrão de 053: INSERT ... WHERE NOT EXISTS, que não
-- depende do índice único e nunca sobrescreve valor já ajustado.
-- =====================================================================

INSERT INTO tutor_configuracoes (chave, valor, atualizado_em)
SELECT 'tutor_ia_ativo', '0', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tutor_configuracoes WHERE chave = 'tutor_ia_ativo');

INSERT INTO tutor_configuracoes (chave, valor, atualizado_em)
SELECT 'tutor_ia_max_output_tokens', '1200', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tutor_configuracoes WHERE chave = 'tutor_ia_max_output_tokens');

INSERT INTO tutor_configuracoes (chave, valor, atualizado_em)
SELECT 'tutor_ia_prompt_complementar', '', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tutor_configuracoes WHERE chave = 'tutor_ia_prompt_complementar');

-- =====================================================================
-- 075 — Norminha: chaves de limite do rate limit
-- Docs: docs/norminha/17-deploy-onda-0.md
--
-- O NorminhaRateLimitService já lia estas chaves de tutor_configuracoes, com
-- queda para os padrões quando ausentes. Elas passam a existir de fato, para
-- aparecerem preenchidas na tela de configurações do admin — e para que
-- ajustar o limite durante um pico não dependa de acesso ao servidor.
--
-- Aditiva e idempotente, no mesmo padrão de
-- sql/053_tutor_virtual_norminha_config_seed.sql: INSERT ... WHERE NOT EXISTS,
-- que não depende do índice único existir e nunca sobrescreve um valor que o
-- administrador já ajustou.
-- =====================================================================

INSERT INTO tutor_configuracoes (chave, valor, atualizado_em)
SELECT 'tutor_ia_limite_5min', '20', NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM tutor_configuracoes WHERE chave = 'tutor_ia_limite_5min'
);

INSERT INTO tutor_configuracoes (chave, valor, atualizado_em)
SELECT 'tutor_ia_limite_diario', '200', NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM tutor_configuracoes WHERE chave = 'tutor_ia_limite_diario'
);

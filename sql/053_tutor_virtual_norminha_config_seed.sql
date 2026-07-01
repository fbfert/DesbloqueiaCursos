-- Seeds complementares das configuracoes da Norminha
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO tutor_configuracoes (chave, valor, atualizado_em)
SELECT 'tutor_ttl_fechamento_horas', '24', NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM tutor_configuracoes
    WHERE chave = 'tutor_ttl_fechamento_horas'
);

INSERT INTO tutor_configuracoes (chave, valor, atualizado_em)
SELECT 'tutor_texto_botao', 'Ouvir orientação', NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM tutor_configuracoes
    WHERE chave = 'tutor_texto_botao'
);

INSERT INTO tutor_configuracoes (chave, valor, atualizado_em)
SELECT 'tutor_titulo_padrao', 'Norminha', NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM tutor_configuracoes
    WHERE chave = 'tutor_titulo_padrao'
);

INSERT INTO tutor_configuracoes (chave, valor, atualizado_em)
SELECT 'tutor_avatar_idle', '/assets/norminha/norminha-idle.webp', NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM tutor_configuracoes
    WHERE chave = 'tutor_avatar_idle'
);

INSERT INTO tutor_configuracoes (chave, valor, atualizado_em)
SELECT 'tutor_avatar_speaking', '/assets/norminha/norminha-speaking.webp', NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM tutor_configuracoes
    WHERE chave = 'tutor_avatar_speaking'
);

SET FOREIGN_KEY_CHECKS = 1;

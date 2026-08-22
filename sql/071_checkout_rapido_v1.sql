-- Desbloqueia Cursos - Checkout rapido (tela unica + Pix direto)
-- Fase 0: fundacao. Migracao ADITIVA - nenhuma coluna e removida, nenhum
-- dado existente e alterado. Compatibilidade: MySQL 5.7
--
-- Ordem de execucao: rodar inteiro, uma unica vez, em ordem numerica.
-- Rollback: ver o bloco comentado no final do arquivo.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. usuarios: permitir cadastro iniciado sem nome e sem senha
--
-- O checkout rapido coleta e-mail, WhatsApp e CPF. Nome e senha passam a
-- ser preenchidos depois do pagamento (nome no certificado, senha opcional
-- dentro da plataforma). CPF continua NOT NULL + UNIQUE: ele e coletado no
-- formulario e a validacao publica de certificado depende dele.
-- ---------------------------------------------------------------------

ALTER TABLE usuarios
    MODIFY COLUMN nome VARCHAR(150) NULL,
    MODIFY COLUMN senha_hash VARCHAR(255) NULL,
    ADD COLUMN whatsapp VARCHAR(20) NULL AFTER telefone,
    ADD COLUMN cadastro_status VARCHAR(20) NOT NULL DEFAULT 'completo' AFTER status,
    ADD COLUMN cadastro_origem VARCHAR(40) NULL AFTER cadastro_status,
    ADD KEY idx_usuarios_cadastro_status (cadastro_status),
    ADD KEY idx_usuarios_whatsapp (whatsapp);

-- Todo usuario que ja existe continua 'completo' (default da coluna).
-- Somente registros criados pelo checkout rapido nascem 'pendente', o que
-- os mantem separaveis para exclusao a pedido (LGPD).

-- ---------------------------------------------------------------------
-- 2. participantes_pedido: nome deixa de ser obrigatorio
--
-- Em compra propria pelo checkout rapido o participante e criado junto com
-- o pedido, antes de existir nome. O nome e preenchido depois, e continua
-- obrigatorio na pratica para emitir certificado.
-- ---------------------------------------------------------------------

ALTER TABLE participantes_pedido
    MODIFY COLUMN nome VARCHAR(150) NULL;

-- ---------------------------------------------------------------------
-- 3. pedidos: origem de trafego
--
-- Gravada no momento da criacao do pedido, a partir do que foi capturado
-- na primeira visita e mantido em sessao.
-- ---------------------------------------------------------------------

ALTER TABLE pedidos
    ADD COLUMN utm_source VARCHAR(120) NULL AFTER canal_origem,
    ADD COLUMN utm_medium VARCHAR(120) NULL AFTER utm_source,
    ADD COLUMN utm_campaign VARCHAR(191) NULL AFTER utm_medium,
    ADD COLUMN utm_term VARCHAR(191) NULL AFTER utm_campaign,
    ADD COLUMN utm_content VARCHAR(191) NULL AFTER utm_term,
    ADD COLUMN gclid VARCHAR(191) NULL AFTER utm_content,
    ADD COLUMN origem_capturada_em DATETIME NULL AFTER gclid,
    ADD COLUMN conversao_enviada_em DATETIME NULL AFTER origem_capturada_em,
    ADD COLUMN conversao_status VARCHAR(30) NULL AFTER conversao_enviada_em,
    ADD KEY idx_pedidos_gclid (gclid),
    ADD KEY idx_pedidos_utm_campaign (utm_campaign),
    ADD KEY idx_pedidos_conversao_status (conversao_status);

-- ---------------------------------------------------------------------
-- 4. acessos_tokens: link magico
--
-- Tabela dedicada. NAO reaproveita usuarios.token_recuperacao, que tem
-- finalidade distinta (redefinir senha) e regras proprias de expiracao.
-- Token guardado como hash: vazamento do banco nao permite entrar.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS acessos_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    finalidade VARCHAR(40) NOT NULL DEFAULT 'acesso_curso',
    pedido_id BIGINT UNSIGNED NULL,
    inscricao_id BIGINT UNSIGNED NULL,
    destino VARCHAR(255) NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL,
    usado_ip VARCHAR(45) NULL,
    usado_user_agent VARCHAR(255) NULL,
    criado_por_evento VARCHAR(80) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_acessos_tokens_hash (token_hash),
    KEY idx_acessos_tokens_usuario (usuario_id),
    KEY idx_acessos_tokens_pedido (pedido_id),
    KEY idx_acessos_tokens_expira (expira_em),
    KEY idx_acessos_tokens_usado (usado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. configuracoes_checkout_rapido: a feature flag e os parametros
--
-- Segue o padrao das demais configuracoes_* do projeto: linha unica,
-- editavel pelo admin, com fallback para .env quando ausente.
-- ativo = 0 na instalacao: o fluxo novo nasce desligado.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS configuracoes_checkout_rapido (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ativo TINYINT(1) NOT NULL DEFAULT 0,
    cursos_habilitados VARCHAR(255) NULL,
    pix_expira_minutos INT UNSIGNED NOT NULL DEFAULT 30,
    polling_intervalo_segundos INT UNSIGNED NOT NULL DEFAULT 3,
    token_acesso_validade_horas INT UNSIGNED NOT NULL DEFAULT 168,
    google_ads_conversion_id VARCHAR(60) NULL,
    google_ads_conversion_label VARCHAR(120) NULL,
    google_ads_ativo TINYINT(1) NOT NULL DEFAULT 0,
    whatsapp_provider VARCHAR(40) NULL,
    whatsapp_ativo TINYINT(1) NOT NULL DEFAULT 0,
    texto_lgpd TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuracoes_checkout_rapido
    (id, ativo, pix_expira_minutos, polling_intervalo_segundos, token_acesso_validade_horas,
     google_ads_ativo, whatsapp_ativo, texto_lgpd, created_at)
SELECT 1, 0, 30, 3, 168, 0, 0,
       'Usamos seu e-mail e WhatsApp apenas para enviar o acesso ao curso e o comprovante da compra. Nada de spam.',
       NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracoes_checkout_rapido WHERE id = 1);

-- ---------------------------------------------------------------------
-- ROLLBACK (executar somente se precisar desfazer)
-- ---------------------------------------------------------------------
--
-- DROP TABLE IF EXISTS acessos_tokens;
-- DROP TABLE IF EXISTS configuracoes_checkout_rapido;
--
-- ALTER TABLE pedidos
--     DROP KEY idx_pedidos_conversao_status,
--     DROP KEY idx_pedidos_utm_campaign,
--     DROP KEY idx_pedidos_gclid,
--     DROP COLUMN conversao_status,
--     DROP COLUMN conversao_enviada_em,
--     DROP COLUMN origem_capturada_em,
--     DROP COLUMN gclid,
--     DROP COLUMN utm_content,
--     DROP COLUMN utm_term,
--     DROP COLUMN utm_campaign,
--     DROP COLUMN utm_medium,
--     DROP COLUMN utm_source;
--
-- ATENCAO: os MODIFY abaixo so podem voltar a NOT NULL se nao houver
-- nenhuma linha com valor nulo. Rode antes:
--   SELECT COUNT(*) FROM usuarios WHERE nome IS NULL OR senha_hash IS NULL;
--   SELECT COUNT(*) FROM participantes_pedido WHERE nome IS NULL;
--
-- ALTER TABLE usuarios
--     DROP KEY idx_usuarios_whatsapp,
--     DROP KEY idx_usuarios_cadastro_status,
--     DROP COLUMN cadastro_origem,
--     DROP COLUMN cadastro_status,
--     DROP COLUMN whatsapp,
--     MODIFY COLUMN senha_hash VARCHAR(255) NOT NULL,
--     MODIFY COLUMN nome VARCHAR(150) NOT NULL;
--
-- ALTER TABLE participantes_pedido
--     MODIFY COLUMN nome VARCHAR(150) NOT NULL;

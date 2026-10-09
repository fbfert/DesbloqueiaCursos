-- 083 — Login com Google (site e app), CPF opcional e certificados retidos aguardando CPF
-- Compatibilidade: MySQL 5.7 / MariaDB 10.5. Aditiva: CREATE TABLE IF NOT EXISTS e um MODIFY
-- que só afrouxa a coluna (nenhum dado é alterado). Pode rodar mais de uma vez.
--
-- CPF OPCIONAL
--
-- Contas criadas pelo Google não têm CPF. usuarios.cpf passa a aceitar NULL e o UNIQUE
-- continua valendo: o MySQL aceita vários NULL num índice único. O código nunca grava ''
-- em conta nova (Usuario::normalizarCpf); os '' legados são tratados como "sem CPF".
--
-- IDENTIDADES
--
-- usuario_identidades liga um usuário a um provedor externo (hoje 'google'; 'apple' depois).
-- A pessoa é identificada pelo `sub` do provedor, que não muda — não pelo e-mail.
-- provedor e sub são ASCII para o índice (provedor, sub) caber no limite de 767 bytes do
-- MySQL 5.7 sem innodb_large_prefix.
--
-- CERTIFICADOS RETIDOS
--
-- Emissão pedida pelo admin para participante sem CPF ligado a conta sem CPF não gera
-- certificado: vira uma linha aguardando_cpf com as opções da solicitação (JSON em TEXT),
-- emitida automaticamente quando o aluno informa o CPF. Uma retenção ativa por inscrição é
-- garantida no serviço (o MySQL 5.7 não tem índice parcial).
--
-- HORÁRIOS: gravados pelo PHP no fuso da aplicação, nunca com NOW() do banco (ver 082).
--
-- Rollback: DROP TABLE usuario_identidades, certificados_retidos. Voltar cpf a NOT NULL só
-- se SELECT COUNT(*) FROM usuarios WHERE cpf IS NULL der 0.

SET NAMES utf8mb4;

ALTER TABLE usuarios MODIFY cpf VARCHAR(14) NULL;

CREATE TABLE IF NOT EXISTS usuario_identidades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    provedor VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    sub VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    email VARCHAR(191) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL,
    ultimo_uso_em DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuario_identidades_sub (provedor, sub),
    UNIQUE KEY uk_usuario_identidades_usuario (usuario_id, provedor),
    CONSTRAINT fk_usuario_identidades_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certificados_retidos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    modo VARCHAR(30) NOT NULL,
    opcoes TEXT NOT NULL,
    contexto TEXT NOT NULL,
    solicitado_por BIGINT UNSIGNED NULL DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'aguardando_cpf',
    certificado_id BIGINT UNSIGNED NULL DEFAULT NULL,
    ultima_falha VARCHAR(500) NULL DEFAULT NULL,
    tentativas INT UNSIGNED NOT NULL DEFAULT 0,
    aviso_enviado_em DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_certificados_retidos_inscricao (inscricao_id, status),
    KEY idx_certificados_retidos_usuario (usuario_id, status),
    CONSTRAINT fk_certificados_retidos_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_certificados_retidos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

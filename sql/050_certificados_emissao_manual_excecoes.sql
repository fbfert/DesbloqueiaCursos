-- Desbloqueia Cursos - emissao manual com excecao administrativa de certificados
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
VALUES
    ('certificados', 'emitir_excecao', 'certificados.emitir_excecao', 'Emitir certificado com exceção administrativa', 'Permite emitir certificados mesmo quando houver pendências acadêmicas, mediante justificativa e auditoria.', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    modulo = VALUES(modulo),
    acao = VALUES(acao),
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    updated_at = NOW();

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug = 'certificados.emitir_excecao'
WHERE p.slug = 'superadmin'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

CREATE TABLE IF NOT EXISTS certificados_emissao_excecoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    certificado_id BIGINT UNSIGNED NULL,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    participante_pedido_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    emitido_por_usuario_id BIGINT UNSIGNED NOT NULL,
    justificativa TEXT NOT NULL,
    situacao_elegibilidade VARCHAR(60) NULL,
    motivos_pendencias TEXT NULL,
    snapshot_elegibilidade LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cert_excecoes_certificado (certificado_id),
    KEY idx_cert_excecoes_inscricao (inscricao_id),
    KEY idx_cert_excecoes_curso (curso_evento_id),
    KEY idx_cert_excecoes_turma (turma_id),
    KEY idx_cert_excecoes_usuario (usuario_id),
    KEY idx_cert_excecoes_emitido_por (emitido_por_usuario_id),
    KEY idx_cert_excecoes_created_at (created_at),
    CONSTRAINT fk_cert_excecoes_certificado
        FOREIGN KEY (certificado_id) REFERENCES certificados (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_cert_excecoes_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cert_excecoes_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_cert_excecoes_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_cert_excecoes_participante
        FOREIGN KEY (participante_pedido_id) REFERENCES participantes_pedido (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_cert_excecoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_cert_excecoes_emitido_por
        FOREIGN KEY (emitido_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_certificado_emissao_excepcional := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'certificados'
      AND COLUMN_NAME = 'emissao_excepcional'
);

SET @sql_add_emissao_excepcional := IF(
    @has_certificado_emissao_excepcional = 0,
    'ALTER TABLE certificados ADD COLUMN emissao_excepcional TINYINT(1) NOT NULL DEFAULT 0 AFTER substituido_por_certificado_id',
    'SELECT 1'
);
PREPARE stmt_add_emissao_excepcional FROM @sql_add_emissao_excepcional;
EXECUTE stmt_add_emissao_excepcional;
DEALLOCATE PREPARE stmt_add_emissao_excepcional;

SET @has_certificado_emissao_justificativa := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'certificados'
      AND COLUMN_NAME = 'emissao_excepcional_justificativa'
);

SET @sql_add_emissao_justificativa := IF(
    @has_certificado_emissao_justificativa = 0,
    'ALTER TABLE certificados ADD COLUMN emissao_excepcional_justificativa TEXT NULL AFTER emissao_excepcional',
    'SELECT 1'
);
PREPARE stmt_add_emissao_justificativa FROM @sql_add_emissao_justificativa;
EXECUTE stmt_add_emissao_justificativa;
DEALLOCATE PREPARE stmt_add_emissao_justificativa;

SET FOREIGN_KEY_CHECKS = 1;

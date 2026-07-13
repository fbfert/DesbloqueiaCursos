-- 064_turma_emails.sql
-- Comunicados por e-mail enviados para os alunos de uma turma.
-- Cada linha e um lote (campanha): assunto + corpo editados na hora do envio.
-- Os destinatarios individuais ficam em emails_envios com
-- entidade_tipo = 'turma_email' e entidade_id = turma_emails.id.
-- Compativel com MySQL 5.7.

CREATE TABLE IF NOT EXISTS turma_emails (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    turma_id BIGINT UNSIGNED NOT NULL,
    assunto VARCHAR(255) NOT NULL,
    corpo_html MEDIUMTEXT NOT NULL,
    total_destinatarios INT UNSIGNED NOT NULL DEFAULT 0,
    criado_por_usuario_id BIGINT UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_turma_emails_turma (turma_id, id),
    KEY idx_turma_emails_criado_por (criado_por_usuario_id),
    CONSTRAINT fk_turma_emails_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_turma_emails_usuario
        FOREIGN KEY (criado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

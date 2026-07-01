-- Tutor Virtual Norminha
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS tutor_falas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    contexto VARCHAR(80) NOT NULL,
    rota VARCHAR(255) NULL,
    curso_id INT NULL,
    modulo_id INT NULL,
    aula_id INT NULL,
    texto TEXT NOT NULL,
    audio_url VARCHAR(500) NULL,
    estado_avatar VARCHAR(50) DEFAULT 'speaking',
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL,
    KEY idx_tutor_falas_contexto (contexto),
    KEY idx_tutor_falas_rota (rota),
    KEY idx_tutor_falas_curso_id (curso_id),
    KEY idx_tutor_falas_modulo_id (modulo_id),
    KEY idx_tutor_falas_aula_id (aula_id),
    KEY idx_tutor_falas_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutor_configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT NULL,
    atualizado_em DATETIME NULL,
    KEY idx_tutor_configuracoes_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tutor_configuracoes (chave, valor, atualizado_em)
VALUES ('tutor_ativo', '1', NOW());

INSERT IGNORE INTO tutor_configuracoes (chave, valor, atualizado_em)
VALUES ('tutor_home', '1', NOW());

INSERT IGNORE INTO tutor_configuracoes (chave, valor, atualizado_em)
VALUES ('tutor_area_aluno', '1', NOW());

INSERT IGNORE INTO tutor_configuracoes (chave, valor, atualizado_em)
VALUES ('tutor_cursos', '1', NOW());

INSERT IGNORE INTO tutor_configuracoes (chave, valor, atualizado_em)
VALUES ('tutor_checkout', '0', NOW());

INSERT IGNORE INTO tutor_configuracoes (chave, valor, atualizado_em)
VALUES ('tutor_minimizado_padrao', '0', NOW());

SET FOREIGN_KEY_CHECKS = 1;

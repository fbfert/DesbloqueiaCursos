-- Polo Rainbow - atividades simples da area interna do curso
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS atividades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    modulo_id BIGINT UNSIGNED NOT NULL,
    aula_id BIGINT UNSIGNED NOT NULL,
    titulo VARCHAR(191) NOT NULL,
    descricao LONGTEXT NULL,
    tipo_entrega VARCHAR(40) NOT NULL DEFAULT 'texto',
    prazo DATETIME NULL,
    nota_maxima DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    visivel TINYINT(1) NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'publicado',
    criado_por BIGINT UNSIGNED NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_atividades_curso (curso_evento_id),
    KEY idx_atividades_turma (turma_id),
    KEY idx_atividades_modulo (modulo_id),
    KEY idx_atividades_aula (aula_id),
    KEY idx_atividades_tipo_entrega (tipo_entrega),
    KEY idx_atividades_status (status),
    KEY idx_atividades_ordem (ordem),
    KEY idx_atividades_deleted_at (deleted_at),
    CONSTRAINT fk_atividades_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_atividades_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_atividades_modulo
        FOREIGN KEY (modulo_id) REFERENCES modulos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_atividades_aula
        FOREIGN KEY (aula_id) REFERENCES aulas (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_atividades_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_atividades_atualizado_por
        FOREIGN KEY (atualizado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atividades_entregas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atividade_id BIGINT UNSIGNED NOT NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    modulo_id BIGINT UNSIGNED NOT NULL,
    aula_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    resposta_texto LONGTEXT NULL,
    arquivo_nome_original VARCHAR(255) NULL,
    arquivo_nome_fisico VARCHAR(255) NULL,
    arquivo_caminho VARCHAR(255) NULL,
    arquivo_mime VARCHAR(120) NULL,
    arquivo_tamanho BIGINT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'enviada',
    nota DECIMAL(5,2) NULL,
    feedback LONGTEXT NULL,
    corrigido_por BIGINT UNSIGNED NULL,
    entregue_em DATETIME NULL,
    corrigido_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_atividades_entregas_usuario (atividade_id, usuario_id),
    KEY idx_atividades_entregas_atividade (atividade_id),
    KEY idx_atividades_entregas_curso (curso_evento_id),
    KEY idx_atividades_entregas_turma (turma_id),
    KEY idx_atividades_entregas_modulo (modulo_id),
    KEY idx_atividades_entregas_aula (aula_id),
    KEY idx_atividades_entregas_usuario_idx (usuario_id),
    KEY idx_atividades_entregas_status (status),
    KEY idx_atividades_entregas_deleted_at (deleted_at),
    CONSTRAINT fk_atividades_entregas_atividade
        FOREIGN KEY (atividade_id) REFERENCES atividades (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_atividades_entregas_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_atividades_entregas_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_atividades_entregas_modulo
        FOREIGN KEY (modulo_id) REFERENCES modulos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_atividades_entregas_aula
        FOREIGN KEY (aula_id) REFERENCES aulas (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_atividades_entregas_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_atividades_entregas_corrigido_por
        FOREIGN KEY (corrigido_por) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

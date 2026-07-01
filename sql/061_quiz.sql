-- Desbloqueia Cursos - Sistema nativo de Quizzes no LMS
-- Compatibilidade: MySQL 5.7
-- Todas as operacoes sao idempotentes (IF NOT EXISTS / SET @sql...)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Adicionar tipo 'quiz' ao ENUM de conteudo_itens
-- Recria o ENUM preservando todos os valores existentes
SET @sql = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'conteudo_itens'
              AND COLUMN_NAME  = 'tipo'
              AND COLUMN_TYPE LIKE '%quiz%'
        ),
        'SELECT ''conteudo_itens.tipo ja tem quiz'' AS mensagem',
        'ALTER TABLE conteudo_itens MODIFY tipo ENUM(''etiqueta'',''texto'',''arquivo'',''link'',''avaliacao_textual'',''video'',''quiz'') NOT NULL'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Tabela principal do quiz (detalhe do item)
CREATE TABLE IF NOT EXISTS conteudo_quizzes (
    id                            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id                       BIGINT UNSIGNED NOT NULL,
    instrucoes                    TEXT NULL,
    tentativas_maximas            INT UNSIGNED NULL COMMENT 'NULL = ilimitadas',
    percentual_minimo             DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    exige_aprovacao               TINYINT(1) NOT NULL DEFAULT 0,
    exibir_resultado_apos_envio   TINYINT(1) NOT NULL DEFAULT 1,
    exibir_gabarito_apos_envio    TINYINT(1) NOT NULL DEFAULT 1,
    exibir_comentarios_apos_envio TINYINT(1) NOT NULL DEFAULT 1,
    embaralhar_perguntas          TINYINT(1) NOT NULL DEFAULT 0,
    embaralhar_alternativas       TINYINT(1) NOT NULL DEFAULT 0,
    created_at                    DATETIME NULL,
    updated_at                    DATETIME NULL,
    deleted_at                    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_conteudo_quizzes_item (item_id),
    KEY idx_conteudo_quizzes_deleted_at (deleted_at),
    CONSTRAINT fk_conteudo_quizzes_item
        FOREIGN KEY (item_id) REFERENCES conteudo_itens (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Perguntas do quiz
CREATE TABLE IF NOT EXISTS conteudo_quiz_perguntas (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quiz_id     BIGINT UNSIGNED NOT NULL,
    enunciado   LONGTEXT NOT NULL,
    tipo        ENUM('multipla_escolha') NOT NULL DEFAULT 'multipla_escolha',
    explicacao  TEXT NULL,
    peso        DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    obrigatoria TINYINT(1) NOT NULL DEFAULT 1,
    ordem       INT NOT NULL DEFAULT 0,
    created_at  DATETIME NULL,
    updated_at  DATETIME NULL,
    deleted_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cqp_quiz_id (quiz_id),
    KEY idx_cqp_ordem (ordem),
    KEY idx_cqp_deleted_at (deleted_at),
    CONSTRAINT fk_cqp_quiz
        FOREIGN KEY (quiz_id) REFERENCES conteudo_quizzes (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Alternativas das perguntas
CREATE TABLE IF NOT EXISTS conteudo_quiz_alternativas (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pergunta_id  BIGINT UNSIGNED NOT NULL,
    texto        TEXT NOT NULL,
    correta      TINYINT(1) NOT NULL DEFAULT 0,
    ordem        INT NOT NULL DEFAULT 0,
    created_at   DATETIME NULL,
    updated_at   DATETIME NULL,
    deleted_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cqa_pergunta_id (pergunta_id),
    KEY idx_cqa_correta (correta),
    KEY idx_cqa_ordem (ordem),
    KEY idx_cqa_deleted_at (deleted_at),
    CONSTRAINT fk_cqa_pergunta
        FOREIGN KEY (pergunta_id) REFERENCES conteudo_quiz_perguntas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tentativas dos alunos
CREATE TABLE IF NOT EXISTS conteudo_quiz_tentativas (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quiz_id            BIGINT UNSIGNED NOT NULL,
    curso_evento_id    BIGINT UNSIGNED NOT NULL,
    turma_id           BIGINT UNSIGNED NULL,
    inscricao_id       BIGINT UNSIGNED NOT NULL,
    aluno_id           BIGINT UNSIGNED NOT NULL,
    numero_tentativa   INT UNSIGNED NOT NULL DEFAULT 1,
    status             ENUM('em_andamento','enviada','corrigida','cancelada') NOT NULL DEFAULT 'em_andamento',
    total_perguntas    INT UNSIGNED NOT NULL DEFAULT 0,
    total_acertos      INT UNSIGNED NOT NULL DEFAULT 0,
    pontos_obtidos     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pontos_totais      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    percentual         DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    aprovado           TINYINT(1) NULL,
    quiz_snapshot_json LONGTEXT NULL COMMENT 'Snapshot JSON do quiz no momento da tentativa',
    iniciada_em        DATETIME NULL,
    enviada_em         DATETIME NULL,
    corrigida_em       DATETIME NULL,
    created_at         DATETIME NULL,
    updated_at         DATETIME NULL,
    deleted_at         DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cqt_quiz_inscricao_numero (quiz_id, inscricao_id, numero_tentativa),
    KEY idx_cqt_quiz_id (quiz_id),
    KEY idx_cqt_inscricao_id (inscricao_id),
    KEY idx_cqt_aluno_id (aluno_id),
    KEY idx_cqt_status (status),
    KEY idx_cqt_deleted_at (deleted_at),
    CONSTRAINT fk_cqt_quiz
        FOREIGN KEY (quiz_id) REFERENCES conteudo_quizzes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cqt_curso_evento
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cqt_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cqt_aluno
        FOREIGN KEY (aluno_id) REFERENCES usuarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Respostas das tentativas (uma por pergunta)
CREATE TABLE IF NOT EXISTS conteudo_quiz_respostas (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tentativa_id          BIGINT UNSIGNED NOT NULL,
    pergunta_id           BIGINT UNSIGNED NOT NULL,
    alternativa_id        BIGINT UNSIGNED NULL,
    resposta_json         LONGTEXT NULL,
    correta               TINYINT(1) NOT NULL DEFAULT 0,
    pontos_obtidos        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pergunta_snapshot_json LONGTEXT NULL,
    created_at            DATETIME NULL,
    updated_at            DATETIME NULL,
    deleted_at            DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cqr_tentativa_pergunta (tentativa_id, pergunta_id),
    KEY idx_cqr_tentativa_id (tentativa_id),
    KEY idx_cqr_pergunta_id (pergunta_id),
    KEY idx_cqr_alternativa_id (alternativa_id),
    KEY idx_cqr_deleted_at (deleted_at),
    CONSTRAINT fk_cqr_tentativa
        FOREIGN KEY (tentativa_id) REFERENCES conteudo_quiz_tentativas (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cqr_pergunta
        FOREIGN KEY (pergunta_id) REFERENCES conteudo_quiz_perguntas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabela de auditoria/migracao de quizzes legados
CREATE TABLE IF NOT EXISTS conteudo_quiz_migracoes_legacy (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id         BIGINT UNSIGNED NOT NULL,
    quiz_id         BIGINT UNSIGNED NULL,
    conteudo_texto_id BIGINT UNSIGNED NULL,
    html_original   LONGTEXT NULL,
    hash_origem     VARCHAR(64) NOT NULL,
    status          ENUM('pendente','migrado','erro','ignorado','divergente') NOT NULL DEFAULT 'pendente',
    detalhes_erro   TEXT NULL,
    migrado_em      DATETIME NULL,
    created_at      DATETIME NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cqml_hash (hash_origem),
    KEY idx_cqml_item_id (item_id),
    KEY idx_cqml_quiz_id (quiz_id),
    KEY idx_cqml_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'conteudo_quiz_migracoes_legacy'
              AND COLUMN_NAME  = 'status'
              AND COLUMN_TYPE LIKE '%divergente%'
        ),
        'SELECT ''conteudo_quiz_migracoes_legacy.status ja tem divergente'' AS mensagem',
        'ALTER TABLE conteudo_quiz_migracoes_legacy MODIFY status ENUM(''pendente'',''migrado'',''erro'',''ignorado'',''divergente'') NOT NULL DEFAULT ''pendente'''
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- Polo Rainbow - presenca, avaliacao, progresso final e aptidao para certificado
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE cursos_eventos
    ADD COLUMN exige_presenca TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN percentual_minimo_presenca DECIMAL(5,2) NOT NULL DEFAULT 75.00 AFTER exige_presenca,
    ADD COLUMN percentual_minimo_conclusao DECIMAL(5,2) NOT NULL DEFAULT 75.00 AFTER percentual_minimo_presenca,
    ADD COLUMN exige_avaliacao TINYINT(1) NOT NULL DEFAULT 0 AFTER percentual_minimo_conclusao,
    ADD COLUMN nota_minima DECIMAL(5,2) NOT NULL DEFAULT 70.00 AFTER exige_avaliacao,
    ADD COLUMN progresso_base VARCHAR(20) NOT NULL DEFAULT 'aulas' AFTER nota_minima;

ALTER TABLE turmas
    ADD COLUMN exige_presenca TINYINT(1) NULL AFTER vagas,
    ADD COLUMN percentual_minimo_presenca DECIMAL(5,2) NULL AFTER exige_presenca,
    ADD COLUMN percentual_minimo_conclusao DECIMAL(5,2) NULL AFTER percentual_minimo_presenca,
    ADD COLUMN exige_avaliacao TINYINT(1) NULL AFTER percentual_minimo_conclusao,
    ADD COLUMN nota_minima DECIMAL(5,2) NULL AFTER exige_avaliacao,
    ADD COLUMN progresso_base VARCHAR(20) NULL AFTER nota_minima;

CREATE TABLE IF NOT EXISTS presencas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    pedido_id BIGINT UNSIGNED NOT NULL,
    pedido_item_id BIGINT UNSIGNED NOT NULL,
    participante_pedido_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    aula_id BIGINT UNSIGNED NULL,
    data_presenca DATE NOT NULL,
    status ENUM('presente', 'ausente', 'justificada') NOT NULL DEFAULT 'presente',
    observacao VARCHAR(500) NULL,
    marcado_por_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_presencas_contexto (inscricao_id, participante_pedido_id, aula_id, data_presenca),
    KEY idx_presencas_inscricao (inscricao_id),
    KEY idx_presencas_pedido (pedido_id),
    KEY idx_presencas_participante (participante_pedido_id),
    KEY idx_presencas_usuario (usuario_id),
    KEY idx_presencas_curso (curso_evento_id),
    KEY idx_presencas_turma (turma_id),
    KEY idx_presencas_aula (aula_id),
    KEY idx_presencas_status (status),
    KEY idx_presencas_created_at (created_at),
    KEY idx_presencas_deleted_at (deleted_at),
    CONSTRAINT fk_presencas_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_presencas_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_presencas_pedido_item
        FOREIGN KEY (pedido_item_id) REFERENCES pedido_itens (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_presencas_participante
        FOREIGN KEY (participante_pedido_id) REFERENCES participantes_pedido (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_presencas_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_presencas_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_presencas_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_presencas_aula
        FOREIGN KEY (aula_id) REFERENCES aulas (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_presencas_marcado_por
        FOREIGN KEY (marcado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS avaliacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(191) NOT NULL,
    descricao TEXT NULL,
    tipo VARCHAR(40) NOT NULL DEFAULT 'avaliacao',
    visivel TINYINT(1) NOT NULL DEFAULT 1,
    obrigatoria TINYINT(1) NOT NULL DEFAULT 0,
    percentual_minimo DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    nota_minima DECIMAL(5,2) NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_avaliacoes_curso (curso_evento_id),
    KEY idx_avaliacoes_turma (turma_id),
    KEY idx_avaliacoes_visivel (visivel),
    KEY idx_avaliacoes_obrigatoria (obrigatoria),
    KEY idx_avaliacoes_ordem (ordem),
    KEY idx_avaliacoes_deleted_at (deleted_at),
    CONSTRAINT fk_avaliacoes_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_avaliacoes_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS avaliacao_perguntas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    avaliacao_id BIGINT UNSIGNED NOT NULL,
    enunciado TEXT NOT NULL,
    tipo_resposta VARCHAR(40) NOT NULL DEFAULT 'dissertativa',
    opcoes_json LONGTEXT NULL,
    obrigatoria TINYINT(1) NOT NULL DEFAULT 0,
    ordem INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_avaliacao_perguntas_avaliacao (avaliacao_id),
    KEY idx_avaliacao_perguntas_ordem (ordem),
    KEY idx_avaliacao_perguntas_deleted_at (deleted_at),
    CONSTRAINT fk_avaliacao_perguntas_avaliacao
        FOREIGN KEY (avaliacao_id) REFERENCES avaliacoes (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS avaliacao_respostas_usuario (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    avaliacao_id BIGINT UNSIGNED NOT NULL,
    pergunta_id BIGINT UNSIGNED NOT NULL,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    resposta_texto LONGTEXT NULL,
    resposta_json LONGTEXT NULL,
    pontuacao DECIMAL(5,2) NULL,
    corrigida_por_usuario_id BIGINT UNSIGNED NULL,
    corrigida_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_avaliacao_respostas_contexto (avaliacao_id, pergunta_id, inscricao_id, usuario_id),
    KEY idx_avaliacao_respostas_avaliacao (avaliacao_id),
    KEY idx_avaliacao_respostas_pergunta (pergunta_id),
    KEY idx_avaliacao_respostas_inscricao (inscricao_id),
    KEY idx_avaliacao_respostas_usuario (usuario_id),
    KEY idx_avaliacao_respostas_deleted_at (deleted_at),
    CONSTRAINT fk_avaliacao_respostas_avaliacao
        FOREIGN KEY (avaliacao_id) REFERENCES avaliacoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_avaliacao_respostas_pergunta
        FOREIGN KEY (pergunta_id) REFERENCES avaliacao_perguntas (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_avaliacao_respostas_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_avaliacao_respostas_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_avaliacao_respostas_corrigida_por
        FOREIGN KEY (corrigida_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notas_avaliacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    avaliacao_id BIGINT UNSIGNED NOT NULL,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    nota DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    status ENUM('pendente', 'corrigida', 'aprovada', 'reprovada') NOT NULL DEFAULT 'pendente',
    observacao TEXT NULL,
    corrigida_por_usuario_id BIGINT UNSIGNED NULL,
    corrigida_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_notas_avaliacoes_contexto (avaliacao_id, inscricao_id, usuario_id),
    KEY idx_notas_avaliacoes_avaliacao (avaliacao_id),
    KEY idx_notas_avaliacoes_inscricao (inscricao_id),
    KEY idx_notas_avaliacoes_usuario (usuario_id),
    KEY idx_notas_avaliacoes_status (status),
    KEY idx_notas_avaliacoes_deleted_at (deleted_at),
    CONSTRAINT fk_notas_avaliacoes_avaliacao
        FOREIGN KEY (avaliacao_id) REFERENCES avaliacoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_notas_avaliacoes_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_notas_avaliacoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_notas_avaliacoes_corrigida_por
        FOREIGN KEY (corrigida_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
VALUES
    ('academico', 'ver', 'academico.ver', 'Ver area academica', 'Visualizar presenca, avaliacao e aptidao', NOW(), NOW(), NULL),
    ('academico', 'gerenciar', 'academico.gerenciar', 'Gerenciar area academica', 'Gerenciar presenca, avaliacao e aptidao', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    updated_at = NOW();

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('academico.ver', 'academico.gerenciar')
WHERE p.slug = 'superadmin'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('academico.ver', 'academico.gerenciar')
WHERE p.slug = 'conteudo'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug = 'academico.ver'
WHERE p.slug = 'atendimento'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('academico.ver', 'academico.gerenciar')
WHERE p.slug = 'professor'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

SET FOREIGN_KEY_CHECKS = 1;

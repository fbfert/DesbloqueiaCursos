-- Polo Rainbow - area interna do curso
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS instrucoes_curso (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(191) NOT NULL,
    conteudo LONGTEXT NULL,
    visivel TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_instrucoes_curso_contexto (curso_evento_id, turma_id),
    KEY idx_instrucoes_curso_curso (curso_evento_id),
    KEY idx_instrucoes_curso_turma (turma_id),
    KEY idx_instrucoes_curso_visivel (visivel),
    KEY idx_instrucoes_curso_deleted_at (deleted_at),
    CONSTRAINT fk_instrucoes_curso_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_instrucoes_curso_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modulos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(191) NOT NULL,
    descricao TEXT NULL,
    visivel TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_modulos_curso (curso_evento_id),
    KEY idx_modulos_turma (turma_id),
    KEY idx_modulos_visivel (visivel),
    KEY idx_modulos_ordem (ordem),
    KEY idx_modulos_deleted_at (deleted_at),
    CONSTRAINT fk_modulos_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_modulos_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aulas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    modulo_id BIGINT UNSIGNED NOT NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(191) NOT NULL,
    conteudo LONGTEXT NULL,
    tipo VARCHAR(40) NOT NULL DEFAULT 'texto',
    url_video VARCHAR(255) NULL,
    duracao_minutos INT UNSIGNED NULL,
    visivel TINYINT(1) NOT NULL DEFAULT 1,
    obrigatoria TINYINT(1) NOT NULL DEFAULT 0,
    ordem INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_aulas_modulo (modulo_id),
    KEY idx_aulas_curso (curso_evento_id),
    KEY idx_aulas_turma (turma_id),
    KEY idx_aulas_visivel (visivel),
    KEY idx_aulas_ordem (ordem),
    KEY idx_aulas_deleted_at (deleted_at),
    CONSTRAINT fk_aulas_modulo
        FOREIGN KEY (modulo_id) REFERENCES modulos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_aulas_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_aulas_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materiais (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    modulo_id BIGINT UNSIGNED NULL,
    aula_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(191) NOT NULL,
    descricao VARCHAR(500) NULL,
    arquivo_caminho VARCHAR(255) NOT NULL,
    arquivo_nome_original VARCHAR(255) NULL,
    arquivo_mime_type VARCHAR(120) NULL,
    arquivo_tamanho_bytes BIGINT UNSIGNED NULL,
    tipo_arquivo VARCHAR(40) NOT NULL DEFAULT 'outro',
    visivel TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_materiais_curso (curso_evento_id),
    KEY idx_materiais_turma (turma_id),
    KEY idx_materiais_modulo (modulo_id),
    KEY idx_materiais_aula (aula_id),
    KEY idx_materiais_visivel (visivel),
    KEY idx_materiais_ordem (ordem),
    KEY idx_materiais_deleted_at (deleted_at),
    CONSTRAINT fk_materiais_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_materiais_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_materiais_modulo
        FOREIGN KEY (modulo_id) REFERENCES modulos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_materiais_aula
        FOREIGN KEY (aula_id) REFERENCES aulas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS links_externos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    modulo_id BIGINT UNSIGNED NULL,
    aula_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(191) NOT NULL,
    url VARCHAR(255) NOT NULL,
    tipo_link VARCHAR(40) NOT NULL DEFAULT 'generico',
    visivel TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_links_externos_curso (curso_evento_id),
    KEY idx_links_externos_turma (turma_id),
    KEY idx_links_externos_modulo (modulo_id),
    KEY idx_links_externos_aula (aula_id),
    KEY idx_links_externos_visivel (visivel),
    KEY idx_links_externos_ordem (ordem),
    KEY idx_links_externos_deleted_at (deleted_at),
    CONSTRAINT fk_links_externos_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_links_externos_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_links_externos_modulo
        FOREIGN KEY (modulo_id) REFERENCES modulos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_links_externos_aula
        FOREIGN KEY (aula_id) REFERENCES aulas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS progresso_usuario_modulos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    modulo_id BIGINT UNSIGNED NOT NULL,
    percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    concluido TINYINT(1) NOT NULL DEFAULT 0,
    concluido_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_progresso_usuario_modulo (inscricao_id, usuario_id, modulo_id),
    KEY idx_progresso_usuario_modulos_inscricao (inscricao_id),
    KEY idx_progresso_usuario_modulos_usuario (usuario_id),
    KEY idx_progresso_usuario_modulos_curso (curso_evento_id),
    KEY idx_progresso_usuario_modulos_turma (turma_id),
    KEY idx_progresso_usuario_modulos_modulo (modulo_id),
    CONSTRAINT fk_progresso_usuario_modulos_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_progresso_usuario_modulos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_progresso_usuario_modulos_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_progresso_usuario_modulos_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_progresso_usuario_modulos_modulo
        FOREIGN KEY (modulo_id) REFERENCES modulos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS progresso_usuario_aulas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    aula_id BIGINT UNSIGNED NOT NULL,
    percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    concluido TINYINT(1) NOT NULL DEFAULT 0,
    visualizado_em DATETIME NULL,
    concluido_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_progresso_usuario_aula (inscricao_id, usuario_id, aula_id),
    KEY idx_progresso_usuario_aulas_inscricao (inscricao_id),
    KEY idx_progresso_usuario_aulas_usuario (usuario_id),
    KEY idx_progresso_usuario_aulas_curso (curso_evento_id),
    KEY idx_progresso_usuario_aulas_turma (turma_id),
    KEY idx_progresso_usuario_aulas_aula (aula_id),
    CONSTRAINT fk_progresso_usuario_aulas_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_progresso_usuario_aulas_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_progresso_usuario_aulas_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_progresso_usuario_aulas_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_progresso_usuario_aulas_aula
        FOREIGN KEY (aula_id) REFERENCES aulas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
VALUES
    ('area_curso', 'ver', 'area_curso.ver', 'Ver area do curso', 'Visualizar a area interna do curso', NOW(), NOW(), NULL),
    ('area_curso', 'gerenciar', 'area_curso.gerenciar', 'Gerenciar area do curso', 'Administrar instrucoes, modulos, aulas, materiais e links', NOW(), NOW(), NULL),
    ('area_curso_professor', 'ver', 'area_curso.professor.ver', 'Ver area do professor', 'Acessar a area interna com escopo de professor', NOW(), NOW(), NULL),
    ('area_curso_professor', 'gerenciar', 'area_curso.professor.gerenciar', 'Gerenciar area do professor', 'Administrar conteudo da area interna como professor', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    updated_at = NOW();

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('area_curso.ver', 'area_curso.gerenciar', 'area_curso.professor.ver', 'area_curso.professor.gerenciar')
WHERE p.slug = 'superadmin'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('area_curso.ver', 'area_curso.professor.ver', 'area_curso.professor.gerenciar')
WHERE p.slug = 'conteudo'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('area_curso.professor.ver', 'area_curso.professor.gerenciar')
WHERE p.slug = 'professor'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug = 'area_curso.ver'
WHERE p.slug = 'atendimento'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

SET FOREIGN_KEY_CHECKS = 1;

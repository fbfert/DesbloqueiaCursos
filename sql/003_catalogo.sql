-- Polo Rainbow - catalogo de cursos e eventos
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS categorias (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    descricao VARCHAR(255) NULL,
    parent_id INT UNSIGNED NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_categorias_slug (slug),
    KEY idx_categorias_parent (parent_id),
    KEY idx_categorias_status (status),
    KEY idx_categorias_deleted_at (deleted_at),
    CONSTRAINT fk_categorias_parent
        FOREIGN KEY (parent_id) REFERENCES categorias (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cursos_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    categoria_id INT UNSIGNED NULL,
    nome VARCHAR(191) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    tipo ENUM('curso', 'evento') NOT NULL DEFAULT 'curso',
    modalidade VARCHAR(40) NOT NULL DEFAULT 'presencial',
    thumbnail VARCHAR(255) NULL,
    descricao_curta VARCHAR(500) NULL,
    descricao_completa LONGTEXT NULL,
    carga_horaria SMALLINT UNSIGNED NULL,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    em_promocao TINYINT(1) NOT NULL DEFAULT 0,
    destaque TINYINT(1) NOT NULL DEFAULT 0,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('rascunho', 'ativo', 'inativo', 'arquivado') NOT NULL DEFAULT 'rascunho',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cursos_eventos_slug (slug),
    KEY idx_cursos_eventos_categoria (categoria_id),
    KEY idx_cursos_eventos_tipo (tipo),
    KEY idx_cursos_eventos_modalidade (modalidade),
    KEY idx_cursos_eventos_status (status),
    KEY idx_cursos_eventos_destaque (destaque),
    KEY idx_cursos_eventos_ordem (ordem),
    KEY idx_cursos_eventos_deleted_at (deleted_at),
    CONSTRAINT fk_cursos_eventos_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS turmas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(191) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    codigo VARCHAR(80) NOT NULL,
    data_inicio DATE NULL,
    data_fim DATE NULL,
    vagas INT UNSIGNED NULL,
    status ENUM('planejada', 'aberta', 'encerrada', 'excluida') NOT NULL DEFAULT 'planejada',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_turmas_slug (slug),
    UNIQUE KEY uk_turmas_codigo (codigo),
    KEY idx_turmas_curso_evento (curso_evento_id),
    KEY idx_turmas_status (status),
    KEY idx_turmas_data_inicio (data_inicio),
    KEY idx_turmas_deleted_at (deleted_at),
    CONSTRAINT fk_turmas_curso_evento
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS curso_pessoas_vinculadas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    nome VARCHAR(191) NOT NULL,
    tipo_pessoa ENUM('professor', 'tutor', 'mediador', 'palestrante') NOT NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_curso_pessoas_curso (curso_evento_id),
    KEY idx_curso_pessoas_usuario (usuario_id),
    KEY idx_curso_pessoas_tipo (tipo_pessoa),
    KEY idx_curso_pessoas_status (status),
    KEY idx_curso_pessoas_deleted_at (deleted_at),
    CONSTRAINT fk_curso_pessoas_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_curso_pessoas_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_cursos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    tipo_vinculo ENUM('professor', 'tutor', 'mediador', 'palestrante') NOT NULL DEFAULT 'professor',
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuario_cursos (usuario_id, curso_evento_id, tipo_vinculo),
    KEY idx_usuario_cursos_curso (curso_evento_id),
    KEY idx_usuario_cursos_tipo (tipo_vinculo),
    KEY idx_usuario_cursos_status (status),
    KEY idx_usuario_cursos_deleted_at (deleted_at),
    CONSTRAINT fk_usuario_cursos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_usuario_cursos_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_turmas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NOT NULL,
    tipo_vinculo ENUM('professor', 'tutor', 'mediador', 'palestrante') NOT NULL DEFAULT 'professor',
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuario_turmas (usuario_id, turma_id, tipo_vinculo),
    KEY idx_usuario_turmas_turma (turma_id),
    KEY idx_usuario_turmas_tipo (tipo_vinculo),
    KEY idx_usuario_turmas_status (status),
    KEY idx_usuario_turmas_deleted_at (deleted_at),
    CONSTRAINT fk_usuario_turmas_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_usuario_turmas_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cursos_destaque (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cursos_destaque_curso (curso_evento_id),
    KEY idx_cursos_destaque_ordem (ordem),
    KEY idx_cursos_destaque_status (status),
    KEY idx_cursos_destaque_deleted_at (deleted_at),
    CONSTRAINT fk_cursos_destaque_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
VALUES
    ('catalogo', 'ver', 'catalogo.ver', 'Ver catalogo', 'Acessar listagem do catalogo', NOW(), NOW(), NULL),
    ('catalogo', 'gerenciar', 'catalogo.gerenciar', 'Gerenciar catalogo', 'Administrar catalogo de cursos e eventos', NOW(), NOW(), NULL),
    ('catalogo', 'professor.ver', 'catalogo.professor.ver', 'Ver area do professor', 'Acessar area restrita do professor', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    updated_at = NOW();

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('catalogo.ver', 'catalogo.gerenciar')
WHERE p.slug = 'conteudo'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug = 'catalogo.professor.ver'
WHERE p.slug = 'professor'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
CROSS JOIN permissoes per
WHERE p.slug = 'superadmin'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

SET FOREIGN_KEY_CHECKS = 1;

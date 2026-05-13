-- Polo Rainbow - avisos administrativos e destinatários
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS avisos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id INT UNSIGNED NULL,
    usuario_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(180) NULL,
    mensagem TEXT NOT NULL,
    tipo_destino VARCHAR(50) NOT NULL,
    mostrar_inicio DATETIME NULL,
    mostrar_fim DATETIME NULL,
    permitir_ocultar TINYINT(1) NOT NULL DEFAULT 1,
    status VARCHAR(30) NOT NULL DEFAULT 'rascunho',
    origem VARCHAR(30) NOT NULL DEFAULT 'manual',
    gatilho VARCHAR(100) NULL,
    prioridade INT NOT NULL DEFAULT 0,
    link_url VARCHAR(255) NULL,
    link_rotulo VARCHAR(80) NULL,
    destaque TINYINT(1) NOT NULL DEFAULT 0,
    criado_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    editado_por BIGINT UNSIGNED NULL,
    editado_em DATETIME NULL,
    enviado_por BIGINT UNSIGNED NULL,
    enviado_em DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_avisos_curso_evento_id (curso_evento_id),
    KEY idx_avisos_usuario_id (usuario_id),
    KEY idx_avisos_tipo_destino (tipo_destino),
    KEY idx_avisos_status (status),
    KEY idx_avisos_periodo (mostrar_inicio, mostrar_fim),
    KEY idx_avisos_deleted_at (deleted_at),
    KEY idx_avisos_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS avisos_destinatarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    aviso_id INT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    curso_evento_id INT UNSIGNED NULL,
    inscricao_id INT UNSIGNED NULL,
    pedido_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ativo',
    visualizado_em DATETIME NULL,
    ocultado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_avisos_destinatarios_aviso_id (aviso_id),
    KEY idx_avisos_destinatarios_usuario_id (usuario_id),
    KEY idx_avisos_destinatarios_status (status),
    KEY idx_avisos_destinatarios_visualizado_em (visualizado_em),
    KEY idx_avisos_destinatarios_ocultado_em (ocultado_em),
    KEY idx_avisos_destinatarios_deleted_at (deleted_at),
    KEY idx_avisos_destinatarios_aviso_usuario_curso (aviso_id, usuario_id, curso_evento_id),
    CONSTRAINT fk_avisos_destinatarios_aviso
        FOREIGN KEY (aviso_id) REFERENCES avisos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
VALUES
    ('avisos', 'visualizar', 'avisos.visualizar', 'Ver avisos', 'Listar e visualizar avisos da plataforma', NOW(), NOW(), NULL),
    ('avisos', 'criar', 'avisos.criar', 'Criar avisos', 'Cadastrar novos avisos da plataforma', NOW(), NOW(), NULL),
    ('avisos', 'editar', 'avisos.editar', 'Editar avisos', 'Atualizar avisos cadastrados', NOW(), NOW(), NULL),
    ('avisos', 'excluir', 'avisos.excluir', 'Excluir avisos', 'Enviar avisos para a lixeira', NOW(), NOW(), NULL),
    ('avisos', 'enviar', 'avisos.enviar', 'Enviar avisos', 'Disparar avisos para os destinatários', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    updated_at = NOW();

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('avisos.visualizar', 'avisos.criar', 'avisos.editar', 'avisos.excluir', 'avisos.enviar')
WHERE p.slug = 'superadmin'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('avisos.visualizar', 'avisos.enviar')
WHERE p.slug = 'atendimento'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('avisos.visualizar', 'avisos.criar', 'avisos.editar', 'avisos.excluir', 'avisos.enviar')
WHERE p.slug = 'marketing'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug = 'avisos.visualizar'
WHERE p.slug = 'conteudo'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

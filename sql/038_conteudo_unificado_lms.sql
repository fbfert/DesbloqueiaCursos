-- Desbloqueia Cursos - Conteúdo Unificado (fundação)
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS conteudo_modulos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    titulo VARCHAR(190) NOT NULL,
    descricao TEXT NULL,
    ordem INT NOT NULL DEFAULT 0,
    status ENUM('rascunho','publicado','oculto','arquivado') NOT NULL DEFAULT 'rascunho',
    criado_por BIGINT UNSIGNED NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_conteudo_modulos_curso_evento_id (curso_evento_id),
    KEY idx_conteudo_modulos_status (status),
    KEY idx_conteudo_modulos_ordem (ordem),
    KEY idx_conteudo_modulos_deleted_at (deleted_at),
    CONSTRAINT fk_conteudo_modulos_curso_evento
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_modulos_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_conteudo_modulos_atualizado_por
        FOREIGN KEY (atualizado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteudo_itens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    modulo_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('etiqueta','texto','arquivo','link','avaliacao_textual','video') NOT NULL,
    titulo VARCHAR(190) NOT NULL,
    descricao_curta TEXT NULL,
    obrigatorio TINYINT(1) NOT NULL DEFAULT 0,
    ordem INT NOT NULL DEFAULT 0,
    status ENUM('rascunho','publicado','oculto','arquivado') NOT NULL DEFAULT 'rascunho',
    abre_em ENUM('mesma_pagina','nova_pagina','modal','embed','nova_aba') NULL,
    criado_por BIGINT UNSIGNED NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_conteudo_itens_curso_evento_id (curso_evento_id),
    KEY idx_conteudo_itens_modulo_id (modulo_id),
    KEY idx_conteudo_itens_tipo (tipo),
    KEY idx_conteudo_itens_obrigatorio (obrigatorio),
    KEY idx_conteudo_itens_status (status),
    KEY idx_conteudo_itens_ordem (ordem),
    KEY idx_conteudo_itens_deleted_at (deleted_at),
    CONSTRAINT fk_conteudo_itens_curso_evento
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_itens_modulo
        FOREIGN KEY (modulo_id) REFERENCES conteudo_modulos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_itens_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_conteudo_itens_atualizado_por
        FOREIGN KEY (atualizado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteudo_textos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id BIGINT UNSIGNED NOT NULL,
    conteudo LONGTEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_conteudo_textos_item (item_id),
    CONSTRAINT fk_conteudo_textos_item
        FOREIGN KEY (item_id) REFERENCES conteudo_itens (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteudo_etiquetas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id BIGINT UNSIGNED NOT NULL,
    conteudo LONGTEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_conteudo_etiquetas_item (item_id),
    CONSTRAINT fk_conteudo_etiquetas_item
        FOREIGN KEY (item_id) REFERENCES conteudo_itens (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteudo_arquivos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id BIGINT UNSIGNED NOT NULL,
    nome_original VARCHAR(255) NULL,
    nome_arquivo VARCHAR(255) NULL,
    caminho VARCHAR(500) NULL,
    mime_type VARCHAR(190) NULL,
    extensao VARCHAR(20) NULL,
    tamanho_bytes BIGINT UNSIGNED NULL,
    versao_atual_id BIGINT UNSIGNED NULL,
    permite_download TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_conteudo_arquivos_item (item_id),
    KEY idx_conteudo_arquivos_versao_atual_id (versao_atual_id),
    CONSTRAINT fk_conteudo_arquivos_item
        FOREIGN KEY (item_id) REFERENCES conteudo_itens (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteudo_arquivos_versoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    arquivo_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    nome_original VARCHAR(255) NULL,
    nome_arquivo VARCHAR(255) NULL,
    caminho VARCHAR(500) NULL,
    mime_type VARCHAR(190) NULL,
    extensao VARCHAR(20) NULL,
    tamanho_bytes BIGINT UNSIGNED NULL,
    versao INT NOT NULL DEFAULT 1,
    substituido_por BIGINT UNSIGNED NULL,
    criado_por BIGINT UNSIGNED NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_conteudo_arquivos_versoes_arquivo_id (arquivo_id),
    KEY idx_conteudo_arquivos_versoes_item_id (item_id),
    KEY idx_conteudo_arquivos_versoes_versao (versao),
    KEY idx_conteudo_arquivos_versoes_criado_por (criado_por),
    CONSTRAINT fk_conteudo_arquivos_versoes_arquivo
        FOREIGN KEY (arquivo_id) REFERENCES conteudo_arquivos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_arquivos_versoes_item
        FOREIGN KEY (item_id) REFERENCES conteudo_itens (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_arquivos_versoes_substituido_por
        FOREIGN KEY (substituido_por) REFERENCES conteudo_arquivos_versoes (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_conteudo_arquivos_versoes_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteudo_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id BIGINT UNSIGNED NOT NULL,
    url VARCHAR(700) NOT NULL,
    modo_abertura ENUM('nova_aba','embed','botao') NOT NULL DEFAULT 'nova_aba',
    provedor VARCHAR(80) NULL,
    embed_html TEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_conteudo_links_item (item_id),
    KEY idx_conteudo_links_modo_abertura (modo_abertura),
    CONSTRAINT fk_conteudo_links_item
        FOREIGN KEY (item_id) REFERENCES conteudo_itens (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteudo_videos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id BIGINT UNSIGNED NOT NULL,
    url VARCHAR(700) NOT NULL,
    provedor VARCHAR(80) NULL,
    embed_html TEXT NULL,
    duracao_segundos INT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_conteudo_videos_item (item_id),
    CONSTRAINT fk_conteudo_videos_item
        FOREIGN KEY (item_id) REFERENCES conteudo_itens (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteudo_avaliacoes_textuais (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id BIGINT UNSIGNED NOT NULL,
    enunciado LONGTEXT NOT NULL,
    orientacoes LONGTEXT NULL,
    nota_maxima DECIMAL(10,2) NULL,
    nota_minima DECIMAL(10,2) NULL,
    peso DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    prazo DATETIME NULL,
    permite_reenvio TINYINT(1) NOT NULL DEFAULT 1,
    reenvio_livre_ate_prazo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_conteudo_avaliacoes_textuais_item (item_id),
    KEY idx_conteudo_avaliacoes_textuais_prazo (prazo),
    CONSTRAINT fk_conteudo_avaliacoes_textuais_item
        FOREIGN KEY (item_id) REFERENCES conteudo_itens (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteudo_avaliacoes_entregas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    avaliacao_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    inscricao_id BIGINT UNSIGNED NULL,
    aluno_id BIGINT UNSIGNED NOT NULL,
    resposta LONGTEXT NULL,
    status ENUM('enviada','reenviada','corrigida','devolvida','aprovada','reprovada','cancelada') NOT NULL DEFAULT 'enviada',
    nota DECIMAL(10,2) NULL,
    feedback LONGTEXT NULL,
    corrigido_por BIGINT UNSIGNED NULL,
    corrigido_em DATETIME NULL,
    enviado_em DATETIME NULL,
    prazo_liberado_ate DATETIME NULL,
    liberado_reenvio_por BIGINT UNSIGNED NULL,
    liberado_reenvio_em DATETIME NULL,
    tentativa INT NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_conteudo_avaliacoes_entregas_avaliacao_id (avaliacao_id),
    KEY idx_conteudo_avaliacoes_entregas_item_id (item_id),
    KEY idx_conteudo_avaliacoes_entregas_curso_evento_id (curso_evento_id),
    KEY idx_conteudo_avaliacoes_entregas_turma_id (turma_id),
    KEY idx_conteudo_avaliacoes_entregas_inscricao_id (inscricao_id),
    KEY idx_conteudo_avaliacoes_entregas_aluno_id (aluno_id),
    KEY idx_conteudo_avaliacoes_entregas_status (status),
    KEY idx_conteudo_avaliacoes_entregas_corrigido_por (corrigido_por),
    KEY idx_conteudo_avaliacoes_entregas_enviado_em (enviado_em),
    KEY idx_conteudo_avaliacoes_entregas_deleted_at (deleted_at),
    CONSTRAINT fk_conteudo_avaliacoes_entregas_avaliacao
        FOREIGN KEY (avaliacao_id) REFERENCES conteudo_avaliacoes_textuais (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_avaliacoes_entregas_item
        FOREIGN KEY (item_id) REFERENCES conteudo_itens (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_avaliacoes_entregas_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_avaliacoes_entregas_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_conteudo_avaliacoes_entregas_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_conteudo_avaliacoes_entregas_aluno
        FOREIGN KEY (aluno_id) REFERENCES usuarios (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_avaliacoes_entregas_corrigido_por
        FOREIGN KEY (corrigido_por) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_conteudo_avaliacoes_entregas_liberado_reenvio_por
        FOREIGN KEY (liberado_reenvio_por) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteudo_progresso_aluno (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    aluno_id BIGINT UNSIGNED NOT NULL,
    modulo_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    status ENUM('nao_iniciado','acessado','em_andamento','concluido','pendente_correcao','reprovado') NOT NULL DEFAULT 'nao_iniciado',
    percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    obrigatorio TINYINT(1) NOT NULL DEFAULT 0,
    primeiro_acesso_em DATETIME NULL,
    ultimo_acesso_em DATETIME NULL,
    concluido_em DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_conteudo_progresso_aluno_contexto (aluno_id, inscricao_id, item_id),
    KEY idx_conteudo_progresso_aluno_curso_evento_id (curso_evento_id),
    KEY idx_conteudo_progresso_aluno_turma_id (turma_id),
    KEY idx_conteudo_progresso_aluno_inscricao_id (inscricao_id),
    KEY idx_conteudo_progresso_aluno_modulo_id (modulo_id),
    KEY idx_conteudo_progresso_aluno_item_id (item_id),
    KEY idx_conteudo_progresso_aluno_status (status),
    KEY idx_conteudo_progresso_aluno_deleted_at (deleted_at),
    CONSTRAINT fk_conteudo_progresso_aluno_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_progresso_aluno_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_conteudo_progresso_aluno_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_progresso_aluno_aluno
        FOREIGN KEY (aluno_id) REFERENCES usuarios (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_progresso_aluno_modulo
        FOREIGN KEY (modulo_id) REFERENCES conteudo_modulos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_progresso_aluno_item
        FOREIGN KEY (item_id) REFERENCES conteudo_itens (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteudo_logs_aluno (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    inscricao_id BIGINT UNSIGNED NULL,
    aluno_id BIGINT UNSIGNED NOT NULL,
    modulo_id BIGINT UNSIGNED NULL,
    item_id BIGINT UNSIGNED NULL,
    acao VARCHAR(80) NOT NULL,
    dados_json LONGTEXT NULL,
    ip VARCHAR(60) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_conteudo_logs_aluno_curso_evento_id (curso_evento_id),
    KEY idx_conteudo_logs_aluno_turma_id (turma_id),
    KEY idx_conteudo_logs_aluno_inscricao_id (inscricao_id),
    KEY idx_conteudo_logs_aluno_aluno_id (aluno_id),
    KEY idx_conteudo_logs_aluno_modulo_id (modulo_id),
    KEY idx_conteudo_logs_aluno_item_id (item_id),
    KEY idx_conteudo_logs_aluno_acao (acao),
    KEY idx_conteudo_logs_aluno_created_at (created_at),
    CONSTRAINT fk_conteudo_logs_aluno_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_logs_aluno_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_conteudo_logs_aluno_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_conteudo_logs_aluno_aluno
        FOREIGN KEY (aluno_id) REFERENCES usuarios (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_conteudo_logs_aluno_modulo
        FOREIGN KEY (modulo_id) REFERENCES conteudo_modulos (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_conteudo_logs_aluno_item
        FOREIGN KEY (item_id) REFERENCES conteudo_itens (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Desbloqueia Cursos - Certificados: templates + configuracoes globais (extensao)
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1) certificados_templates: complementar colunas para CRUD
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS certificados_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    descricao VARCHAR(255) NULL,
    corpo_html LONGTEXT NULL,
    cor_fundo VARCHAR(20) NULL,
    cor_texto VARCHAR(20) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    padrao TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_certificados_templates_slug (slug),
    KEY idx_certificados_templates_ativo (ativo),
    KEY idx_certificados_templates_padrao (padrao),
    KEY idx_certificados_templates_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status (rascunho/ativo/inativo)
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'status'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN status ENUM(''rascunho'',''ativo'',''inativo'') NULL DEFAULT ''ativo'' AFTER descricao'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- contexto/escopo (global/curso/turma/evento/etc)
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'contexto'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN contexto VARCHAR(60) NULL DEFAULT ''global'' AFTER status'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- curso_id/turma_id
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'curso_id'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN curso_id BIGINT UNSIGNED NULL AFTER contexto'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'turma_id'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN turma_id BIGINT UNSIGNED NULL AFTER curso_id'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- layout (orientacao/papel/margens)
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'orientacao'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN orientacao ENUM(''paisagem'',''retrato'') NULL DEFAULT ''paisagem'' AFTER padrao'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'tamanho_papel'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN tamanho_papel VARCHAR(20) NULL DEFAULT ''A4'' AFTER orientacao'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'margem_top'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN margem_top DECIMAL(8,2) NULL AFTER tamanho_papel'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'margem_bottom'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN margem_bottom DECIMAL(8,2) NULL AFTER margem_top'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'margem_left'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN margem_left DECIMAL(8,2) NULL AFTER margem_bottom'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'margem_right'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN margem_right DECIMAL(8,2) NULL AFTER margem_left'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CSS, imagem de fundo, logo e observacoes
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'css'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN css LONGTEXT NULL AFTER corpo_html'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'imagem_fundo'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN imagem_fundo VARCHAR(255) NULL AFTER css'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'logo'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN logo VARCHAR(255) NULL AFTER imagem_fundo'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'observacoes'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN observacoes TEXT NULL AFTER logo'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- auditoria simples (criado_por/atualizado_por)
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'criado_por'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN criado_por BIGINT UNSIGNED NULL AFTER observacoes'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND COLUMN_NAME = 'atualizado_por'
        ),
        'SELECT 1',
        'ALTER TABLE certificados_templates ADD COLUMN atualizado_por BIGINT UNSIGNED NULL AFTER criado_por'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- indices adicionais
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND INDEX_NAME = 'idx_certificados_templates_status'
        ),
        'SELECT 1',
        'CREATE INDEX idx_certificados_templates_status ON certificados_templates (status)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND INDEX_NAME = 'idx_certificados_templates_contexto'
        ),
        'SELECT 1',
        'CREATE INDEX idx_certificados_templates_contexto ON certificados_templates (contexto)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND INDEX_NAME = 'idx_certificados_templates_curso_id'
        ),
        'SELECT 1',
        'CREATE INDEX idx_certificados_templates_curso_id ON certificados_templates (curso_id)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'certificados_templates'
              AND INDEX_NAME = 'idx_certificados_templates_turma_id'
        ),
        'SELECT 1',
        'CREATE INDEX idx_certificados_templates_turma_id ON certificados_templates (turma_id)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ajustar status inicial para registros existentes
UPDATE certificados_templates
SET status = CASE
    WHEN status IS NULL AND ativo = 1 THEN 'ativo'
    WHEN status IS NULL AND ativo = 0 THEN 'inativo'
    ELSE status
END
WHERE deleted_at IS NULL;

UPDATE certificados_templates
SET contexto = IF(contexto IS NULL OR contexto = '', 'global', contexto)
WHERE deleted_at IS NULL;

-- template padrão inicial com placeholders (somente se estiver vazio)
INSERT INTO certificados_templates (nome, slug, descricao, corpo_html, cor_fundo, cor_texto, ativo, padrao, status, contexto, created_at, updated_at, deleted_at)
SELECT
    'Padrão (placeholders)',
    'padrao-placeholders',
    'Template base com placeholders para iniciar o módulo.',
    CONCAT(
        '<h1 style=\"margin:0 0 12px 0;\">Certificado</h1>',
        '<p>Certificamos que <strong>{aluno_nome}</strong> concluiu o curso <strong>{curso_nome}</strong>, com carga horária de {curso_carga_horaria}, realizado no período de {turma_data_inicio} a {turma_data_fim}.</p>',
        '<p>Emitido em {cidade_data_atual}.</p>',
        '<p><strong>Código de validação:</strong> {certificado_codigo}<br>',
        '<strong>Valide em:</strong> {certificado_url_validacao}</p>'
    ),
    '#ffffff',
    '#111827',
    1,
    0,
    'ativo',
    'global',
    NOW(),
    NOW(),
    NULL
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM certificados_templates WHERE deleted_at IS NULL
);

-- ------------------------------------------------------------
-- 2) configuracoes_certificados: complementar colunas globais
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS configuracoes_certificados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    prefixo_certificado VARCHAR(20) NOT NULL DEFAULT 'PRC',
    titulo_padrao VARCHAR(191) NULL,
    texto_validacao_publica TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_configuracoes_certificados_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- helper para adicionar coluna idempotente (repetido por compatibilidade MySQL 5.7)
-- Status do módulo
SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_habilitado'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_habilitado TINYINT(1) NOT NULL DEFAULT 1 AFTER texto_validacao_publica'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_emissao_habilitada'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_emissao_habilitada TINYINT(1) NOT NULL DEFAULT 1 AFTER certificados_habilitado'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_modo_emissao'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_modo_emissao VARCHAR(40) NULL DEFAULT ''manual'' AFTER certificados_emissao_habilitada'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_area_aluno'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_area_aluno TINYINT(1) NOT NULL DEFAULT 1 AFTER certificados_modo_emissao'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_permitir_download'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_permitir_download TINYINT(1) NOT NULL DEFAULT 1 AFTER certificados_exibir_area_aluno'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_permitir_reemissao_aluno'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_permitir_reemissao_aluno TINYINT(1) NOT NULL DEFAULT 0 AFTER certificados_permitir_download'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_botao_validacao_publica'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_botao_validacao_publica TINYINT(1) NOT NULL DEFAULT 1 AFTER certificados_permitir_reemissao_aluno'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Elegibilidade (globais; regras por curso/turma continuam no módulo acadêmico)
SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exigir_inscricao_concluida'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exigir_inscricao_concluida TINYINT(1) NOT NULL DEFAULT 0 AFTER certificados_exibir_botao_validacao_publica'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exigir_pagamento_aprovado'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exigir_pagamento_aprovado TINYINT(1) NOT NULL DEFAULT 0 AFTER certificados_exigir_inscricao_concluida'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exigir_presenca_minima'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exigir_presenca_minima TINYINT(1) NOT NULL DEFAULT 0 AFTER certificados_exigir_pagamento_aprovado'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_percentual_presenca_minima'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_percentual_presenca_minima DECIMAL(5,2) NULL DEFAULT 75.00 AFTER certificados_exigir_presenca_minima'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exigir_conclusao_aulas'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exigir_conclusao_aulas TINYINT(1) NOT NULL DEFAULT 0 AFTER certificados_percentual_presenca_minima'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_percentual_conclusao_minima'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_percentual_conclusao_minima DECIMAL(5,2) NULL DEFAULT 100.00 AFTER certificados_exigir_conclusao_aulas'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exigir_avaliacao'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exigir_avaliacao TINYINT(1) NOT NULL DEFAULT 0 AFTER certificados_percentual_conclusao_minima'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_nota_minima'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_nota_minima DECIMAL(5,2) NULL DEFAULT 70.00 AFTER certificados_exigir_avaliacao'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exigir_atividades_aprovadas'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exigir_atividades_aprovadas TINYINT(1) NOT NULL DEFAULT 0 AFTER certificados_nota_minima'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_permitir_emissao_com_pendencias_admin'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_permitir_emissao_com_pendencias_admin TINYINT(1) NOT NULL DEFAULT 1 AFTER certificados_exigir_atividades_aprovadas'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_status_inscricao_permitidos'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_status_inscricao_permitidos VARCHAR(255) NULL DEFAULT ''ativa,em_andamento,concluida,concluida_sem_certificado,certificado_emitido'' AFTER certificados_permitir_emissao_com_pendencias_admin'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_observacao_regras_emissao'),
        'SELECT 1',
        'ALTER TABLE configuracoes_certificados ADD COLUMN certificados_observacao_regras_emissao TEXT NULL AFTER certificados_status_inscricao_permitidos'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Dados exibidos
SET @campos_dados := 'certificados_exibir_nome_aluno,
certificados_exibir_documento_aluno,
certificados_exibir_nome_curso,
certificados_exibir_turma,
certificados_exibir_carga_horaria,
certificados_exibir_modalidade,
certificados_exibir_periodo_curso,
certificados_exibir_data_conclusao,
certificados_exibir_data_emissao,
certificados_exibir_codigo_certificado,
certificados_exibir_qrcode,
certificados_exibir_url_validacao,
certificados_exibir_professor_responsavel,
certificados_exibir_coordenador_institucional,
certificados_exibir_cnpj_instituicao,
certificados_exibir_local_emissao';

-- adicionar os campos um a um (MySQL 5.7)
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_nome_aluno'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_nome_aluno TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_documento_aluno'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_documento_aluno TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_nome_curso'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_nome_curso TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_turma'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_turma TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_carga_horaria'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_carga_horaria TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_modalidade'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_modalidade TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_periodo_curso'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_periodo_curso TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_data_conclusao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_data_conclusao TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_data_emissao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_data_emissao TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_codigo_certificado'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_codigo_certificado TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_qrcode'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_qrcode TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_url_validacao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_url_validacao TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_professor_responsavel'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_professor_responsavel TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_coordenador_institucional'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_coordenador_institucional TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_cnpj_instituicao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_cnpj_instituicao TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exibir_local_emissao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exibir_local_emissao TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Validacao publica
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_validacao_publica_habilitada'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_validacao_publica_habilitada TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_validacao_exibir_nome_aluno'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_validacao_exibir_nome_aluno TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_validacao_exibir_curso'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_validacao_exibir_curso TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_validacao_exibir_carga_horaria'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_validacao_exibir_carga_horaria TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_validacao_exibir_data_emissao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_validacao_exibir_data_emissao TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_validacao_exibir_status'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_validacao_exibir_status TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_validacao_exibir_motivo_bloqueio'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_validacao_exibir_motivo_bloqueio TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_codigo_formato'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_codigo_formato VARCHAR(40) NULL DEFAULT ''alfanumerico'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_codigo_prefixo'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_codigo_prefixo VARCHAR(20) NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_codigo_tamanho_minimo'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_codigo_tamanho_minimo INT UNSIGNED NULL DEFAULT 10'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_permitir_validacao_por_qrcode'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_permitir_validacao_por_qrcode TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_url_validacao_publica_base'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_url_validacao_publica_base VARCHAR(255) NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_mensagem_valido'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_mensagem_valido TEXT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_mensagem_invalido'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_mensagem_invalido TEXT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_mensagem_cancelado'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_mensagem_cancelado TEXT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Layout padrão (global)
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_template_padrao_id'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_template_padrao_id INT UNSIGNED NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_orientacao_padrao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_orientacao_padrao VARCHAR(20) NULL DEFAULT ''paisagem'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_tamanho_papel_padrao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_tamanho_papel_padrao VARCHAR(20) NULL DEFAULT ''A4'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_margem_top_padrao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_margem_top_padrao DECIMAL(8,2) NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_margem_bottom_padrao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_margem_bottom_padrao DECIMAL(8,2) NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_margem_left_padrao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_margem_left_padrao DECIMAL(8,2) NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_margem_right_padrao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_margem_right_padrao DECIMAL(8,2) NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_usar_imagem_fundo'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_usar_imagem_fundo TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_imagem_fundo_padrao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_imagem_fundo_padrao VARCHAR(255) NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_usar_logo_institucional'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_usar_logo_institucional TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_logo_padrao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_logo_padrao VARCHAR(255) NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_qrcode_habilitado'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_qrcode_habilitado TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_qrcode_posicao_padrao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_qrcode_posicao_padrao VARCHAR(30) NULL DEFAULT ''inferior_direita'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_observacoes_layout'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_observacoes_layout TEXT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Assinaturas (como campos globais; assinantes por curso já existem)
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_assinatura_1_exibir'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_assinatura_1_exibir TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_assinatura_1_nome'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_assinatura_1_nome VARCHAR(191) NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_assinatura_1_cargo'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_assinatura_1_cargo VARCHAR(191) NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_assinatura_1_imagem'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_assinatura_1_imagem VARCHAR(255) NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_assinatura_2_exibir'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_assinatura_2_exibir TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_assinatura_2_nome'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_assinatura_2_nome VARCHAR(191) NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_assinatura_2_cargo'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_assinatura_2_cargo VARCHAR(191) NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_assinatura_2_imagem'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_assinatura_2_imagem VARCHAR(255) NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_assinatura_3_exibir'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_assinatura_3_exibir TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_assinatura_3_nome'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_assinatura_3_nome VARCHAR(191) NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_assinatura_3_cargo'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_assinatura_3_cargo VARCHAR(191) NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_assinatura_3_imagem'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_assinatura_3_imagem VARCHAR(255) NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_permitir_assinatura_professor'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_permitir_assinatura_professor TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_permitir_assinatura_coordenador'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_permitir_assinatura_coordenador TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Reemissao/cancelamento/auditoria
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_permitir_segunda_via'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_permitir_segunda_via TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_registrar_numero_via'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_registrar_numero_via TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_manter_historico_reemissoes'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_manter_historico_reemissoes TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_permitir_cancelamento'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_permitir_cancelamento TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_exigir_motivo_cancelamento'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_exigir_motivo_cancelamento TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_registrar_usuario_emissor'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_registrar_usuario_emissor TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_registrar_usuario_cancelou'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_registrar_usuario_cancelou TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_registrar_ip_data_hora_emissao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_registrar_ip_data_hora_emissao TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_regenerar_pdf_mesmo_codigo'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_regenerar_pdf_mesmo_codigo TINYINT(1) NOT NULL DEFAULT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_bloquear_alteracao_apos_emitido'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_bloquear_alteracao_apos_emitido TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Textos padrão
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_texto_padrao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_texto_padrao LONGTEXT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_texto_rodape'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_texto_rodape LONGTEXT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_texto_validacao'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_texto_validacao LONGTEXT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_texto_observacoes_legais'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_texto_observacoes_legais LONGTEXT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_texto_indisponivel'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_texto_indisponivel LONGTEXT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes_certificados' AND COLUMN_NAME = 'certificados_texto_requisitos_nao_cumpridos'),'SELECT 1','ALTER TABLE configuracoes_certificados ADD COLUMN certificados_texto_requisitos_nao_cumpridos LONGTEXT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;


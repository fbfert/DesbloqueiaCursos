-- Polo Rainbow - financeiro e repasses
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS professores_fiscal (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    tipo_pessoa ENUM('pf', 'pj') NOT NULL DEFAULT 'pf',
    cpf VARCHAR(14) NULL,
    cnpj VARCHAR(20) NULL,
    razao_social VARCHAR(191) NULL,
    nome_fantasia VARCHAR(191) NULL,
    inscricao_municipal VARCHAR(100) NULL,
    aliquota_retencao DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    exige_nota_fiscal TINYINT(1) NOT NULL DEFAULT 0,
    email_financeiro VARCHAR(191) NULL,
    observacao TEXT NULL,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_professores_fiscal_usuario (usuario_id),
    KEY idx_professores_fiscal_tipo (tipo_pessoa),
    KEY idx_professores_fiscal_status (status),
    KEY idx_professores_fiscal_deleted_at (deleted_at),
    CONSTRAINT fk_professores_fiscal_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS apuracoes_mensais (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    competencia CHAR(7) NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    base_bruta DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    desconto_cupons DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    base_liquida DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    percentual_rateio_total DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    valor_rateio_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_retenido_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('aberta', 'apurando', 'fechada', 'paga', 'cancelada') NOT NULL DEFAULT 'aberta',
    fechada_em DATETIME NULL,
    criada_por_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_apuracoes_mensais_competencia (competencia),
    KEY idx_apuracoes_mensais_status (status),
    KEY idx_apuracoes_mensais_data_inicio (data_inicio),
    KEY idx_apuracoes_mensais_deleted_at (deleted_at),
    CONSTRAINT fk_apuracoes_mensais_usuario
        FOREIGN KEY (criada_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cursos_rateio (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    apuracao_id BIGINT UNSIGNED NOT NULL,
    curso_evento_id BIGINT UNSIGNED NOT NULL,
    turma_id BIGINT UNSIGNED NULL,
    competencia CHAR(7) NOT NULL,
    base_bruta DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    desconto_cupons DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    base_liquida DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    percentual_total DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    valor_rateio_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('calculado', 'fechado', 'cancelado') NOT NULL DEFAULT 'calculado',
    fechado_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cursos_rateio_apuracao (apuracao_id),
    KEY idx_cursos_rateio_curso (curso_evento_id),
    KEY idx_cursos_rateio_turma (turma_id),
    KEY idx_cursos_rateio_competencia (competencia),
    KEY idx_cursos_rateio_status (status),
    KEY idx_cursos_rateio_deleted_at (deleted_at),
    CONSTRAINT fk_cursos_rateio_apuracao
        FOREIGN KEY (apuracao_id) REFERENCES apuracoes_mensais (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cursos_rateio_curso
        FOREIGN KEY (curso_evento_id) REFERENCES cursos_eventos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cursos_rateio_turma
        FOREIGN KEY (turma_id) REFERENCES turmas (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cursos_rateio_participantes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_rateio_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    tipo_fiscal ENUM('pf', 'pj') NOT NULL DEFAULT 'pf',
    percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    valor_base DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_rateado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    retencao_percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    valor_retenido DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_liquido DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pendente', 'aguardando_documento', 'documento_recebido', 'aprovado', 'pago', 'cancelado') NOT NULL DEFAULT 'pendente',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cursos_rateio_participantes (curso_rateio_id, usuario_id),
    KEY idx_cursos_rateio_participantes_usuario (usuario_id),
    KEY idx_cursos_rateio_participantes_status (status),
    KEY idx_cursos_rateio_participantes_deleted_at (deleted_at),
    CONSTRAINT fk_cursos_rateio_participantes_rateio
        FOREIGN KEY (curso_rateio_id) REFERENCES cursos_rateio (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cursos_rateio_participantes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS repasses_professores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    apuracao_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    tipo_fiscal ENUM('pf', 'pj') NOT NULL DEFAULT 'pf',
    percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    base_liquida DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_bruto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    retencao_percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    valor_retenido DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_liquido DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    documento_obrigatorio TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pendente', 'aguardando_documento', 'documento_recebido', 'aprovado', 'pago', 'cancelado') NOT NULL DEFAULT 'pendente',
    competencia CHAR(7) NOT NULL,
    documento_validado_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_repasses_professores_apuracao (apuracao_id),
    KEY idx_repasses_professores_usuario (usuario_id),
    KEY idx_repasses_professores_status (status),
    KEY idx_repasses_professores_competencia (competencia),
    KEY idx_repasses_professores_deleted_at (deleted_at),
    CONSTRAINT fk_repasses_professores_apuracao
        FOREIGN KEY (apuracao_id) REFERENCES apuracoes_mensais (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_repasses_professores_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS repasses_documentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    repasse_professor_id BIGINT UNSIGNED NOT NULL,
    tipo_documento ENUM('nota_fiscal', 'rpa', 'outro') NOT NULL DEFAULT 'outro',
    numero_documento VARCHAR(80) NULL,
    arquivo_caminho VARCHAR(255) NOT NULL,
    arquivo_nome_original VARCHAR(255) NOT NULL,
    arquivo_tipo VARCHAR(120) NULL,
    status ENUM('pendente', 'recebido', 'validado', 'rejeitado') NOT NULL DEFAULT 'pendente',
    observacao TEXT NULL,
    enviado_por_usuario_id BIGINT UNSIGNED NULL,
    validado_por_usuario_id BIGINT UNSIGNED NULL,
    validado_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_repasses_documentos_repasse (repasse_professor_id),
    KEY idx_repasses_documentos_tipo (tipo_documento),
    KEY idx_repasses_documentos_status (status),
    KEY idx_repasses_documentos_deleted_at (deleted_at),
    CONSTRAINT fk_repasses_documentos_repasse
        FOREIGN KEY (repasse_professor_id) REFERENCES repasses_professores (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_repasses_documentos_enviado_por
        FOREIGN KEY (enviado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_repasses_documentos_validado_por
        FOREIGN KEY (validado_por_usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamentos_professores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    repasse_professor_id BIGINT UNSIGNED NOT NULL,
    data_pagamento DATE NULL,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    metodo ENUM('pix', 'ted', 'transferencia', 'boleto', 'outro') NOT NULL DEFAULT 'outro',
    comprovante_caminho VARCHAR(255) NULL,
    comprovante_nome_original VARCHAR(255) NULL,
    referencia_bancaria VARCHAR(120) NULL,
    status ENUM('pendente', 'agendado', 'pago', 'cancelado') NOT NULL DEFAULT 'pendente',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pagamentos_professores_repasse (repasse_professor_id),
    KEY idx_pagamentos_professores_status (status),
    KEY idx_pagamentos_professores_deleted_at (deleted_at),
    CONSTRAINT fk_pagamentos_professores_repasse
        FOREIGN KEY (repasse_professor_id) REFERENCES repasses_professores (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rpa_espelhos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    repasse_professor_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    competencia CHAR(7) NOT NULL,
    valor_bruto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_retenido DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_liquido DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cpf VARCHAR(14) NULL,
    nome VARCHAR(191) NOT NULL,
    arquivo_caminho VARCHAR(255) NOT NULL,
    arquivo_nome_original VARCHAR(255) NOT NULL,
    status ENUM('gerado', 'emitido', 'cancelado') NOT NULL DEFAULT 'gerado',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_rpa_espelhos_repasse (repasse_professor_id),
    KEY idx_rpa_espelhos_usuario (usuario_id),
    KEY idx_rpa_espelhos_competencia (competencia),
    KEY idx_rpa_espelhos_deleted_at (deleted_at),
    CONSTRAINT fk_rpa_espelhos_repasse
        FOREIGN KEY (repasse_professor_id) REFERENCES repasses_professores (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rpa_espelhos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
VALUES
    ('financeiro', 'ver', 'financeiro.ver', 'Ver financeiro', 'Visualizar apuracoes, repasses e pagamentos', NOW(), NOW(), NULL),
    ('financeiro', 'gerenciar', 'financeiro.gerenciar', 'Gerenciar financeiro', 'Administrar apuracoes, repasses e pagamentos', NOW(), NOW(), NULL),
    ('financeiro', 'professor.ver', 'financeiro.professor.ver', 'Ver financeiro do professor', 'Acessar dados financeiros do professor', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    updated_at = NOW();

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('financeiro.ver', 'financeiro.gerenciar')
WHERE p.slug = 'superadmin'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug IN ('financeiro.ver', 'financeiro.gerenciar')
WHERE p.slug = 'financeiro'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug = 'financeiro.ver'
WHERE p.slug = 'atendimento'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
SELECT p.id, per.id, NOW()
FROM perfis p
INNER JOIN permissoes per ON per.slug = 'financeiro.professor.ver'
WHERE p.slug = 'professor'
ON DUPLICATE KEY UPDATE created_at = VALUES(created_at);

SET FOREIGN_KEY_CHECKS = 1;

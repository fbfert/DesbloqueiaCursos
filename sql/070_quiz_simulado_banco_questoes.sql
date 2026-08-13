-- Desbloqueia Cursos - Quizzes: banco de questoes, blocos de sorteio,
-- questoes discursivas, tempo de prova e correcao manual/automatizavel.
--
-- Compatibilidade: MySQL 5.7 (sem window functions, sem CHECK, sem DEFAULT em TEXT)
-- Charset/collation: utf8mb4 / utf8mb4_unicode_ci
-- Todas as operacoes sao idempotentes (IF NOT EXISTS / information_schema + PREPARE)
--
-- Compatibilidade retroativa: nenhum quiz existente muda de comportamento.
--   conteudo_quizzes.modo_selecao  = 'todas' (todas as perguntas, como hoje)
--   conteudo_quizzes.duracao_minutos = NULL  (sem limite de tempo, como hoje)
--   conteudo_quiz_perguntas.bloco_id = NULL  (questoes legadas sem bloco)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ==================================================================
-- 1. conteudo_quizzes: duracao, modo de selecao e politica de tempo
-- ==================================================================

-- duracao_minutos: NULL = sem limite de tempo (comportamento atual)
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quizzes' AND COLUMN_NAME = 'duracao_minutos'),
    'SELECT ''conteudo_quizzes.duracao_minutos ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quizzes ADD COLUMN duracao_minutos INT UNSIGNED NULL COMMENT ''NULL = sem limite de tempo'' AFTER percentual_minimo'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- modo_selecao: 'todas' (legado) | 'blocos' (sorteio por banco de questoes)
-- VARCHAR em vez de ENUM para permitir novos modos sem ALTER de tabela grande.
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quizzes' AND COLUMN_NAME = 'modo_selecao'),
    'SELECT ''conteudo_quizzes.modo_selecao ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quizzes ADD COLUMN modo_selecao VARCHAR(20) NOT NULL DEFAULT ''todas'' COMMENT ''todas | blocos'' AFTER duracao_minutos'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- acao_ao_expirar: 'enviar_automatico' (PND) | 'encerrar_sem_envio'
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quizzes' AND COLUMN_NAME = 'acao_ao_expirar'),
    'SELECT ''conteudo_quizzes.acao_ao_expirar ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quizzes ADD COLUMN acao_ao_expirar VARCHAR(30) NOT NULL DEFAULT ''enviar_automatico'' COMMENT ''enviar_automatico | encerrar_sem_envio'' AFTER modo_selecao'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- evitar_repeticao_tentativas: nao repetir questoes entre tentativas da mesma inscricao
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quizzes' AND COLUMN_NAME = 'evitar_repeticao_tentativas'),
    'SELECT ''conteudo_quizzes.evitar_repeticao_tentativas ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quizzes ADD COLUMN evitar_repeticao_tentativas TINYINT(1) NOT NULL DEFAULT 1 AFTER acao_ao_expirar'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- permitir_banco_insuficiente: excecao consciente para publicar simulado sem banco completo
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quizzes' AND COLUMN_NAME = 'permitir_banco_insuficiente'),
    'SELECT ''conteudo_quizzes.permitir_banco_insuficiente ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quizzes ADD COLUMN permitir_banco_insuficiente TINYINT(1) NOT NULL DEFAULT 0 AFTER evitar_repeticao_tentativas'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- limite_caracteres_discursiva: NULL = usa o limite padrao do servico
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quizzes' AND COLUMN_NAME = 'limite_caracteres_discursiva'),
    'SELECT ''conteudo_quizzes.limite_caracteres_discursiva ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quizzes ADD COLUMN limite_caracteres_discursiva INT UNSIGNED NULL AFTER permitir_banco_insuficiente'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ==================================================================
-- 2. conteudo_quiz_blocos: blocos de sorteio reutilizaveis
-- ==================================================================

CREATE TABLE IF NOT EXISTS conteudo_quiz_blocos (
    id                            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quiz_id                       BIGINT UNSIGNED NOT NULL,
    codigo                        VARCHAR(60) NOT NULL COMMENT 'Ex.: FGD, PEDAGOGIA, DISCURSIVA',
    titulo                        VARCHAR(190) NOT NULL,
    descricao                     TEXT NULL,
    tipo_questao                  VARCHAR(30) NOT NULL DEFAULT 'multipla_escolha' COMMENT 'multipla_escolha | discursiva',
    quantidade_sortear            INT UNSIGNED NOT NULL DEFAULT 0,
    distribuicao_dificuldade_json TEXT NULL COMMENT 'JSON {"facil":20,"media":60,"dificil":20} em percentual',
    conta_para_percentual         TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = fora do percentual de aprovacao (ex.: discursiva)',
    obrigatorio_para_envio        TINYINT(1) NOT NULL DEFAULT 1,
    ordem                         INT NOT NULL DEFAULT 0,
    status                        VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'ativo | inativo',
    created_at                    DATETIME NULL,
    updated_at                    DATETIME NULL,
    deleted_at                    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cqb_quiz_codigo (quiz_id, codigo),
    KEY idx_cqb_quiz_id (quiz_id),
    KEY idx_cqb_status (status),
    KEY idx_cqb_ordem (ordem),
    KEY idx_cqb_deleted_at (deleted_at),
    CONSTRAINT fk_cqb_quiz
        FOREIGN KEY (quiz_id) REFERENCES conteudo_quizzes (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- 3. conteudo_quiz_perguntas: vinculo com bloco + metadados do banco
-- ==================================================================

-- tipo: acrescenta 'discursiva' preservando 'multipla_escolha'
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_perguntas'
      AND COLUMN_NAME = 'tipo' AND COLUMN_TYPE LIKE '%discursiva%'),
    'SELECT ''conteudo_quiz_perguntas.tipo ja tem discursiva'' AS mensagem',
    'ALTER TABLE conteudo_quiz_perguntas MODIFY tipo ENUM(''multipla_escolha'',''discursiva'') NOT NULL DEFAULT ''multipla_escolha'''));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- bloco_id: NULL para questoes legadas (quiz sem banco de questoes)
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_perguntas' AND COLUMN_NAME = 'bloco_id'),
    'SELECT ''conteudo_quiz_perguntas.bloco_id ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_perguntas ADD COLUMN bloco_id BIGINT UNSIGNED NULL COMMENT ''NULL = questao legada sem bloco'' AFTER quiz_id'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- dificuldade: facil | media | dificil (VARCHAR para permitir novas faixas)
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_perguntas' AND COLUMN_NAME = 'dificuldade'),
    'SELECT ''conteudo_quiz_perguntas.dificuldade ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_perguntas ADD COLUMN dificuldade VARCHAR(10) NOT NULL DEFAULT ''media'' COMMENT ''facil | media | dificil'' AFTER tipo'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tema / objeto de conhecimento
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_perguntas' AND COLUMN_NAME = 'tema'),
    'SELECT ''conteudo_quiz_perguntas.tema ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_perguntas ADD COLUMN tema VARCHAR(190) NULL COMMENT ''Objeto de conhecimento'' AFTER dificuldade'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- status de uso no banco: ativo | inativo
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_perguntas' AND COLUMN_NAME = 'status'),
    'SELECT ''conteudo_quiz_perguntas.status ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_perguntas ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT ''ativo'' COMMENT ''ativo | inativo'' AFTER tema'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- referencia livre da questao (fonte, codigo interno do banco)
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_perguntas' AND COLUMN_NAME = 'referencia'),
    'SELECT ''conteudo_quiz_perguntas.referencia ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_perguntas ADD COLUMN referencia VARCHAR(120) NULL AFTER status'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rubrica/criterio de correcao (usada pelas discursivas)
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_perguntas' AND COLUMN_NAME = 'rubrica'),
    'SELECT ''conteudo_quiz_perguntas.rubrica ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_perguntas ADD COLUMN rubrica TEXT NULL COMMENT ''Criterios de correcao da discursiva'' AFTER explicacao'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- nota maxima da discursiva (informativa, nao entra no percentual objetivo)
SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_perguntas' AND COLUMN_NAME = 'nota_maxima'),
    'SELECT ''conteudo_quiz_perguntas.nota_maxima ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_perguntas ADD COLUMN nota_maxima DECIMAL(6,2) NULL AFTER rubrica'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_perguntas' AND INDEX_NAME = 'idx_cqp_bloco_id'),
    'SELECT ''idx_cqp_bloco_id ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_perguntas ADD KEY idx_cqp_bloco_id (bloco_id)'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_perguntas' AND INDEX_NAME = 'idx_cqp_sorteio'),
    'SELECT ''idx_cqp_sorteio ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_perguntas ADD KEY idx_cqp_sorteio (quiz_id, bloco_id, status, dificuldade, deleted_at)'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_perguntas' AND INDEX_NAME = 'idx_cqp_tema'),
    'SELECT ''idx_cqp_tema ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_perguntas ADD KEY idx_cqp_tema (tema)'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_perguntas' AND CONSTRAINT_NAME = 'fk_cqp_bloco'),
    'SELECT ''fk_cqp_bloco ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_perguntas ADD CONSTRAINT fk_cqp_bloco FOREIGN KEY (bloco_id) REFERENCES conteudo_quiz_blocos (id) ON DELETE SET NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ==================================================================
-- 4. conteudo_quiz_tentativas: tempo, totais objetivos e auditoria
-- ==================================================================

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_tentativas' AND COLUMN_NAME = 'duracao_minutos'),
    'SELECT ''conteudo_quiz_tentativas.duracao_minutos ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_tentativas ADD COLUMN duracao_minutos INT UNSIGNED NULL COMMENT ''Copiada do quiz no inicio da tentativa'' AFTER quiz_snapshot_json'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_tentativas' AND COLUMN_NAME = 'expira_em'),
    'SELECT ''conteudo_quiz_tentativas.expira_em ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_tentativas ADD COLUMN expira_em DATETIME NULL COMMENT ''Prazo calculado no servidor'' AFTER duracao_minutos'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_tentativas' AND COLUMN_NAME = 'encerrada_por_tempo'),
    'SELECT ''conteudo_quiz_tentativas.encerrada_por_tempo ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_tentativas ADD COLUMN encerrada_por_tempo TINYINT(1) NOT NULL DEFAULT 0 AFTER expira_em'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_tentativas' AND COLUMN_NAME = 'tempo_utilizado_segundos'),
    'SELECT ''conteudo_quiz_tentativas.tempo_utilizado_segundos ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_tentativas ADD COLUMN tempo_utilizado_segundos INT UNSIGNED NULL AFTER encerrada_por_tempo'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_tentativas' AND COLUMN_NAME = 'total_objetivas'),
    'SELECT ''conteudo_quiz_tentativas.total_objetivas ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_tentativas ADD COLUMN total_objetivas INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''Denominador do percentual de aprovacao'' AFTER total_perguntas'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_tentativas' AND COLUMN_NAME = 'total_discursivas'),
    'SELECT ''conteudo_quiz_tentativas.total_discursivas ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_tentativas ADD COLUMN total_discursivas INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_objetivas'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_tentativas' AND COLUMN_NAME = 'discursiva_status'),
    'SELECT ''conteudo_quiz_tentativas.discursiva_status ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_tentativas ADD COLUMN discursiva_status VARCHAR(20) NOT NULL DEFAULT ''nao_aplicavel'' COMMENT ''nao_aplicavel | pendente | corrigida'' AFTER aprovado'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_tentativas' AND COLUMN_NAME = 'sorteio_com_repeticao'),
    'SELECT ''conteudo_quiz_tentativas.sorteio_com_repeticao ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_tentativas ADD COLUMN sorteio_com_repeticao TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = faltaram ineditas e houve reaproveitamento'' AFTER discursiva_status'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_tentativas' AND COLUMN_NAME = 'sorteio_auditoria_json'),
    'SELECT ''conteudo_quiz_tentativas.sorteio_auditoria_json ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_tentativas ADD COLUMN sorteio_auditoria_json TEXT NULL COMMENT ''Ocorrencias auditaveis do sorteio'' AFTER sorteio_com_repeticao'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_tentativas' AND COLUMN_NAME = 'ultima_atividade_em'),
    'SELECT ''conteudo_quiz_tentativas.ultima_atividade_em ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_tentativas ADD COLUMN ultima_atividade_em DATETIME NULL AFTER iniciada_em'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_tentativas' AND INDEX_NAME = 'idx_cqt_expira_em'),
    'SELECT ''idx_cqt_expira_em ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_tentativas ADD KEY idx_cqt_expira_em (expira_em)'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_tentativas' AND INDEX_NAME = 'idx_cqt_discursiva_status'),
    'SELECT ''idx_cqt_discursiva_status ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_tentativas ADD KEY idx_cqt_discursiva_status (discursiva_status)'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ==================================================================
-- 5. conteudo_quiz_respostas: bloco, tipo, texto discursivo e revisao
-- ==================================================================

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_respostas' AND COLUMN_NAME = 'bloco_id'),
    'SELECT ''conteudo_quiz_respostas.bloco_id ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_respostas ADD COLUMN bloco_id BIGINT UNSIGNED NULL AFTER pergunta_id'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_respostas' AND COLUMN_NAME = 'tipo'),
    'SELECT ''conteudo_quiz_respostas.tipo ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_respostas ADD COLUMN tipo VARCHAR(30) NOT NULL DEFAULT ''multipla_escolha'' AFTER bloco_id'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_respostas' AND COLUMN_NAME = 'texto_resposta'),
    'SELECT ''conteudo_quiz_respostas.texto_resposta ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_respostas ADD COLUMN texto_resposta LONGTEXT NULL COMMENT ''Resposta discursiva em texto puro'' AFTER resposta_json'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_respostas' AND COLUMN_NAME = 'marcada_para_revisao'),
    'SELECT ''conteudo_quiz_respostas.marcada_para_revisao ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_respostas ADD COLUMN marcada_para_revisao TINYINT(1) NOT NULL DEFAULT 0 AFTER texto_resposta'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_respostas' AND COLUMN_NAME = 'conta_para_percentual'),
    'SELECT ''conteudo_quiz_respostas.conta_para_percentual ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_respostas ADD COLUMN conta_para_percentual TINYINT(1) NOT NULL DEFAULT 1 AFTER marcada_para_revisao'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conteudo_quiz_respostas' AND INDEX_NAME = 'idx_cqr_bloco_id'),
    'SELECT ''idx_cqr_bloco_id ja existe'' AS mensagem',
    'ALTER TABLE conteudo_quiz_respostas ADD KEY idx_cqr_bloco_id (bloco_id)'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ==================================================================
-- 6. conteudo_quiz_itens_utilizados: historico de itens ja sorteados
--    (permite evitar repeticao entre tentativas da mesma inscricao)
-- ==================================================================

CREATE TABLE IF NOT EXISTS conteudo_quiz_itens_utilizados (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quiz_id          BIGINT UNSIGNED NOT NULL,
    inscricao_id     BIGINT UNSIGNED NOT NULL,
    aluno_id         BIGINT UNSIGNED NOT NULL,
    tentativa_id     BIGINT UNSIGNED NOT NULL,
    numero_tentativa INT UNSIGNED NOT NULL DEFAULT 1,
    pergunta_id      BIGINT UNSIGNED NOT NULL,
    bloco_id         BIGINT UNSIGNED NULL,
    reutilizada      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = item repetido por falta de ineditos',
    created_at       DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cqiu_tentativa_pergunta (tentativa_id, pergunta_id),
    KEY idx_cqiu_quiz_inscricao (quiz_id, inscricao_id),
    KEY idx_cqiu_pergunta_id (pergunta_id),
    KEY idx_cqiu_reutilizada (reutilizada),
    CONSTRAINT fk_cqiu_tentativa
        FOREIGN KEY (tentativa_id) REFERENCES conteudo_quiz_tentativas (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cqiu_pergunta
        FOREIGN KEY (pergunta_id) REFERENCES conteudo_quiz_perguntas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- 7. conteudo_quiz_correcoes_discursivas: correcao manual (e futura IA)
-- ==================================================================

CREATE TABLE IF NOT EXISTS conteudo_quiz_correcoes_discursivas (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tentativa_id        BIGINT UNSIGNED NOT NULL,
    resposta_id         BIGINT UNSIGNED NULL,
    pergunta_id         BIGINT UNSIGNED NOT NULL,
    bloco_id            BIGINT UNSIGNED NULL,
    status              VARCHAR(20) NOT NULL DEFAULT 'pendente' COMMENT 'pendente | corrigida | dispensada',
    nota                DECIMAL(6,2) NULL COMMENT 'Informativa: nao entra no percentual objetivo',
    nota_maxima         DECIMAL(6,2) NOT NULL DEFAULT 10.00,
    rubrica             TEXT NULL COMMENT 'Criterio aplicado na correcao',
    feedback            TEXT NULL,
    origem              VARCHAR(20) NOT NULL DEFAULT 'manual' COMMENT 'manual | automatica',
    corretor_id         BIGINT UNSIGNED NULL,
    corretor_referencia VARCHAR(120) NULL COMMENT 'Identificacao do corretor automatizado, quando houver',
    corrigida_em        DATETIME NULL,
    created_at          DATETIME NULL,
    updated_at          DATETIME NULL,
    deleted_at          DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cqcd_tentativa_pergunta (tentativa_id, pergunta_id),
    KEY idx_cqcd_status (status),
    KEY idx_cqcd_corretor_id (corretor_id),
    KEY idx_cqcd_deleted_at (deleted_at),
    CONSTRAINT fk_cqcd_tentativa
        FOREIGN KEY (tentativa_id) REFERENCES conteudo_quiz_tentativas (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cqcd_pergunta
        FOREIGN KEY (pergunta_id) REFERENCES conteudo_quiz_perguntas (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cqcd_corretor
        FOREIGN KEY (corretor_id) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conteudo_quiz_correcoes_discursivas_historico (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    correcao_id        BIGINT UNSIGNED NOT NULL,
    nota_anterior      DECIMAL(6,2) NULL,
    nota_nova          DECIMAL(6,2) NULL,
    status_anterior    VARCHAR(20) NULL,
    status_novo        VARCHAR(20) NULL,
    rubrica_anterior   TEXT NULL,
    rubrica_nova       TEXT NULL,
    feedback_anterior  TEXT NULL,
    feedback_novo      TEXT NULL,
    origem             VARCHAR(20) NOT NULL DEFAULT 'manual',
    usuario_id         BIGINT UNSIGNED NULL,
    created_at         DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cqcdh_correcao_id (correcao_id),
    KEY idx_cqcdh_usuario_id (usuario_id),
    CONSTRAINT fk_cqcdh_correcao
        FOREIGN KEY (correcao_id) REFERENCES conteudo_quiz_correcoes_discursivas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

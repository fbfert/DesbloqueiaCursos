-- =====================================================================
-- 073 — Norminha IA V1: persistência da conversa
-- Docs: docs/norminha/CONTEXTO-EXECUCAO.md
--
-- Cria as quatro tabelas da assistente acadêmica: conversas, mensagens,
-- feedback do aluno e contadores de uso (base do rate limit, que não
-- existe hoje em lugar nenhum do projeto).
--
-- Nenhuma tabela existente é alterada. Nada aqui depende de IA: a Onda 0
-- opera inteira sobre estas tabelas sem uma única chamada a provedor.
--
-- Compatível com MySQL 5.7 (sem CHECK, CTE ou window function).
-- Idempotente: CREATE TABLE IF NOT EXISTS, pode ser reaplicada.
--
-- CONVENÇÃO DE NOMES: o projeto usa created_at/updated_at/deleted_at em
-- 113/89/74 tabelas; apenas a família tutor_* usa criado_em/atualizado_em
-- (7 tabelas). O plano mestre propunha criado_em, mas estas tabelas serão
-- lidas em conjunto com inscricoes, usuarios e conteudo_*, todas no padrão
-- dominante. Seguimos o padrão do projeto.
--
-- CHAVES ESTRANGEIRAS: o projeto as usa com parcimônia — revisao_comentarios
-- (072), por exemplo, tem FK só para cursos_eventos e deixa autor_id solto.
-- Aqui valem só as FKs INTERNAS da Norminha (mensagem -> conversa,
-- feedback -> mensagem), onde o CASCADE é desejado. usuario_id, inscricao_id,
-- curso_evento_id e turma_id ficam sem FK, como no restante do projeto: são
-- sempre revalidados contra a sessão na camada de serviço, e o sistema usa
-- exclusão lógica (lixeira), não hard delete.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Conversas
--
--    O aluno nunca vê o id numérico: o cliente usa o `uuid`, e toda busca
--    é por (uuid, usuario_id) — nunca só por uuid. Isso é o que impede um
--    aluno de abrir a conversa de outro adivinhando identificador.
--
--    O contexto acadêmico (inscrição, curso, turma) é gravado como o
--    servidor o VALIDOU, não como o browser sugeriu.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `norminha_conversas` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` CHAR(36) NOT NULL COMMENT 'identificador publico; o id numerico nunca sai do servidor',
    `usuario_id` BIGINT(20) UNSIGNED NOT NULL COMMENT 'sempre de Session::get(usuario_id)',
    `inscricao_id` BIGINT(20) UNSIGNED NULL,
    `curso_evento_id` BIGINT(20) UNSIGNED NULL,
    `turma_id` BIGINT(20) UNSIGNED NULL,
    `contexto` VARCHAR(40) NOT NULL DEFAULT 'area_aluno' COMMENT 'area_aluno, curso, aula, avaliacao',
    `rota` VARCHAR(255) NULL COMMENT 'rota onde a conversa comecou',
    `status` VARCHAR(20) NOT NULL DEFAULT 'ativa' COMMENT 'ativa, encerrada',
    `resumo` TEXT NULL COMMENT 'resumo curto para janela de memoria; sem PII',
    `ultima_mensagem_em` DATETIME NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_norminha_conversas_uuid` (`uuid`),
    KEY `idx_norminha_conversas_usuario` (`usuario_id`, `ultima_mensagem_em`),
    KEY `idx_norminha_conversas_inscricao` (`inscricao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. Mensagens
--
--    Guarda a conversa inteira para auditoria — inclusive o que foi
--    resolvido sem IA. O campo `resolved_by` é o dado mais valioso da
--    Onda 0: 'unresolved' marca a pergunta que o PHP não soube responder,
--    e é a partir dessa contagem que se decide se a Onda 1 se paga.
--
--    resolved_by: php | ai | hybrid | unresolved
--    papel:       user | assistant | tool
--
--    VARCHAR em vez de ENUM: acrescentar um valor novo não exige ALTER
--    numa tabela que tende a crescer. Mesmo critério de revisao_comentarios.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `norminha_mensagens` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversa_id` BIGINT(20) UNSIGNED NOT NULL,
    `usuario_id` BIGINT(20) UNSIGNED NULL COMMENT 'nulo quando a mensagem e do sistema',
    `papel` VARCHAR(20) NOT NULL COMMENT 'user, assistant, tool',
    `mensagem` MEDIUMTEXT NOT NULL,
    `intencao` VARCHAR(80) NULL COMMENT 'resume_course, show_progress, next_step, certificate_status...',
    `resolved_by` VARCHAR(20) NULL COMMENT 'php, ai, hybrid, unresolved',
    `tool_name` VARCHAR(100) NULL,
    `openai_response_id` VARCHAR(120) NULL,
    `modelo_ia` VARCHAR(80) NULL,
    `input_tokens` INT(11) NULL,
    `cached_input_tokens` INT(11) NULL,
    `output_tokens` INT(11) NULL,
    `latencia_ms` INT(11) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'ok' COMMENT 'ok, erro',
    `error_code` VARCHAR(80) NULL,
    `created_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_norminha_mensagens_conversa` (`conversa_id`, `id`),
    KEY `idx_norminha_mensagens_usuario_data` (`usuario_id`, `created_at`),
    KEY `idx_norminha_mensagens_resolucao` (`resolved_by`, `created_at`),
    CONSTRAINT `fk_norminha_msg_conversa` FOREIGN KEY (`conversa_id`)
        REFERENCES `norminha_conversas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. Feedback do aluno
--
--    Uma linha por (mensagem, usuário): o aluno pode mudar de ideia, não
--    acumular votos. O UNIQUE é o que torna o upsert seguro.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `norminha_feedback` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `mensagem_id` BIGINT(20) UNSIGNED NOT NULL,
    `usuario_id` BIGINT(20) UNSIGNED NOT NULL,
    `util` TINYINT(1) NOT NULL COMMENT '1 = util, 0 = nao util',
    `comentario` VARCHAR(1000) NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_norminha_feedback_usuario_msg` (`mensagem_id`, `usuario_id`),
    KEY `idx_norminha_feedback_usuario` (`usuario_id`),
    CONSTRAINT `fk_norminha_feedback_msg` FOREIGN KEY (`mensagem_id`)
        REFERENCES `norminha_mensagens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 4. Contadores de uso (base do rate limit)
--
--    A auditoria confirmou que o projeto NÃO tem nenhuma infraestrutura de
--    rate limit, e o servidor não tem Redis nem APCu — só MySQL. Uma linha
--    por (usuario_id, dia), atualizada com INSERT ... ON DUPLICATE KEY
--    UPDATE, que é atômico e correto sob concorrência (duas abas, duas
--    requisições simultâneas).
--
--    `janela_inicio` marca o começo da janela curta (5 minutos por padrão);
--    quando ela expira, o serviço reinicia janela_inicio e mensagens_janela
--    na mesma instrução.
--
--    IP não entra aqui: o aluno é identificado pela sessão. IP só no log de
--    segurança.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `norminha_uso` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` BIGINT(20) UNSIGNED NOT NULL,
    `dia` DATE NOT NULL,
    `janela_inicio` DATETIME NOT NULL COMMENT 'inicio da janela curta de rate limit',
    `mensagens_janela` INT(11) NOT NULL DEFAULT 0,
    `mensagens_dia` INT(11) NOT NULL DEFAULT 0,
    `mensagens_ia_dia` INT(11) NOT NULL DEFAULT 0 COMMENT 'subconjunto que consumiu provedor de IA',
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_norminha_uso_usuario_dia` (`usuario_id`, `dia`),
    KEY `idx_norminha_uso_janela` (`usuario_id`, `janela_inicio`),
    KEY `idx_norminha_uso_dia` (`dia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

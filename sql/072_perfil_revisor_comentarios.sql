-- =====================================================================
-- 072 — Perfil Revisor e comentários de revisão
-- Spec: specs/0002-perfil-revisor/
--
-- Cria a base para revisão de conteúdo por especialista externo:
-- um perfil que lê os cursos atribuídos e registra apontamentos, sem
-- qualquer permissão de gravação em conteúdo.
--
-- Compatível com MySQL 5.7. Idempotente: pode ser reaplicada sem
-- duplicar perfil, permissões nem vínculos.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Vínculo pessoa-curso passa a aceitar o tipo "revisor"
--    A tabela já vincula professor, tutor, mediador e palestrante a um
--    curso, com status próprio. O revisor entra no mesmo mecanismo, e é
--    por ele que o escopo de acesso será resolvido.
-- ---------------------------------------------------------------------

ALTER TABLE `curso_pessoas_vinculadas`
    MODIFY COLUMN `tipo_pessoa`
    ENUM('professor','tutor','mediador','palestrante','revisor') NOT NULL;


-- ---------------------------------------------------------------------
-- 2. Comentários de revisão
--
--    O alvo é polimórfico (alvo_tipo + alvo_id) porque um apontamento
--    pode recair sobre item de conteúdo, módulo, pergunta de quiz ou
--    alternativa — quatro tabelas distintas. Sem chave estrangeira,
--    seguindo o padrão já adotado em emails_envios (entidade_tipo /
--    entidade_id); a integridade é validada no Service.
--
--    severidade, status e alvo_tipo são VARCHAR e não ENUM, pela mesma
--    razão da migration 070: são campos com evolução prevista, e
--    acrescentar um valor não deve exigir ALTER TABLE.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `revisao_comentarios` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `curso_evento_id` BIGINT(20) UNSIGNED NOT NULL,
    `alvo_tipo` VARCHAR(30) NOT NULL COMMENT 'conteudo_item, conteudo_modulo, quiz_pergunta, quiz_alternativa',
    `alvo_id` BIGINT(20) UNSIGNED NOT NULL,
    `trecho` TEXT NULL COMMENT 'trecho exato a que o comentario se refere',
    `comentario` TEXT NOT NULL,
    `severidade` VARCHAR(20) NOT NULL DEFAULT 'sugestao' COMMENT 'erro, impreciso, sugestao, duvida',
    `status` VARCHAR(20) NOT NULL DEFAULT 'aberto' COMMENT 'aberto, aceito, recusado, resolvido',
    `resposta` TEXT NULL COMMENT 'o que o gestor de conteudo fez a respeito',
    `autor_id` BIGINT(20) UNSIGNED NOT NULL COMMENT 'usuario revisor',
    `triado_por` BIGINT(20) UNSIGNED NULL,
    `triado_em` DATETIME NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_revisao_curso_status` (`curso_evento_id`, `status`),
    KEY `idx_revisao_alvo` (`alvo_tipo`, `alvo_id`),
    KEY `idx_revisao_autor` (`autor_id`),
    KEY `idx_revisao_deleted` (`deleted_at`),
    CONSTRAINT `fk_revisao_curso` FOREIGN KEY (`curso_evento_id`)
        REFERENCES `cursos_eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. Perfil Revisor
--    sistema = 1 porque é perfil estrutural do portal, como Professor,
--    e não um perfil criado pelo operador.
-- ---------------------------------------------------------------------

INSERT INTO `perfis` (`nome`, `slug`, `descricao`, `status`, `sistema`, `created_at`, `updated_at`)
SELECT 'Revisor', 'revisor',
       'Leitura dos cursos atribuídos e registro de apontamentos de revisão, sem edição de conteúdo',
       'ativo', 1, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `perfis` WHERE `slug` = 'revisor');


-- ---------------------------------------------------------------------
-- 4. Permissões do revisor
--    Duas, e apenas duas. Nenhuma permissão terminada em "gerenciar"
--    de conteúdo pode ser concedida a este perfil: é essa ausência que
--    sustenta a premissa da feature, e ela é coberta por
--    tests/Unit/revisor_permissoes.php.
-- ---------------------------------------------------------------------

INSERT INTO `permissoes` (`modulo`, `acao`, `slug`, `nome`, `descricao`, `created_at`, `updated_at`)
SELECT 'area_curso_revisor', 'ver', 'area_curso.revisor.ver',
       'Ver área de revisão',
       'Acessar, somente para leitura, o conteúdo e o banco de questões dos cursos atribuídos',
       NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `permissoes` WHERE `slug` = 'area_curso.revisor.ver');

INSERT INTO `permissoes` (`modulo`, `acao`, `slug`, `nome`, `descricao`, `created_at`, `updated_at`)
SELECT 'area_curso_revisor', 'comentar', 'area_curso.revisor.comentar',
       'Comentar na revisão',
       'Registrar, editar e excluir os próprios apontamentos de revisão enquanto estiverem abertos',
       NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `permissoes` WHERE `slug` = 'area_curso.revisor.comentar');


-- ---------------------------------------------------------------------
-- 5. Vínculo perfil x permissões
-- ---------------------------------------------------------------------

INSERT INTO `perfil_permissoes` (`perfil_id`, `permissao_id`, `created_at`)
SELECT pf.`id`, pm.`id`, NOW()
FROM `perfis` pf
JOIN `permissoes` pm ON pm.`slug` IN ('area_curso.revisor.ver', 'area_curso.revisor.comentar')
WHERE pf.`slug` = 'revisor'
  AND NOT EXISTS (
      SELECT 1 FROM `perfil_permissoes` pp
      WHERE pp.`perfil_id` = pf.`id` AND pp.`permissao_id` = pm.`id`
  );


-- ---------------------------------------------------------------------
-- Conferência (rodar depois de aplicar)
-- ---------------------------------------------------------------------
-- SHOW COLUMNS FROM curso_pessoas_vinculadas LIKE 'tipo_pessoa';
-- SHOW TABLES LIKE 'revisao_comentarios';
-- SELECT p.slug FROM perfil_permissoes pp
--   JOIN perfis pf ON pf.id = pp.perfil_id
--   JOIN permissoes p ON p.id = pp.permissao_id
--  WHERE pf.slug = 'revisor';

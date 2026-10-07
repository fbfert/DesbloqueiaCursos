-- 081 — Lacunas de schema: cupons.escopo e frontend_menu_exibicao_regras
-- Compatibilidade: MySQL 5.7 / MariaDB 10.5
--
-- O código usa as duas estruturas desde as mudanças de cupom por curso (033) e de
-- regras de exibição dos menus, mas nenhuma migração as criava: existiam só no banco
-- de produção. Um banco montado apenas com sql/ (servidor novo, restauração,
-- ambiente local) quebrava ao criar ou editar cupom (Cupom::create grava escopo)
-- e ao salvar regras de menu.
--
-- As duas operações são condicionais: onde a estrutura já existe (produção), nada
-- muda. A tabela espelha frontend_modulo_exibicao_regras (041), com menu_id no
-- lugar de modulo_id. Pode rodar mais de uma vez.

SET NAMES utf8mb4;

-- cupons.escopo: 'todo_site' | 'cursos_especificos' (CupomService::normalizeEscopo)
SET @has_cupons_escopo := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cupons'
      AND COLUMN_NAME = 'escopo'
);
SET @sql_add_cupons_escopo := IF(
    @has_cupons_escopo = 0,
    'ALTER TABLE cupons ADD COLUMN escopo VARCHAR(40) NOT NULL DEFAULT ''todo_site'' AFTER descricao',
    'SELECT 1'
);
PREPARE stmt_add_cupons_escopo FROM @sql_add_cupons_escopo;
EXECUTE stmt_add_cupons_escopo;
DEALLOCATE PREPARE stmt_add_cupons_escopo;

-- Cupons que já têm cursos vinculados passam a ter o escopo correspondente
-- (mesma regra da 033, que rodava antes de a coluna existir em bancos novos).
UPDATE cupons c
INNER JOIN (
    SELECT DISTINCT cupom_id
    FROM cupom_cursos
) cc ON cc.cupom_id = c.id
SET c.escopo = 'cursos_especificos',
    c.updated_at = NOW()
WHERE c.deleted_at IS NULL
  AND c.escopo = 'todo_site';

CREATE TABLE IF NOT EXISTS frontend_menu_exibicao_regras (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    menu_id INT UNSIGNED NOT NULL,
    tipo_regra ENUM('include', 'exclude') NOT NULL,
    alvo_tipo ENUM('route', 'page_key', 'area', 'auth_state') NOT NULL,
    alvo_valor VARCHAR(191) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_frontend_menu_exibicao_regras_menu_id (menu_id),
    KEY idx_frontend_menu_exibicao_regras_ativo (ativo),
    KEY idx_frontend_menu_exibicao_regras_ordem (ordem),
    KEY idx_frontend_menu_exibicao_regras_alvo (alvo_tipo, alvo_valor),
    KEY idx_frontend_menu_exibicao_regras_menu_ativo_ordem (menu_id, ativo, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qualquer FK já existente em menu_id (com qualquer nome) conta: não duplica em produção.
SET @fk_menu_exists := (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'frontend_menu_exibicao_regras'
      AND COLUMN_NAME = 'menu_id'
      AND REFERENCED_TABLE_NAME IS NOT NULL
);
SET @fk_menu_sql := IF(
    @fk_menu_exists = 0,
    'ALTER TABLE frontend_menu_exibicao_regras ADD CONSTRAINT fk_frontend_menu_exibicao_regras_menu FOREIGN KEY (menu_id) REFERENCES frontend_menus(id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk_menu FROM @fk_menu_sql;
EXECUTE stmt_fk_menu;
DEALLOCATE PREPARE stmt_fk_menu;

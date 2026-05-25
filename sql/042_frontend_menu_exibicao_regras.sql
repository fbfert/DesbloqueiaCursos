-- Polo Rainbow - Regras de exibição dos menus de frontend
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

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

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_frontend_menu_exibicao_regras_menu'
);
SET @fk_sql = IF(
    @fk_exists = 0,
    'ALTER TABLE frontend_menu_exibicao_regras ADD CONSTRAINT fk_frontend_menu_exibicao_regras_menu FOREIGN KEY (menu_id) REFERENCES frontend_menus(id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk FROM @fk_sql;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

<?php

namespace App\Models;

use App\Core\Helpers;
use App\Core\Database;
use PDO;

class ConfiguracaoFrontend
{
    private $schemaEnsured = false;

    public function current()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM configuracoes_frontend
             WHERE deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function save(array $data)
    {
        $this->ensureSchema();

        $current = $this->current();

        $templateVisualPortal = isset($data['template_visual_portal']) ? strtolower(trim((string) $data['template_visual_portal'])) : '';
        if (!in_array($templateVisualPortal, array('v1', 'v2', 'v3', 'v4-claude'), true)) {
            $templateVisualPortal = 'v1';
        }

        $payload = array(
            'template_visual_portal' => $templateVisualPortal,
            'cor_primaria' => isset($data['cor_primaria']) ? trim((string) $data['cor_primaria']) : null,
            'cor_secundaria' => isset($data['cor_secundaria']) ? trim((string) $data['cor_secundaria']) : null,
            'logo_caminho' => isset($data['logo_caminho']) ? trim((string) $data['logo_caminho']) : null,
            'banner_caminho' => isset($data['banner_caminho']) ? trim((string) $data['banner_caminho']) : null,
            'descricao_home' => isset($data['descricao_home']) ? trim((string) $data['descricao_home']) : null,
            'home_destaques_limite' => $this->normalizeHomeDestaquesLimite(isset($data['home_destaques_limite']) ? $data['home_destaques_limite'] : null),
            'home_categorias_limite' => $this->normalizeHomeCategoriasLimite(isset($data['home_categorias_limite']) ? $data['home_categorias_limite'] : null),
            'frontend_card_gap' => Helpers::sanitizeCssSpacingValue(
                isset($data['frontend_card_gap']) ? $data['frontend_card_gap'] : null,
                'clamp(16px, 2vw, 24px)'
            ),
            'frontend_section_gap' => Helpers::sanitizeCssSpacingValue(
                isset($data['frontend_section_gap']) ? $data['frontend_section_gap'] : null,
                'clamp(24px, 3vw, 40px)'
            ),
        );

        if ($current) {
            $stmt = Database::connection()->prepare(
                'UPDATE configuracoes_frontend
                 SET template_visual_portal = :template_visual_portal,
                     cor_primaria = :cor_primaria,
                     cor_secundaria = :cor_secundaria,
                     logo_caminho = :logo_caminho,
                     banner_caminho = :banner_caminho,
                     descricao_home = :descricao_home,
                     home_destaques_limite = :home_destaques_limite,
                     home_categorias_limite = :home_categorias_limite,
                     frontend_card_gap = :frontend_card_gap,
                     frontend_section_gap = :frontend_section_gap,
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $stmt->execute(array_merge($payload, array('id' => (int) $current['id'])));
            return (int) $current['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO configuracoes_frontend
             (template_visual_portal, cor_primaria, cor_secundaria, logo_caminho, banner_caminho, descricao_home, home_destaques_limite, home_categorias_limite, frontend_card_gap, frontend_section_gap, created_at, updated_at, deleted_at)
             VALUES
             (:template_visual_portal, :cor_primaria, :cor_secundaria, :logo_caminho, :banner_caminho, :descricao_home, :home_destaques_limite, :home_categorias_limite, :frontend_card_gap, :frontend_section_gap, NOW(), NOW(), NULL)'
        );

        $stmt->execute($payload);

        return (int) Database::connection()->lastInsertId();
    }

    private function ensureSchema()
    {
        if ($this->schemaEnsured) {
            return;
        }

        $this->schemaEnsured = true;

        $frontendCardGapExists = $this->columnExists('frontend_card_gap');
        $frontendSectionGapExists = $this->columnExists('frontend_section_gap');
        $homeCategoriasLimiteExists = $this->columnExists('home_categorias_limite');

        if (!$frontendCardGapExists) {
            $afterColumn = $this->columnExists('home_destaques_limite') ? 'home_destaques_limite' : 'descricao_home';
            Database::connection()->exec(
                'ALTER TABLE configuracoes_frontend ADD COLUMN frontend_card_gap VARCHAR(191) NULL AFTER ' . $afterColumn
            );
            $frontendCardGapExists = true;
        }

        if (!$frontendSectionGapExists) {
            $afterColumn = $frontendCardGapExists ? 'frontend_card_gap' : ($this->columnExists('descricao_home') ? 'descricao_home' : 'home_destaques_limite');
            Database::connection()->exec(
                'ALTER TABLE configuracoes_frontend ADD COLUMN frontend_section_gap VARCHAR(191) NULL AFTER ' . $afterColumn
            );
        }

        if (!$homeCategoriasLimiteExists) {
            $afterColumn = $this->columnExists('home_destaques_limite')
                ? 'home_destaques_limite'
                : ($this->columnExists('descricao_home') ? 'descricao_home' : 'banner_caminho');
            Database::connection()->exec(
                'ALTER TABLE configuracoes_frontend ADD COLUMN home_categorias_limite INT NOT NULL DEFAULT 6 AFTER ' . $afterColumn
            );
        }
    }

    private function columnExists($column)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $stmt->execute(array(
            'table_name' => 'configuracoes_frontend',
            'column_name' => $column,
        ));

        return (int) $stmt->fetchColumn() > 0;
    }

    private function normalizeHomeDestaquesLimite($value)
    {
        if ($value === null) {
            return 6;
        }

        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^\d+$/', $value)) {
            return 6;
        }

        $limite = (int) $value;
        if ($limite < 1 || $limite > 12) {
            return 6;
        }

        return $limite;
    }

    private function normalizeHomeCategoriasLimite($value)
    {
        if ($value === null) {
            return 6;
        }

        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^\d+$/', $value)) {
            return 6;
        }

        $limite = (int) $value;
        if ($limite < 1 || $limite > 12) {
            return 6;
        }

        return $limite;
    }
}

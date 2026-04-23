<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConfiguracaoFrontend
{
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
        $current = $this->current();

        $payload = array(
            'template_visual_portal' => isset($data['template_visual_portal']) && trim((string) $data['template_visual_portal']) !== '' ? trim((string) $data['template_visual_portal']) : 'padrao',
            'cor_primaria' => isset($data['cor_primaria']) ? trim((string) $data['cor_primaria']) : null,
            'cor_secundaria' => isset($data['cor_secundaria']) ? trim((string) $data['cor_secundaria']) : null,
            'logo_caminho' => isset($data['logo_caminho']) ? trim((string) $data['logo_caminho']) : null,
            'banner_caminho' => isset($data['banner_caminho']) ? trim((string) $data['banner_caminho']) : null,
            'descricao_home' => isset($data['descricao_home']) ? trim((string) $data['descricao_home']) : null,
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
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $stmt->execute(array_merge($payload, array('id' => (int) $current['id'])));
            return (int) $current['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO configuracoes_frontend
             (template_visual_portal, cor_primaria, cor_secundaria, logo_caminho, banner_caminho, descricao_home, created_at, updated_at, deleted_at)
             VALUES
             (:template_visual_portal, :cor_primaria, :cor_secundaria, :logo_caminho, :banner_caminho, :descricao_home, NOW(), NOW(), NULL)'
        );

        $stmt->execute($payload);

        return (int) Database::connection()->lastInsertId();
    }
}

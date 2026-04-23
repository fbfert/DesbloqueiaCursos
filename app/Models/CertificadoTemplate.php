<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CertificadoTemplate
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM certificados_templates
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function defaultTemplate()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM certificados_templates
             WHERE deleted_at IS NULL
               AND ativo = 1
             ORDER BY padrao DESC, id ASC
             LIMIT 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function allActive()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM certificados_templates
             WHERE deleted_at IS NULL
               AND ativo = 1
             ORDER BY padrao DESC, nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

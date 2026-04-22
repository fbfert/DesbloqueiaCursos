<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Perfil
{
    public function all()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM perfis
             WHERE deleted_at IS NULL
             ORDER BY id ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM perfis WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('id' => $id));

        $perfil = $stmt->fetch(PDO::FETCH_ASSOC);

        return $perfil ?: null;
    }

    public function withPermissions()
    {
        $stmt = Database::connection()->query(
            'SELECT p.*, COUNT(pp.permissao_id) AS total_permissoes
             FROM perfis p
             LEFT JOIN perfil_permissoes pp ON pp.perfil_id = p.id
             WHERE p.deleted_at IS NULL
             GROUP BY p.id
             ORDER BY p.id ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

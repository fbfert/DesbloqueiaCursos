<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Categoria
{
    public function allWithCounts()
    {
        $stmt = Database::connection()->query(
            'SELECT c.*,
                    COALESCE(ce_total.total_cursos, 0) AS total_cursos
             FROM categorias c
             LEFT JOIN (
                SELECT categoria_id, COUNT(*) AS total_cursos
                FROM cursos_eventos
                WHERE deleted_at IS NULL
                GROUP BY categoria_id
             ) ce_total ON ce_total.categoria_id = c.id
             WHERE c.deleted_at IS NULL
             ORDER BY c.ordem ASC, c.nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM categorias
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CursoDestaque
{
    public function allWithCourse()
    {
        $stmt = Database::connection()->query(
            'SELECT cd.*,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    ce.tipo AS curso_tipo,
                    ce.modalidade AS curso_modalidade,
                    ce.status AS curso_status
             FROM cursos_destaque cd
             INNER JOIN cursos_eventos ce ON ce.id = cd.curso_evento_id
             WHERE cd.deleted_at IS NULL
               AND ce.deleted_at IS NULL
             ORDER BY cd.ordem ASC, cd.id ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

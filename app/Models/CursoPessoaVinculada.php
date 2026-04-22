<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CursoPessoaVinculada
{
    public function forCourse($cursoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT cpv.*
             FROM curso_pessoas_vinculadas cpv
             WHERE cpv.curso_evento_id = :curso_evento_id
               AND cpv.deleted_at IS NULL
             ORDER BY cpv.ordem ASC, cpv.nome ASC'
        );

        $stmt->execute(array('curso_evento_id' => $cursoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

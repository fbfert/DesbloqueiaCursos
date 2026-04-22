<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class UsuarioCurso
{
    public function forUser($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT uc.*
             FROM usuario_cursos uc
             WHERE uc.usuario_id = :usuario_id
               AND uc.deleted_at IS NULL
             ORDER BY uc.created_at DESC'
        );

        $stmt->execute(array('usuario_id' => $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

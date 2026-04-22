<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class UsuarioTurma
{
    public function forUser($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT ut.*
             FROM usuario_turmas ut
             WHERE ut.usuario_id = :usuario_id
               AND ut.deleted_at IS NULL
             ORDER BY ut.created_at DESC'
        );

        $stmt->execute(array('usuario_id' => $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class UsuarioPerfil
{
    public function forUser($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*
             FROM perfis p
             INNER JOIN usuario_perfis up ON up.perfil_id = p.id
             WHERE up.usuario_id = :usuario_id
               AND p.deleted_at IS NULL
             ORDER BY p.id ASC'
        );
        $stmt->execute(array('usuario_id' => $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sync($usuarioId, array $perfilIds)
    {
        $perfilIds = array_values(array_unique(array_map('intval', $perfilIds)));
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $delete = $pdo->prepare('DELETE FROM usuario_perfis WHERE usuario_id = :usuario_id');
            $delete->execute(array('usuario_id' => $usuarioId));

            if ($perfilIds) {
                $insert = $pdo->prepare(
                    'INSERT INTO usuario_perfis (usuario_id, perfil_id, created_at)
                     VALUES (:usuario_id, :perfil_id, NOW())'
                );

                foreach ($perfilIds as $perfilId) {
                    $insert->execute(array(
                        'usuario_id' => $usuarioId,
                        'perfil_id' => $perfilId,
                    ));
                }
            }

            $pdo->commit();
        } catch (\Exception $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}

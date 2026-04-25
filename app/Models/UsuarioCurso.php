<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class UsuarioCurso
{
    public function findProfessorForCourse($cursoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT uc.*
             FROM usuario_cursos uc
             WHERE uc.curso_evento_id = :curso_evento_id
               AND uc.tipo_vinculo = "professor"
               AND uc.deleted_at IS NULL
             ORDER BY uc.id ASC
             LIMIT 1'
        );

        $stmt->execute(array('curso_evento_id' => $cursoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

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

    public function syncProfessorForCourse($cursoId, $usuarioId, $status = 'ativo')
    {
        $existente = $this->findProfessorForCourse($cursoId);

        if (!$usuarioId) {
            if ($existente) {
                $stmt = Database::connection()->prepare(
                    'UPDATE usuario_cursos
                     SET deleted_at = NOW(), updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute(array('id' => $existente['id']));
            }

            return;
        }

        if ($existente) {
            $stmt = Database::connection()->prepare(
                'UPDATE usuario_cursos
                 SET usuario_id = :usuario_id,
                     status = :status,
                     deleted_at = NULL,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute(array(
                'usuario_id' => $usuarioId,
                'status' => $status,
                'id' => $existente['id'],
            ));
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO usuario_cursos
             (usuario_id, curso_evento_id, tipo_vinculo, status, created_at, updated_at, deleted_at)
             VALUES
             (:usuario_id, :curso_evento_id, "professor", :status, NOW(), NOW(), NULL)'
        );
        $stmt->execute(array(
            'usuario_id' => $usuarioId,
            'curso_evento_id' => $cursoId,
            'status' => $status,
        ));
    }
}

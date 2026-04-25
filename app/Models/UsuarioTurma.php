<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class UsuarioTurma
{
    public function findProfessorForTurma($turmaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT ut.*
             FROM usuario_turmas ut
             WHERE ut.turma_id = :turma_id
               AND ut.tipo_vinculo = "professor"
               AND ut.deleted_at IS NULL
             ORDER BY ut.id ASC
             LIMIT 1'
        );

        $stmt->execute(array('turma_id' => $turmaId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

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

    public function syncProfessorForTurma($turmaId, $usuarioId, $status = 'ativo')
    {
        $existente = $this->findProfessorForTurma($turmaId);

        if (!$usuarioId) {
            if ($existente) {
                $stmt = Database::connection()->prepare(
                    'UPDATE usuario_turmas
                     SET deleted_at = NOW(), updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute(array('id' => $existente['id']));
            }

            return;
        }

        if ($existente) {
            $stmt = Database::connection()->prepare(
                'UPDATE usuario_turmas
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
            'INSERT INTO usuario_turmas
             (usuario_id, turma_id, tipo_vinculo, status, created_at, updated_at, deleted_at)
             VALUES
             (:usuario_id, :turma_id, "professor", :status, NOW(), NOW(), NULL)'
        );
        $stmt->execute(array(
            'usuario_id' => $usuarioId,
            'turma_id' => $turmaId,
            'status' => $status,
        ));
    }
}

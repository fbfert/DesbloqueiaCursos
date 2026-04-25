<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CursoPessoaVinculada
{
    public function findProfessorResponsavel($cursoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT cpv.*
             FROM curso_pessoas_vinculadas cpv
             WHERE cpv.curso_evento_id = :curso_evento_id
               AND cpv.tipo_pessoa = "professor"
               AND cpv.deleted_at IS NULL
             ORDER BY cpv.ordem ASC, cpv.id ASC
             LIMIT 1'
        );

        $stmt->execute(array('curso_evento_id' => $cursoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

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

    public function syncProfessorResponsavel($cursoId, $usuarioId, $nome, $status = 'ativo')
    {
        $existente = $this->findProfessorResponsavel($cursoId);

        if (!$usuarioId) {
            if ($existente) {
                $stmt = Database::connection()->prepare(
                    'UPDATE curso_pessoas_vinculadas
                     SET deleted_at = NOW(), updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute(array('id' => $existente['id']));
            }

            return;
        }

        if ($existente) {
            $stmt = Database::connection()->prepare(
                'UPDATE curso_pessoas_vinculadas
                 SET usuario_id = :usuario_id,
                     nome = :nome,
                     status = :status,
                     ordem = 1,
                     deleted_at = NULL,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute(array(
                'usuario_id' => $usuarioId,
                'nome' => $nome,
                'status' => $status,
                'id' => $existente['id'],
            ));
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO curso_pessoas_vinculadas
             (curso_evento_id, usuario_id, nome, tipo_pessoa, ordem, status, created_at, updated_at, deleted_at)
             VALUES
             (:curso_evento_id, :usuario_id, :nome, "professor", 1, :status, NOW(), NOW(), NULL)'
        );
        $stmt->execute(array(
            'curso_evento_id' => $cursoId,
            'usuario_id' => $usuarioId,
            'nome' => $nome,
            'status' => $status,
        ));
    }
}

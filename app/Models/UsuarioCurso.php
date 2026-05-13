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

    public function syncProfessorForCourse($cursoId, $usuarioId, $status = 'ativo')
    {
        $usuarioIds = array();
        if (!empty($usuarioId)) {
            $usuarioIds[] = (int) $usuarioId;
        }

        $this->syncProfessoresForCourse($cursoId, $usuarioIds, $status);
    }

    public function syncProfessoresForCourse($cursoId, array $usuarioIds, $status = 'ativo')
    {
        $cursoId = (int) $cursoId;
        $selecionados = array();

        foreach ($usuarioIds as $usuarioId) {
            $usuarioId = (int) $usuarioId;
            if ($usuarioId <= 0 || isset($selecionados[$usuarioId])) {
                continue;
            }

            $selecionados[$usuarioId] = array(
                'usuario_id' => $usuarioId,
                'status' => $status,
                'ordem' => count($selecionados) + 1,
            );
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT *
             FROM usuario_cursos
             WHERE curso_evento_id = :curso_evento_id
               AND tipo_vinculo = "professor"
             ORDER BY deleted_at IS NULL DESC, id ASC'
        );
        $stmt->execute(array('curso_evento_id' => $cursoId));
        $existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $existentesPorUsuario = array();
        foreach ($existentes as $existente) {
            $usuarioIdExistente = isset($existente['usuario_id']) ? (int) $existente['usuario_id'] : 0;
            if ($usuarioIdExistente <= 0 || isset($existentesPorUsuario[$usuarioIdExistente])) {
                continue;
            }

            $existentesPorUsuario[$usuarioIdExistente] = $existente;
        }

        foreach ($selecionados as $usuarioId => $vinculo) {
            if (isset($existentesPorUsuario[$usuarioId])) {
                $stmt = $pdo->prepare(
                    'UPDATE usuario_cursos
                     SET usuario_id = :usuario_id,
                         tipo_vinculo = "professor",
                         status = :status,
                         deleted_at = NULL,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute(array(
                    'usuario_id' => $vinculo['usuario_id'],
                    'status' => $vinculo['status'],
                    'id' => $existentesPorUsuario[$usuarioId]['id'],
                ));
                continue;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO usuario_cursos
                 (usuario_id, curso_evento_id, tipo_vinculo, status, created_at, updated_at, deleted_at)
                 VALUES
                 (:usuario_id, :curso_evento_id, "professor", :status, NOW(), NOW(), NULL)'
            );
            $stmt->execute(array(
                'usuario_id' => $vinculo['usuario_id'],
                'curso_evento_id' => $cursoId,
                'status' => $vinculo['status'],
            ));
        }

        foreach ($existentes as $existente) {
            $usuarioIdExistente = isset($existente['usuario_id']) ? (int) $existente['usuario_id'] : 0;
            if ($usuarioIdExistente <= 0 || isset($selecionados[$usuarioIdExistente])) {
                continue;
            }

            if (!empty($existente['deleted_at'])) {
                continue;
            }

            $stmt = $pdo->prepare(
                'UPDATE usuario_cursos
                 SET deleted_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute(array('id' => $existente['id']));
        }
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
}

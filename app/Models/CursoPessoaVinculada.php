<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CursoPessoaVinculada
{
    public function findProfessorResponsavel($cursoId)
    {
        $professores = $this->findProfessoresResponsaveis($cursoId);
        return !empty($professores) ? $professores[0] : null;
    }

    public function findProfessoresResponsaveis($cursoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT cpv.*
             FROM curso_pessoas_vinculadas cpv
             WHERE cpv.curso_evento_id = :curso_evento_id
               AND cpv.tipo_pessoa = "professor"
               AND cpv.deleted_at IS NULL
             ORDER BY cpv.ordem ASC, cpv.nome ASC, cpv.id ASC'
        );

        $stmt->execute(array('curso_evento_id' => $cursoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $professores = array();
        if (!empty($usuarioId)) {
            $professores[] = array(
                'usuario_id' => (int) $usuarioId,
                'nome' => (string) $nome,
            );
        }

        $this->syncProfessoresResponsaveis($cursoId, $professores, $status);
    }

    public function syncProfessoresResponsaveis($cursoId, array $professores, $status = 'ativo')
    {
        $cursoId = (int) $cursoId;
        $selecionados = array();

        foreach ($professores as $ordem => $professor) {
            $usuarioId = 0;
            $nome = '';

            if (is_array($professor)) {
                if (isset($professor['usuario_id'])) {
                    $usuarioId = (int) $professor['usuario_id'];
                } elseif (isset($professor['id'])) {
                    $usuarioId = (int) $professor['id'];
                }

                if (isset($professor['nome'])) {
                    $nome = trim((string) $professor['nome']);
                }
            } else {
                $usuarioId = (int) $professor;
            }

            if ($usuarioId <= 0 || $nome === '') {
                continue;
            }

            if (isset($selecionados[$usuarioId])) {
                continue;
            }

            $selecionados[$usuarioId] = array(
                'usuario_id' => $usuarioId,
                'nome' => $nome,
                'ordem' => count($selecionados) + 1,
                'status' => $status,
            );
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT *
             FROM curso_pessoas_vinculadas
             WHERE curso_evento_id = :curso_evento_id
               AND tipo_pessoa = "professor"
             ORDER BY deleted_at IS NULL DESC, ordem ASC, nome ASC, id ASC'
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

        foreach ($selecionados as $usuarioId => $professor) {
            if (isset($existentesPorUsuario[$usuarioId])) {
                $stmt = $pdo->prepare(
                    'UPDATE curso_pessoas_vinculadas
                     SET usuario_id = :usuario_id,
                         nome = :nome,
                         tipo_pessoa = "professor",
                         ordem = :ordem,
                         status = :status,
                         deleted_at = NULL,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute(array(
                    'usuario_id' => $professor['usuario_id'],
                    'nome' => $professor['nome'],
                    'ordem' => $professor['ordem'],
                    'status' => $professor['status'],
                    'id' => $existentesPorUsuario[$usuarioId]['id'],
                ));
                continue;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO curso_pessoas_vinculadas
                 (curso_evento_id, usuario_id, nome, tipo_pessoa, ordem, status, created_at, updated_at, deleted_at)
                 VALUES
                 (:curso_evento_id, :usuario_id, :nome, "professor", :ordem, :status, NOW(), NOW(), NULL)'
            );
            $stmt->execute(array(
                'curso_evento_id' => $cursoId,
                'usuario_id' => $professor['usuario_id'],
                'nome' => $professor['nome'],
                'ordem' => $professor['ordem'],
                'status' => $professor['status'],
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
                'UPDATE curso_pessoas_vinculadas
                 SET deleted_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute(array('id' => $existente['id']));
        }
    }
}

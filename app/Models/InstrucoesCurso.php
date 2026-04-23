<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class InstrucoesCurso
{
    public function findByContext($cursoId, $turmaId = null)
    {
        $sql = 'SELECT *
                FROM instrucoes_curso
                WHERE curso_evento_id = :curso_evento_id
                  AND deleted_at IS NULL';
        $params = array('curso_evento_id' => $cursoId);

        if ($turmaId !== null) {
            $sql .= ' AND (turma_id = :turma_id OR turma_id IS NULL)';
            $params['turma_id'] = $turmaId;
        } else {
            $sql .= ' AND turma_id IS NULL';
        }

        $sql .= ' ORDER BY turma_id IS NULL ASC, visivel DESC, ordem ASC, id DESC LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function listForContext($cursoId, $turmaId = null)
    {
        $sql = 'SELECT *
                FROM instrucoes_curso
                WHERE curso_evento_id = :curso_evento_id
                  AND deleted_at IS NULL';
        $params = array('curso_evento_id' => $cursoId);

        if ($turmaId !== null) {
            $sql .= ' AND (turma_id = :turma_id OR turma_id IS NULL)';
            $params['turma_id'] = $turmaId;
        } else {
            $sql .= ' AND turma_id IS NULL';
        }

        $sql .= ' ORDER BY turma_id IS NULL ASC, ordem ASC, id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare('SELECT * FROM instrucoes_curso WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO instrucoes_curso
             (curso_evento_id, turma_id, titulo, conteudo, visivel, ordem, created_at, updated_at, deleted_at)
             VALUES
             (:curso_evento_id, :turma_id, :titulo, :conteudo, :visivel, :ordem, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'titulo' => $data['titulo'],
            'conteudo' => isset($data['conteudo']) ? $data['conteudo'] : null,
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE instrucoes_curso
             SET curso_evento_id = :curso_evento_id,
                 turma_id = :turma_id,
                 titulo = :titulo,
                 conteudo = :conteudo,
                 visivel = :visivel,
                 ordem = :ordem,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'titulo' => $data['titulo'],
            'conteudo' => isset($data['conteudo']) ? $data['conteudo'] : null,
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
            'id' => $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare('UPDATE instrucoes_curso SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id));
    }
}

<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Modulo
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare('SELECT * FROM modulos WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForContext($cursoId, $turmaId = null)
    {
        $sql = 'SELECT m.*
                FROM modulos m
                WHERE m.curso_evento_id = :curso_evento_id
                  AND m.deleted_at IS NULL';
        $params = array('curso_evento_id' => $cursoId);

        if ($turmaId !== null) {
            $sql .= ' AND (m.turma_id = :turma_id OR m.turma_id IS NULL)';
            $params['turma_id'] = $turmaId;
        } else {
            $sql .= ' AND m.turma_id IS NULL';
        }

        $sql .= ' ORDER BY m.turma_id IS NULL ASC, FIELD(m.status, "publicado", "rascunho", "oculto") ASC, m.ordem ASC, m.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO modulos
             (curso_evento_id, turma_id, titulo, descricao, visivel, status, criado_por, atualizado_por, ordem, created_at, updated_at, deleted_at)
             VALUES
             (:curso_evento_id, :turma_id, :titulo, :descricao, :visivel, :status, :criado_por, :atualizado_por, :ordem, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'titulo' => $data['titulo'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'status' => isset($data['status']) ? $data['status'] : 'publicado',
            'criado_por' => isset($data['criado_por']) ? $data['criado_por'] : null,
            'atualizado_por' => isset($data['atualizado_por']) ? $data['atualizado_por'] : null,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE modulos
             SET curso_evento_id = :curso_evento_id,
                 turma_id = :turma_id,
                 titulo = :titulo,
                 descricao = :descricao,
                 visivel = :visivel,
                 status = :status,
                 criado_por = COALESCE(:criado_por, criado_por),
                 atualizado_por = :atualizado_por,
                 ordem = :ordem,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'titulo' => $data['titulo'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'status' => isset($data['status']) ? $data['status'] : 'publicado',
            'criado_por' => isset($data['criado_por']) ? $data['criado_por'] : null,
            'atualizado_por' => isset($data['atualizado_por']) ? $data['atualizado_por'] : null,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
            'id' => $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare('UPDATE modulos SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id));
    }
}

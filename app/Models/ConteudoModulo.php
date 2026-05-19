<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoModulo
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_modulos
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForCurso($cursoEventoId, $status = null)
    {
        $sql = 'SELECT *
                FROM conteudo_modulos
                WHERE curso_evento_id = :curso_evento_id
                  AND deleted_at IS NULL';
        $params = array('curso_evento_id' => (int) $cursoEventoId);

        if ($status !== null && $status !== '') {
            $sql .= ' AND status = :status';
            $params['status'] = (string) $status;
        }

        $sql .= ' ORDER BY ordem ASC, id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_modulos
             (curso_evento_id, titulo, descricao, ordem, status, criado_por, atualizado_por, created_at, updated_at, deleted_at)
             VALUES
             (:curso_evento_id, :titulo, :descricao, :ordem, :status, :criado_por, :atualizado_por, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'curso_evento_id' => (int) $data['curso_evento_id'],
            'titulo' => (string) $data['titulo'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
            'status' => isset($data['status']) ? (string) $data['status'] : 'rascunho',
            'criado_por' => isset($data['criado_por']) ? (int) $data['criado_por'] : null,
            'atualizado_por' => isset($data['atualizado_por']) ? (int) $data['atualizado_por'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_modulos
             SET titulo = :titulo,
                 descricao = :descricao,
                 ordem = :ordem,
                 status = :status,
                 criado_por = COALESCE(:criado_por, criado_por),
                 atualizado_por = :atualizado_por,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'titulo' => (string) $data['titulo'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
            'status' => isset($data['status']) ? (string) $data['status'] : 'rascunho',
            'criado_por' => isset($data['criado_por']) ? (int) $data['criado_por'] : null,
            'atualizado_por' => isset($data['atualizado_por']) ? (int) $data['atualizado_por'] : null,
            'id' => (int) $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_modulos
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(array('id' => (int) $id));
    }

    public function updateStatus($id, $status, $actorUserId = null)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_modulos
             SET status = :status,
                 atualizado_por = :atualizado_por,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(array(
            'status' => (string) $status,
            'atualizado_por' => $actorUserId ? (int) $actorUserId : null,
            'id' => (int) $id,
        ));

        return $stmt->rowCount() > 0;
    }
}


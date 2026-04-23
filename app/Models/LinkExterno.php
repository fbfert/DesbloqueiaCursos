<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class LinkExterno
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare('SELECT * FROM links_externos WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForContext($cursoId, $turmaId = null, $moduloId = null, $aulaId = null)
    {
        $sql = 'SELECT *
                FROM links_externos
                WHERE curso_evento_id = :curso_evento_id
                  AND deleted_at IS NULL';
        $params = array('curso_evento_id' => $cursoId);

        if ($turmaId !== null) {
            $sql .= ' AND (turma_id = :turma_id OR turma_id IS NULL)';
            $params['turma_id'] = $turmaId;
        } else {
            $sql .= ' AND turma_id IS NULL';
        }

        if ($moduloId !== null) {
            $sql .= ' AND (modulo_id = :modulo_id OR modulo_id IS NULL)';
            $params['modulo_id'] = $moduloId;
        }

        if ($aulaId !== null) {
            $sql .= ' AND (aula_id = :aula_id OR aula_id IS NULL)';
            $params['aula_id'] = $aulaId;
        }

        $sql .= ' ORDER BY visivel DESC, ordem ASC, id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO links_externos
             (curso_evento_id, turma_id, modulo_id, aula_id, titulo, url, tipo_link, visivel, ordem, created_at, updated_at, deleted_at)
             VALUES
             (:curso_evento_id, :turma_id, :modulo_id, :aula_id, :titulo, :url, :tipo_link, :visivel, :ordem, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'modulo_id' => isset($data['modulo_id']) ? $data['modulo_id'] : null,
            'aula_id' => isset($data['aula_id']) ? $data['aula_id'] : null,
            'titulo' => $data['titulo'],
            'url' => $data['url'],
            'tipo_link' => isset($data['tipo_link']) ? $data['tipo_link'] : 'generico',
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE links_externos
             SET curso_evento_id = :curso_evento_id,
                 turma_id = :turma_id,
                 modulo_id = :modulo_id,
                 aula_id = :aula_id,
                 titulo = :titulo,
                 url = :url,
                 tipo_link = :tipo_link,
                 visivel = :visivel,
                 ordem = :ordem,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'modulo_id' => isset($data['modulo_id']) ? $data['modulo_id'] : null,
            'aula_id' => isset($data['aula_id']) ? $data['aula_id'] : null,
            'titulo' => $data['titulo'],
            'url' => $data['url'],
            'tipo_link' => isset($data['tipo_link']) ? $data['tipo_link'] : 'generico',
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
            'id' => $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare('UPDATE links_externos SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id));
    }
}

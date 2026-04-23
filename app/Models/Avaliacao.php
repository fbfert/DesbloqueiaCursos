<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Avaliacao
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare('SELECT * FROM avaliacoes WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForContext($cursoId, $turmaId = null)
    {
        $sql = 'SELECT *
                FROM avaliacoes
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

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO avaliacoes
             (curso_evento_id, turma_id, titulo, descricao, tipo, visivel, obrigatoria, percentual_minimo, nota_minima, ordem, created_at, updated_at, deleted_at)
             VALUES
             (:curso_evento_id, :turma_id, :titulo, :descricao, :tipo, :visivel, :obrigatoria, :percentual_minimo, :nota_minima, :ordem, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'titulo' => $data['titulo'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'tipo' => isset($data['tipo']) ? $data['tipo'] : 'avaliacao',
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'obrigatoria' => !empty($data['obrigatoria']) ? 1 : 0,
            'percentual_minimo' => isset($data['percentual_minimo']) ? $data['percentual_minimo'] : 0,
            'nota_minima' => isset($data['nota_minima']) ? $data['nota_minima'] : null,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE avaliacoes
             SET curso_evento_id = :curso_evento_id,
                 turma_id = :turma_id,
                 titulo = :titulo,
                 descricao = :descricao,
                 tipo = :tipo,
                 visivel = :visivel,
                 obrigatoria = :obrigatoria,
                 percentual_minimo = :percentual_minimo,
                 nota_minima = :nota_minima,
                 ordem = :ordem,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'titulo' => $data['titulo'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'tipo' => isset($data['tipo']) ? $data['tipo'] : 'avaliacao',
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'obrigatoria' => !empty($data['obrigatoria']) ? 1 : 0,
            'percentual_minimo' => isset($data['percentual_minimo']) ? $data['percentual_minimo'] : 0,
            'nota_minima' => isset($data['nota_minima']) ? $data['nota_minima'] : null,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
            'id' => $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare('UPDATE avaliacoes SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id));
    }
}

<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Categoria
{
    public function allWithCounts()
    {
        $stmt = Database::connection()->query(
            'SELECT c.*,
                    COALESCE(ce_total.total_cursos, 0) AS total_cursos
             FROM categorias c
             LEFT JOIN (
                SELECT categoria_id, COUNT(*) AS total_cursos
                FROM cursos_eventos
                WHERE deleted_at IS NULL
                GROUP BY categoria_id
             ) ce_total ON ce_total.categoria_id = c.id
             WHERE c.deleted_at IS NULL
             ORDER BY c.ordem ASC, c.nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM categorias
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function allForSelect()
    {
        $stmt = Database::connection()->query(
            'SELECT id, nome, slug
             FROM categorias
             WHERE deleted_at IS NULL
             ORDER BY ordem ASC, nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBySlug($slug)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM categorias
             WHERE slug = :slug
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('slug' => $slug));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO categorias
             (nome, slug, descricao, parent_id, ordem, status, created_at, updated_at, deleted_at)
             VALUES
             (:nome, :slug, :descricao, :parent_id, :ordem, :status, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'nome' => $data['nome'],
            'slug' => $data['slug'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'parent_id' => isset($data['parent_id']) ? $data['parent_id'] : null,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
            'status' => isset($data['status']) ? $data['status'] : 'ativo',
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE categorias
             SET nome = :nome,
                 slug = :slug,
                 descricao = :descricao,
                 parent_id = :parent_id,
                 ordem = :ordem,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'nome' => $data['nome'],
            'slug' => $data['slug'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'parent_id' => isset($data['parent_id']) ? $data['parent_id'] : null,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
            'status' => isset($data['status']) ? $data['status'] : 'ativo',
            'id' => $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare('UPDATE categorias SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id));
    }
}

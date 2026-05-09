<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Perfil
{
    public function all()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM perfis
             WHERE deleted_at IS NULL
             ORDER BY id ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM perfis WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('id' => $id));

        $perfil = $stmt->fetch(PDO::FETCH_ASSOC);

        return $perfil ?: null;
    }

    public function findBySlug($slug, $excludeId = null)
    {
        $sql = 'SELECT *
                FROM perfis
                WHERE slug = :slug
                  AND deleted_at IS NULL';
        $params = array('slug' => (string) $slug);

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = (int) $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $perfil = $stmt->fetch(PDO::FETCH_ASSOC);
        return $perfil ?: null;
    }

    public function withPermissions()
    {
        $stmt = Database::connection()->query(
            'SELECT p.*, COUNT(pp.permissao_id) AS total_permissoes
             FROM perfis p
             LEFT JOIN perfil_permissoes pp ON pp.perfil_id = p.id
             WHERE p.deleted_at IS NULL
             GROUP BY p.id
             ORDER BY p.id ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO perfis
             (nome, slug, descricao, status, sistema, created_at, updated_at, deleted_at)
             VALUES
             (:nome, :slug, :descricao, :status, :sistema, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'nome' => $data['nome'],
            'slug' => $data['slug'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'ativo',
            'sistema' => !empty($data['sistema']) ? 1 : 0,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE perfis
             SET nome = :nome,
                 slug = :slug,
                 descricao = :descricao,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute(array(
            'nome' => $data['nome'],
            'slug' => $data['slug'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'ativo',
            'id' => (int) $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE perfis
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array('id' => (int) $id));
    }
}

<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Permissao
{
    public function all()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM permissoes
             WHERE deleted_at IS NULL
             ORDER BY modulo ASC, acao ASC, id ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function groupedByModule()
    {
        $grouped = array();

        foreach ($this->all() as $permissao) {
            $module = $permissao['modulo'];
            if (!isset($grouped[$module])) {
                $grouped[$module] = array();
            }
            $grouped[$module][] = $permissao;
        }

        return $grouped;
    }

    public function findByIds(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (!$ids) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM permissoes
             WHERE id IN (' . $placeholders . ')
             ORDER BY modulo ASC, acao ASC, id ASC'
        );
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM permissoes
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    public function findBySlug($slug, $excludeId = null)
    {
        $sql = 'SELECT *
                FROM permissoes
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
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO permissoes
             (modulo, acao, slug, nome, descricao, created_at, updated_at, deleted_at)
             VALUES
             (:modulo, :acao, :slug, :nome, :descricao, NOW(), NOW(), NULL)'
        );
        $stmt->execute(array(
            'modulo' => $data['modulo'],
            'acao' => $data['acao'],
            'slug' => $data['slug'],
            'nome' => $data['nome'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
        ));
        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE permissoes
             SET modulo = :modulo,
                 acao = :acao,
                 slug = :slug,
                 nome = :nome,
                 descricao = :descricao,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array(
            'modulo' => $data['modulo'],
            'acao' => $data['acao'],
            'slug' => $data['slug'],
            'nome' => $data['nome'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'id' => (int) $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE permissoes
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array('id' => (int) $id));
    }
}

<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Pagina
{
    public function allAdmin()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM paginas
             WHERE deleted_at IS NULL
             ORDER BY ordem ASC, titulo ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM paginas
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findBySlug($slug, $excludeId = null)
    {
        $sql = 'SELECT *
                FROM paginas
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
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByRota($rota, $excludeId = null)
    {
        $sql = 'SELECT *
                FROM paginas
                WHERE rota = :rota
                  AND deleted_at IS NULL';

        $params = array('rota' => (string) $rota);
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = (int) $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findPublicByRota($rota)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM paginas
             WHERE rota = :rota
               AND status = "publicada"
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('rota' => (string) $rota));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO paginas
             (titulo, slug, rota, resumo, conteudo_html, status, ordem, publicada_em, created_at, updated_at, deleted_at)
             VALUES
             (:titulo, :slug, :rota, :resumo, :conteudo_html, :status, :ordem, :publicada_em, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'titulo' => $data['titulo'],
            'slug' => $data['slug'],
            'rota' => $data['rota'],
            'resumo' => isset($data['resumo']) ? $data['resumo'] : null,
            'conteudo_html' => isset($data['conteudo_html']) ? $data['conteudo_html'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'rascunho',
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
            'publicada_em' => isset($data['publicada_em']) ? $data['publicada_em'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE paginas
             SET titulo = :titulo,
                 slug = :slug,
                 rota = :rota,
                 resumo = :resumo,
                 conteudo_html = :conteudo_html,
                 status = :status,
                 ordem = :ordem,
                 publicada_em = :publicada_em,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'titulo' => $data['titulo'],
            'slug' => $data['slug'],
            'rota' => $data['rota'],
            'resumo' => isset($data['resumo']) ? $data['resumo'] : null,
            'conteudo_html' => isset($data['conteudo_html']) ? $data['conteudo_html'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'rascunho',
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
            'publicada_em' => isset($data['publicada_em']) ? $data['publicada_em'] : null,
            'id' => (int) $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE paginas
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array('id' => (int) $id));
    }
}

<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class FrontendMenuItem
{
    public function allByMenu($menuId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM frontend_menu_itens
             WHERE menu_id = :menu_id
               AND deleted_at IS NULL
             ORDER BY ordem ASC, id ASC'
        );
        $stmt->execute(array('menu_id' => (int) $menuId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM frontend_menu_itens
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listActiveByMenu($menuId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM frontend_menu_itens
             WHERE menu_id = :menu_id
               AND ativo = 1
               AND deleted_at IS NULL
             ORDER BY ordem ASC, id ASC'
        );
        $stmt->execute(array('menu_id' => (int) $menuId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO frontend_menu_itens
             (menu_id, rotulo, url, target, rel, ativo, ordem, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
             VALUES
             (:menu_id, :rotulo, :url, :target, :rel, :ativo, :ordem, :criado_por, :atualizado_por, NULL, NULL, NOW(), NOW(), NULL)'
        );
        $stmt->execute(array(
            'menu_id' => (int) $data['menu_id'],
            'rotulo' => $data['rotulo'],
            'url' => $data['url'],
            'target' => $data['target'],
            'rel' => $data['rel'],
            'ativo' => (int) $data['ativo'],
            'ordem' => (int) $data['ordem'],
            'criado_por' => $data['criado_por'],
            'atualizado_por' => $data['atualizado_por'],
        ));
        return (int) Database::connection()->lastInsertId();
    }

    public function update($id, array $data)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE frontend_menu_itens
             SET rotulo = :rotulo,
                 url = :url,
                 target = :target,
                 rel = :rel,
                 ativo = :ativo,
                 ordem = :ordem,
                 atualizado_por = :atualizado_por,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array(
            'id' => (int) $id,
            'rotulo' => $data['rotulo'],
            'url' => $data['url'],
            'target' => $data['target'],
            'rel' => $data['rel'],
            'ativo' => (int) $data['ativo'],
            'ordem' => (int) $data['ordem'],
            'atualizado_por' => $data['atualizado_por'],
        ));
    }

    public function softDelete($id, $excluidoPor, $justificativa)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE frontend_menu_itens
             SET deleted_at = NOW(),
                 updated_at = NOW(),
                 excluido_por = :excluido_por,
                 justificativa_exclusao = :justificativa_exclusao
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array(
            'id' => (int) $id,
            'excluido_por' => $excluidoPor !== null ? (int) $excluidoPor : null,
            'justificativa_exclusao' => $justificativa,
        ));
    }
}

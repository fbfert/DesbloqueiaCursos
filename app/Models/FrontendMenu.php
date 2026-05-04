<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class FrontendMenu
{
    public function allAdmin()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM frontend_menus
             WHERE deleted_at IS NULL
             ORDER BY ordem ASC, id ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM frontend_menus
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCode($codigo, $excludeId = null)
    {
        $sql = 'SELECT *
                FROM frontend_menus
                WHERE codigo = :codigo
                  AND deleted_at IS NULL';
        $params = array('codigo' => (string) $codigo);
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

    public function findActiveByPositionOrCode($posicao, $codigo = null)
    {
        $sql = 'SELECT *
                FROM frontend_menus
                WHERE ativo = 1
                  AND deleted_at IS NULL';
        $params = array();
        if ($codigo !== null && trim((string) $codigo) !== '') {
            $sql .= ' AND codigo = :codigo';
            $params['codigo'] = (string) $codigo;
        } else {
            $sql .= ' AND posicao = :posicao';
            $params['posicao'] = (string) $posicao;
        }
        $sql .= ' ORDER BY ordem ASC, id ASC LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO frontend_menus
             (codigo, nome_admin, posicao, ativo, ordem, observacoes_admin, criado_por, atualizado_por, excluido_por, justificativa_exclusao, created_at, updated_at, deleted_at)
             VALUES
             (:codigo, :nome_admin, :posicao, :ativo, :ordem, :observacoes_admin, :criado_por, :atualizado_por, NULL, NULL, NOW(), NOW(), NULL)'
        );
        $stmt->execute(array(
            'codigo' => $data['codigo'],
            'nome_admin' => $data['nome_admin'],
            'posicao' => $data['posicao'],
            'ativo' => (int) $data['ativo'],
            'ordem' => (int) $data['ordem'],
            'observacoes_admin' => $data['observacoes_admin'],
            'criado_por' => $data['criado_por'],
            'atualizado_por' => $data['atualizado_por'],
        ));
        return (int) Database::connection()->lastInsertId();
    }

    public function update($id, array $data)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE frontend_menus
             SET codigo = :codigo,
                 nome_admin = :nome_admin,
                 posicao = :posicao,
                 ativo = :ativo,
                 ordem = :ordem,
                 observacoes_admin = :observacoes_admin,
                 atualizado_por = :atualizado_por,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array(
            'id' => (int) $id,
            'codigo' => $data['codigo'],
            'nome_admin' => $data['nome_admin'],
            'posicao' => $data['posicao'],
            'ativo' => (int) $data['ativo'],
            'ordem' => (int) $data['ordem'],
            'observacoes_admin' => $data['observacoes_admin'],
            'atualizado_por' => $data['atualizado_por'],
        ));
    }

    public function softDelete($id, $excluidoPor, $justificativa)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE frontend_menus
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

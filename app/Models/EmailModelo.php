<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class EmailModelo
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM emails_modelos
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByEvento($evento)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM emails_modelos
             WHERE evento = :evento
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('evento' => (string) $evento));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function allForAdmin()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM emails_modelos
             WHERE deleted_at IS NULL
             ORDER BY nome ASC, id ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO emails_modelos
             (evento, template, nome, assunto, corpo_html, gatilho_descricao, variaveis_json, ativo, editavel, created_at, updated_at, deleted_at)
             VALUES
             (:evento, :template, :nome, :assunto, :corpo_html, :gatilho_descricao, :variaveis_json, :ativo, :editavel, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'evento' => $data['evento'],
            'template' => $data['template'],
            'nome' => $data['nome'],
            'assunto' => $data['assunto'],
            'corpo_html' => $data['corpo_html'],
            'gatilho_descricao' => isset($data['gatilho_descricao']) ? $data['gatilho_descricao'] : null,
            'variaveis_json' => isset($data['variaveis_json']) ? $data['variaveis_json'] : null,
            'ativo' => !empty($data['ativo']) ? 1 : 0,
            'editavel' => !empty($data['editavel']) ? 1 : 0,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update($id, array $data)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE emails_modelos
             SET nome = :nome,
                 assunto = :assunto,
                 corpo_html = :corpo_html,
                 gatilho_descricao = :gatilho_descricao,
                 variaveis_json = :variaveis_json,
                 ativo = :ativo,
                 editavel = :editavel,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute(array(
            'nome' => $data['nome'],
            'assunto' => $data['assunto'],
            'corpo_html' => $data['corpo_html'],
            'gatilho_descricao' => isset($data['gatilho_descricao']) ? $data['gatilho_descricao'] : null,
            'variaveis_json' => isset($data['variaveis_json']) ? $data['variaveis_json'] : null,
            'ativo' => !empty($data['ativo']) ? 1 : 0,
            'editavel' => !empty($data['editavel']) ? 1 : 0,
            'id' => (int) $id,
        ));
    }

    public function updateActive($id, $ativo)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE emails_modelos
             SET ativo = :ativo,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute(array(
            'ativo' => !empty($ativo) ? 1 : 0,
            'id' => (int) $id,
        ));
    }

    public function restoreDefault($id, array $data)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE emails_modelos
             SET nome = :nome,
                 assunto = :assunto,
                 corpo_html = :corpo_html,
                 gatilho_descricao = :gatilho_descricao,
                 variaveis_json = :variaveis_json,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute(array(
            'nome' => $data['nome'],
            'assunto' => $data['assunto'],
            'corpo_html' => $data['corpo_html'],
            'gatilho_descricao' => isset($data['gatilho_descricao']) ? $data['gatilho_descricao'] : null,
            'variaveis_json' => isset($data['variaveis_json']) ? $data['variaveis_json'] : null,
            'id' => (int) $id,
        ));
    }
}

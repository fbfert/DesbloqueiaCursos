<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class EmailConfiguracao
{
    public function current()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM configuracoes_email
             WHERE deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function save(array $data)
    {
        $current = $this->current();

        if ($current) {
            $stmt = Database::connection()->prepare(
                'UPDATE configuracoes_email
                 SET nome = :nome,
                     ativo = :ativo,
                     host = :host,
                     porta = :porta,
                     usuario = :usuario,
                     senha = :senha,
                     criptografia = :criptografia,
                     from_email = :from_email,
                     from_name = :from_name,
                     reply_to_email = :reply_to_email,
                     fila_ativa = :fila_ativa,
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $stmt->execute(array(
                'nome' => $data['nome'],
                'ativo' => !empty($data['ativo']) ? 1 : 0,
                'host' => $data['host'],
                'porta' => (int) $data['porta'],
                'usuario' => isset($data['usuario']) ? $data['usuario'] : null,
                'senha' => isset($data['senha']) ? $data['senha'] : null,
                'criptografia' => $data['criptografia'],
                'from_email' => $data['from_email'],
                'from_name' => $data['from_name'],
                'reply_to_email' => isset($data['reply_to_email']) ? $data['reply_to_email'] : null,
                'fila_ativa' => !empty($data['fila_ativa']) ? 1 : 0,
                'id' => (int) $current['id'],
            ));

            return (int) $current['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO configuracoes_email
             (nome, ativo, host, porta, usuario, senha, criptografia, from_email, from_name, reply_to_email, fila_ativa, created_at, updated_at, deleted_at)
             VALUES
             (:nome, :ativo, :host, :porta, :usuario, :senha, :criptografia, :from_email, :from_name, :reply_to_email, :fila_ativa, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'nome' => $data['nome'],
            'ativo' => !empty($data['ativo']) ? 1 : 0,
            'host' => $data['host'],
            'porta' => (int) $data['porta'],
            'usuario' => isset($data['usuario']) ? $data['usuario'] : null,
            'senha' => isset($data['senha']) ? $data['senha'] : null,
            'criptografia' => $data['criptografia'],
            'from_email' => $data['from_email'],
            'from_name' => $data['from_name'],
            'reply_to_email' => isset($data['reply_to_email']) ? $data['reply_to_email'] : null,
            'fila_ativa' => !empty($data['fila_ativa']) ? 1 : 0,
        ));

        return (int) Database::connection()->lastInsertId();
    }
}

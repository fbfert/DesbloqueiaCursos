<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class EmailOptout
{
    public function createToken(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO email_optouts
             (aluno_id, email, tipo, token_hash, optout_em, created_at, updated_at)
             VALUES
             (:aluno_id, :email, :tipo, :token_hash, :optout_em, NOW(), NOW())'
        );

        $stmt->execute(array(
            'aluno_id' => isset($data['aluno_id']) ? (int) $data['aluno_id'] : null,
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'tipo' => isset($data['tipo']) ? (string) $data['tipo'] : 'recuperacao_pedido',
            'token_hash' => isset($data['token_hash']) ? (string) $data['token_hash'] : '',
            'optout_em' => isset($data['optout_em']) ? $data['optout_em'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function findByTokenHash($tokenHash, $tipo = 'recuperacao_pedido')
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM email_optouts
             WHERE token_hash = :token_hash
               AND tipo = :tipo
             LIMIT 1'
        );
        $stmt->execute(array(
            'token_hash' => $tokenHash,
            'tipo' => $tipo,
        ));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findActiveByAlunoOrEmail($alunoId, $email, $tipo = 'recuperacao_pedido')
    {
        $email = strtolower(trim((string) $email));
        $alunoId = (int) $alunoId;

        $sql = 'SELECT *
                FROM email_optouts
                WHERE tipo = :tipo
                  AND optout_em IS NOT NULL
                  AND email = :email';
        $params = array(
            'tipo' => $tipo,
            'email' => $email,
        );

        if ($alunoId > 0) {
            $sql .= ' OR (tipo = :tipo AND optout_em IS NOT NULL AND aluno_id = :aluno_id)';
            $params['aluno_id'] = $alunoId;
        }

        $sql = 'SELECT *
                FROM email_optouts
                WHERE (' . ($alunoId > 0 ? '(tipo = :tipo AND optout_em IS NOT NULL AND (email = :email OR aluno_id = :aluno_id))' : '(tipo = :tipo AND optout_em IS NOT NULL AND email = :email)') . ')
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function confirmByTokenHash($tokenHash, $tipo = 'recuperacao_pedido')
    {
        $stmt = Database::connection()->prepare(
            'UPDATE email_optouts
             SET optout_em = COALESCE(optout_em, NOW()),
                 updated_at = NOW()
             WHERE token_hash = :token_hash
               AND tipo = :tipo'
        );

        $stmt->execute(array(
            'token_hash' => $tokenHash,
            'tipo' => $tipo,
        ));

        return $stmt->rowCount() > 0;
    }
}

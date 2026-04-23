<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Usuario
{
    public function findByLogin($login)
    {
        $login = trim((string) $login);
        $cpf = preg_replace('/\D+/', '', $login);

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM usuarios
             WHERE deleted_at IS NULL
               AND (email = :email OR cpf = :cpf)
             LIMIT 1'
        );

        $stmt->execute(array(
            'email' => strtolower($login),
            'cpf' => $cpf,
        ));

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    public function findByEmail($email)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM usuarios WHERE deleted_at IS NULL AND email = :email LIMIT 1'
        );
        $stmt->execute(array('email' => strtolower(trim((string) $email))));

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    public function findByCpf($cpf)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM usuarios WHERE deleted_at IS NULL AND cpf = :cpf LIMIT 1'
        );
        $stmt->execute(array('cpf' => preg_replace('/\D+/', '', (string) $cpf)));

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    public function findByRecoveryToken($token)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM usuarios
             WHERE deleted_at IS NULL
               AND token_recuperacao = :token
               AND token_recuperacao_expira_em >= NOW()
             LIMIT 1'
        );
        $stmt->execute(array('token' => $token));

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO usuarios
             (nome, email, cpf, senha_hash, telefone, status, tentativas_login, bloqueado_ate,
              token_recuperacao, token_recuperacao_expira_em, ultimo_login_em, created_at, updated_at, deleted_at)
             VALUES
             (:nome, :email, :cpf, :senha_hash, :telefone, :status, 0, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'nome' => $data['nome'],
            'email' => strtolower(trim($data['email'])),
            'cpf' => preg_replace('/\D+/', '', $data['cpf']),
            'senha_hash' => $data['senha_hash'],
            'telefone' => isset($data['telefone']) ? preg_replace('/\D+/', '', $data['telefone']) : null,
            'status' => 'ativo',
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function updatePassword($usuarioId, $senhaHash)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios
             SET senha_hash = :senha_hash,
                 token_recuperacao = NULL,
                 token_recuperacao_expira_em = NULL,
                 tentativas_login = 0,
                 bloqueado_ate = NULL,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'senha_hash' => $senhaHash,
            'id' => $usuarioId,
        ));
    }

    public function setRecoveryToken($usuarioId, $token, $validadeMinutos = 60)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios
             SET token_recuperacao = :token,
                 token_recuperacao_expira_em = DATE_ADD(NOW(), INTERVAL ' . (int) $validadeMinutos . ' MINUTE),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'token' => $token,
            'id' => $usuarioId,
        ));
    }

    public function resetLoginAttempts($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios
             SET tentativas_login = 0,
                 bloqueado_ate = NULL,
                 ultimo_login_em = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array('id' => $usuarioId));
    }

    public function incrementLoginAttempts($usuarioId, $currentAttempts, $lockMinutes, $maxAttempts = 5)
    {
        $nextAttempts = (int) $currentAttempts + 1;
        $lockSql = $nextAttempts >= (int) $maxAttempts ? ', bloqueado_ate = DATE_ADD(NOW(), INTERVAL ' . (int) $lockMinutes . ' MINUTE)' : '';

        $stmt = Database::connection()->prepare(
            'UPDATE usuarios
             SET tentativas_login = :tentativas' . $lockSql . ',
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'tentativas' => $nextAttempts,
            'id' => $usuarioId,
        ));

        return $nextAttempts;
    }
}

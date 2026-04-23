<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConfiguracaoSeguranca
{
    public function current()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM configuracoes_seguranca
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

        $payload = array(
            'politica_login' => isset($data['politica_login']) && trim((string) $data['politica_login']) !== '' ? trim((string) $data['politica_login']) : 'email_cpf',
            'validade_reset_senha_minutos' => isset($data['validade_reset_senha_minutos']) ? (int) $data['validade_reset_senha_minutos'] : 60,
            'max_tentativas_login' => isset($data['max_tentativas_login']) ? (int) $data['max_tentativas_login'] : 5,
            'tempo_bloqueio_login_minutos' => isset($data['tempo_bloqueio_login_minutos']) ? (int) $data['tempo_bloqueio_login_minutos'] : 15,
        );

        if ($current) {
            $stmt = Database::connection()->prepare(
                'UPDATE configuracoes_seguranca
                 SET politica_login = :politica_login,
                     validade_reset_senha_minutos = :validade_reset_senha_minutos,
                     max_tentativas_login = :max_tentativas_login,
                     tempo_bloqueio_login_minutos = :tempo_bloqueio_login_minutos,
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $stmt->execute(array_merge($payload, array('id' => (int) $current['id'])));
            return (int) $current['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO configuracoes_seguranca
             (politica_login, validade_reset_senha_minutos, max_tentativas_login, tempo_bloqueio_login_minutos, created_at, updated_at, deleted_at)
             VALUES
             (:politica_login, :validade_reset_senha_minutos, :max_tentativas_login, :tempo_bloqueio_login_minutos, NOW(), NOW(), NULL)'
        );

        $stmt->execute($payload);

        return (int) Database::connection()->lastInsertId();
    }
}

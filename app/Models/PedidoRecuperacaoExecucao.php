<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class PedidoRecuperacaoExecucao
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pedido_recuperacao_execucoes
             (status, modo, dry_run, limite_processamento, total_analisados, total_processados, total_enviados,
              total_ignorados, total_bloqueados, total_erros, started_at, finished_at, error_message, created_at, updated_at, deleted_at)
             VALUES
             (:status, :modo, :dry_run, :limite_processamento, :total_analisados, :total_processados, :total_enviados,
              :total_ignorados, :total_bloqueados, :total_erros, :started_at, :finished_at, :error_message, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'status' => isset($data['status']) ? (string) $data['status'] : 'running',
            'modo' => isset($data['modo']) ? (string) $data['modo'] : 'cron',
            'dry_run' => !empty($data['dry_run']) ? 1 : 0,
            'limite_processamento' => isset($data['limite_processamento']) ? (int) $data['limite_processamento'] : 50,
            'total_analisados' => isset($data['total_analisados']) ? (int) $data['total_analisados'] : 0,
            'total_processados' => isset($data['total_processados']) ? (int) $data['total_processados'] : 0,
            'total_enviados' => isset($data['total_enviados']) ? (int) $data['total_enviados'] : 0,
            'total_ignorados' => isset($data['total_ignorados']) ? (int) $data['total_ignorados'] : 0,
            'total_bloqueados' => isset($data['total_bloqueados']) ? (int) $data['total_bloqueados'] : 0,
            'total_erros' => isset($data['total_erros']) ? (int) $data['total_erros'] : 0,
            'started_at' => isset($data['started_at']) ? $data['started_at'] : date('Y-m-d H:i:s'),
            'finished_at' => isset($data['finished_at']) ? $data['finished_at'] : null,
            'error_message' => isset($data['error_message']) ? $data['error_message'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update($id, array $data)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pedido_recuperacao_execucoes
             SET status = :status,
                 modo = :modo,
                 dry_run = :dry_run,
                 limite_processamento = :limite_processamento,
                 total_analisados = :total_analisados,
                 total_processados = :total_processados,
                 total_enviados = :total_enviados,
                 total_ignorados = :total_ignorados,
                 total_bloqueados = :total_bloqueados,
                 total_erros = :total_erros,
                 finished_at = :finished_at,
                 error_message = :error_message,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'status' => isset($data['status']) ? (string) $data['status'] : 'running',
            'modo' => isset($data['modo']) ? (string) $data['modo'] : 'cron',
            'dry_run' => !empty($data['dry_run']) ? 1 : 0,
            'limite_processamento' => isset($data['limite_processamento']) ? (int) $data['limite_processamento'] : 50,
            'total_analisados' => isset($data['total_analisados']) ? (int) $data['total_analisados'] : 0,
            'total_processados' => isset($data['total_processados']) ? (int) $data['total_processados'] : 0,
            'total_enviados' => isset($data['total_enviados']) ? (int) $data['total_enviados'] : 0,
            'total_ignorados' => isset($data['total_ignorados']) ? (int) $data['total_ignorados'] : 0,
            'total_bloqueados' => isset($data['total_bloqueados']) ? (int) $data['total_bloqueados'] : 0,
            'total_erros' => isset($data['total_erros']) ? (int) $data['total_erros'] : 0,
            'finished_at' => isset($data['finished_at']) ? $data['finished_at'] : null,
            'error_message' => isset($data['error_message']) ? $data['error_message'] : null,
            'id' => (int) $id,
        ));
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM pedido_recuperacao_execucoes
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function latest()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM pedido_recuperacao_execucoes
             WHERE deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class PedidoRecuperacaoLog
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pedido_recuperacao_logs
             (pedido_id, aluno_id, curso_id, admin_user_id, canal, modelo_chave, tipo_envio, etapa, status, email_destino,
              valor_pendente, cupom_codigo, motivo_bloqueio, erro, email_envio_id, execucao_id, enviado_em, created_at, updated_at)
             VALUES
             (:pedido_id, :aluno_id, :curso_id, :admin_user_id, :canal, :modelo_chave, :tipo_envio, :etapa, :status, :email_destino,
              :valor_pendente, :cupom_codigo, :motivo_bloqueio, :erro, :email_envio_id, :execucao_id, :enviado_em, NOW(), NOW())'
        );

        $stmt->execute(array(
            'pedido_id' => isset($data['pedido_id']) ? (int) $data['pedido_id'] : null,
            'aluno_id' => isset($data['aluno_id']) ? (int) $data['aluno_id'] : null,
            'curso_id' => isset($data['curso_id']) ? (int) $data['curso_id'] : null,
            'admin_user_id' => isset($data['admin_user_id']) ? (int) $data['admin_user_id'] : null,
            'canal' => isset($data['canal']) ? $data['canal'] : 'email',
            'modelo_chave' => isset($data['modelo_chave']) ? $data['modelo_chave'] : '',
            'tipo_envio' => isset($data['tipo_envio']) ? $data['tipo_envio'] : 'manual',
            'etapa' => isset($data['etapa']) ? $data['etapa'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'bloqueado',
            'email_destino' => isset($data['email_destino']) ? $data['email_destino'] : null,
            'valor_pendente' => isset($data['valor_pendente']) ? $data['valor_pendente'] : null,
            'cupom_codigo' => isset($data['cupom_codigo']) && $data['cupom_codigo'] !== '' ? $data['cupom_codigo'] : null,
            'motivo_bloqueio' => isset($data['motivo_bloqueio']) ? $data['motivo_bloqueio'] : null,
            'erro' => isset($data['erro']) ? $data['erro'] : null,
            'email_envio_id' => isset($data['email_envio_id']) ? (int) $data['email_envio_id'] : null,
            'execucao_id' => isset($data['execucao_id']) ? (int) $data['execucao_id'] : null,
            'enviado_em' => isset($data['enviado_em']) ? $data['enviado_em'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function latestByPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM pedido_recuperacao_logs
             WHERE pedido_id = :pedido_id
             ORDER BY COALESCE(enviado_em, created_at) DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute(array('pedido_id' => (int) $pedidoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function countsByPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                COUNT(*) AS total_envios,
                SUM(CASE WHEN tipo_envio = "manual" AND status = "enviado" THEN 1 ELSE 0 END) AS total_manuais,
                SUM(CASE WHEN tipo_envio = "automatico" AND status = "enviado" THEN 1 ELSE 0 END) AS total_automaticos
             FROM pedido_recuperacao_logs
             WHERE pedido_id = :pedido_id
               AND status = "enviado"'
        );

        $stmt->execute(array('pedido_id' => (int) $pedidoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: array('total_envios' => 0, 'total_manuais' => 0, 'total_automaticos' => 0);
    }

    public function latestSentByEmail($email, $alunoId = null)
    {
        $where = array('status = "enviado"', 'canal = "email"');
        $params = array('email' => strtolower(trim((string) $email)));
        $where[] = 'email_destino = :email';

        if ($alunoId !== null && (int) $alunoId > 0) {
            $where[] = '(aluno_id = :aluno_id OR aluno_id IS NULL)';
            $params['aluno_id'] = (int) $alunoId;
        }

        $sql = 'SELECT *
                FROM pedido_recuperacao_logs
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY COALESCE(enviado_em, created_at) DESC, id DESC
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function latestAutomaticByPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM pedido_recuperacao_logs
             WHERE pedido_id = :pedido_id
               AND tipo_envio = "automatico"
               AND status = "enviado"
             ORDER BY COALESCE(enviado_em, created_at) DESC, id DESC
             LIMIT 1'
        );

        $stmt->execute(array('pedido_id' => (int) $pedidoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function latestByAlunoEmail($alunoId, $email)
    {
        $where = array('status = "enviado"', 'canal = "email"');
        $params = array('email' => strtolower(trim((string) $email)));
        $where[] = 'email_destino = :email';

        if ($alunoId !== null && (int) $alunoId > 0) {
            $where[] = '(aluno_id = :aluno_id OR aluno_id IS NULL)';
            $params['aluno_id'] = (int) $alunoId;
        }

        $sql = 'SELECT *
                FROM pedido_recuperacao_logs
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY COALESCE(enviado_em, created_at) DESC, id DESC
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

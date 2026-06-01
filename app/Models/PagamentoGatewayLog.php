<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class PagamentoGatewayLog
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pagamentos_gateway_logs
             (gateway, pedido_id, event_id, event_type, payload, processed, error_message, created_at)
             VALUES
             (:gateway, :pedido_id, :event_id, :event_type, :payload, :processed, :error_message, NOW())'
        );

        $stmt->execute(array(
            'gateway' => isset($data['gateway']) ? $data['gateway'] : null,
            'pedido_id' => isset($data['pedido_id']) ? $data['pedido_id'] : null,
            'event_id' => isset($data['event_id']) ? $data['event_id'] : null,
            'event_type' => isset($data['event_type']) ? $data['event_type'] : null,
            'payload' => isset($data['payload']) ? $data['payload'] : null,
            'processed' => !empty($data['processed']) ? 1 : 0,
            'error_message' => isset($data['error_message']) ? $data['error_message'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function markProcessed($id, $processed = true, $errorMessage = null)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pagamentos_gateway_logs
             SET processed = :processed,
                 error_message = :error_message
             WHERE id = :id'
        );

        $stmt->execute(array(
            'processed' => $processed ? 1 : 0,
            'error_message' => $errorMessage,
            'id' => (int) $id,
        ));
    }

    public function listByPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM pagamentos_gateway_logs
             WHERE pedido_id = :pedido_id
             ORDER BY id DESC'
        );

        $stmt->execute(array('pedido_id' => (int) $pedidoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

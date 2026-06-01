<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class PagamentoGatewayTransacao
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pagamentos_gateway_transacoes
             (pedido_id, gateway, external_id, provider_id, event_id, event_type, status, amount, paid_amount,
              payment_method, receipt_url, raw_payload, created_at, updated_at)
             VALUES
             (:pedido_id, :gateway, :external_id, :provider_id, :event_id, :event_type, :status, :amount, :paid_amount,
              :payment_method, :receipt_url, :raw_payload, NOW(), NOW())'
        );

        $stmt->execute(array(
            'pedido_id' => isset($data['pedido_id']) ? $data['pedido_id'] : null,
            'gateway' => isset($data['gateway']) ? $data['gateway'] : null,
            'external_id' => isset($data['external_id']) ? $data['external_id'] : null,
            'provider_id' => isset($data['provider_id']) ? $data['provider_id'] : null,
            'event_id' => isset($data['event_id']) ? $data['event_id'] : null,
            'event_type' => isset($data['event_type']) ? $data['event_type'] : null,
            'status' => isset($data['status']) ? $data['status'] : null,
            'amount' => isset($data['amount']) ? $data['amount'] : null,
            'paid_amount' => isset($data['paid_amount']) ? $data['paid_amount'] : null,
            'payment_method' => isset($data['payment_method']) ? $data['payment_method'] : null,
            'receipt_url' => isset($data['receipt_url']) ? $data['receipt_url'] : null,
            'raw_payload' => isset($data['raw_payload']) ? $data['raw_payload'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function existsByEventId($gateway, $eventId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT id
             FROM pagamentos_gateway_transacoes
             WHERE gateway = :gateway
               AND event_id = :event_id
             LIMIT 1'
        );

        $stmt->execute(array(
            'gateway' => $gateway,
            'event_id' => $eventId,
        ));

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listByPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM pagamentos_gateway_transacoes
             WHERE pedido_id = :pedido_id
             ORDER BY id DESC'
        );

        $stmt->execute(array('pedido_id' => (int) $pedidoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

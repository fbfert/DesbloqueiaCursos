<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class PedidoCupom
{
    public function findByPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT pc.*, c.nome AS cupom_nome, c.tipo AS cupom_tipo, c.desconto_tipo, c.valor_desconto AS cupom_valor_desconto, c.status AS cupom_status
             FROM pedidos_cupons pc
             LEFT JOIN cupons c ON c.id = pc.cupom_id
             WHERE pc.pedido_id = :pedido_id
             LIMIT 1'
        );

        $stmt->execute(array('pedido_id' => $pedidoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function upsert(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pedidos_cupons
             (pedido_id, cupom_id, cupom_codigo, valor_desconto, status, observacao, created_at)
             VALUES
             (:pedido_id, :cupom_id, :cupom_codigo, :valor_desconto, :status, :observacao, NOW())
             ON DUPLICATE KEY UPDATE
                 cupom_id = VALUES(cupom_id),
                 cupom_codigo = VALUES(cupom_codigo),
                 valor_desconto = VALUES(valor_desconto),
                 status = VALUES(status),
                 observacao = VALUES(observacao)'
        );

        $stmt->execute(array(
            'pedido_id' => $data['pedido_id'],
            'cupom_id' => isset($data['cupom_id']) ? $data['cupom_id'] : null,
            'cupom_codigo' => $data['cupom_codigo'],
            'valor_desconto' => $data['valor_desconto'],
            'status' => isset($data['status']) ? $data['status'] : 'aplicado',
            'observacao' => isset($data['observacao']) ? $data['observacao'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }
}

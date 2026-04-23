<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ParticipantePedido
{
    public function forPedidoItem($pedidoItemId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM participantes_pedido
             WHERE pedido_item_id = :pedido_item_id
               AND deleted_at IS NULL
             ORDER BY ordem ASC, id ASC'
        );

        $stmt->execute(array('pedido_item_id' => $pedidoItemId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function forPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM participantes_pedido
             WHERE pedido_id = :pedido_id
               AND deleted_at IS NULL
             ORDER BY ordem ASC, id ASC'
        );

        $stmt->execute(array('pedido_id' => $pedidoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO participantes_pedido
             (pedido_id, pedido_item_id, usuario_id, nome, cpf, email, telefone, ordem, status, created_at, updated_at, deleted_at)
             VALUES
             (:pedido_id, :pedido_item_id, :usuario_id, :nome, :cpf, :email, :telefone, :ordem, :status, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'pedido_id' => $data['pedido_id'],
            'pedido_item_id' => isset($data['pedido_item_id']) ? $data['pedido_item_id'] : null,
            'usuario_id' => isset($data['usuario_id']) ? $data['usuario_id'] : null,
            'nome' => $data['nome'],
            'cpf' => isset($data['cpf']) ? $data['cpf'] : null,
            'email' => isset($data['email']) ? $data['email'] : null,
            'telefone' => isset($data['telefone']) ? $data['telefone'] : null,
            'ordem' => isset($data['ordem']) ? $data['ordem'] : 0,
            'status' => isset($data['status']) ? $data['status'] : 'ativo',
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function softDelete($participantePedidoId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE participantes_pedido
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array('id' => $participantePedidoId));
    }
}

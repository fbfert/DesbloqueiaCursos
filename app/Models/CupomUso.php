<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CupomUso
{
    public function findByPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM cupons_usos
             WHERE pedido_id = :pedido_id
             LIMIT 1'
        );

        $stmt->execute(array('pedido_id' => $pedidoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function forCupom($cupomId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*,
                    p.codigo AS pedido_codigo,
                    p.pagador_nome,
                    p.pagador_email,
                    p.pagador_telefone,
                    p.status AS pedido_status,
                    p.total AS pedido_total,
                    p.created_at AS pedido_created_at
             FROM cupons_usos u
             INNER JOIN pedidos p ON p.id = u.pedido_id
             WHERE u.cupom_id = :cupom_id
             ORDER BY u.id DESC'
        );

        $stmt->execute(array('cupom_id' => $cupomId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByCupom($cupomId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total_usos, COALESCE(SUM(valor_desconto), 0) AS total_descontos
             FROM cupons_usos
             WHERE cupom_id = :cupom_id'
        );

        $stmt->execute(array('cupom_id' => $cupomId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: array('total_usos' => 0, 'total_descontos' => 0);
    }

    public function countByCupomAndUsuario($cupomId, $usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total_usos
             FROM cupons_usos
             WHERE cupom_id = :cupom_id
               AND usuario_id = :usuario_id'
        );

        $stmt->execute(array(
            'cupom_id' => $cupomId,
            'usuario_id' => $usuarioId,
        ));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['total_usos'] : 0;
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cupons_usos
             (cupom_id, pedido_id, pedido_cupom_id, usuario_id, cupom_codigo, valor_desconto, created_at)
             VALUES
             (:cupom_id, :pedido_id, :pedido_cupom_id, :usuario_id, :cupom_codigo, :valor_desconto, NOW())
             ON DUPLICATE KEY UPDATE
                 cupom_id = VALUES(cupom_id),
                 pedido_cupom_id = VALUES(pedido_cupom_id),
                 usuario_id = VALUES(usuario_id),
                 cupom_codigo = VALUES(cupom_codigo),
                 valor_desconto = VALUES(valor_desconto)'
        );

        $stmt->execute(array(
            'cupom_id' => $data['cupom_id'],
            'pedido_id' => $data['pedido_id'],
            'pedido_cupom_id' => isset($data['pedido_cupom_id']) ? $data['pedido_cupom_id'] : null,
            'usuario_id' => isset($data['usuario_id']) ? $data['usuario_id'] : null,
            'cupom_codigo' => $data['cupom_codigo'],
            'valor_desconto' => $data['valor_desconto'],
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function deleteByPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM cupons_usos
             WHERE pedido_id = :pedido_id'
        );

        $stmt->execute(array('pedido_id' => (int) $pedidoId));
    }
}

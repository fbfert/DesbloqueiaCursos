<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class PedidoItem
{
    public function forPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT pi.*, ce.nome AS curso_nome, ce.slug AS curso_slug, ce.tipo AS curso_tipo, ce.em_promocao AS curso_em_promocao,
                    t.nome AS turma_nome, t.codigo AS turma_codigo
             FROM pedido_itens pi
             INNER JOIN cursos_eventos ce ON ce.id = pi.curso_evento_id
             LEFT JOIN turmas t ON t.id = pi.turma_id
             WHERE pi.pedido_id = :pedido_id
               AND pi.deleted_at IS NULL
             ORDER BY pi.id ASC'
        );

        $stmt->execute(array('pedido_id' => $pedidoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pedido_itens
             (pedido_id, curso_evento_id, turma_id, quantidade, valor_unitario, valor_total, status, created_at, updated_at, deleted_at)
             VALUES
             (:pedido_id, :curso_evento_id, :turma_id, :quantidade, :valor_unitario, :valor_total, :status, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'pedido_id' => $data['pedido_id'],
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'quantidade' => isset($data['quantidade']) ? $data['quantidade'] : 1,
            'valor_unitario' => isset($data['valor_unitario']) ? $data['valor_unitario'] : 0,
            'valor_total' => isset($data['valor_total']) ? $data['valor_total'] : 0,
            'status' => isset($data['status']) ? $data['status'] : 'ativo',
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function softDelete($pedidoItemId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pedido_itens
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array('id' => $pedidoItemId));
    }
}

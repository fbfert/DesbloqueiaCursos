<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Pedido
{
    public function findByCodigo($codigo)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM pedidos
             WHERE codigo = :codigo
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('codigo' => $codigo));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function forUsuario($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*,
                    COALESCE(pp.total_participantes, 0) AS total_participantes,
                    COALESCE(pi.total_itens, 0) AS total_itens
             FROM pedidos p
             LEFT JOIN (
                SELECT pedido_id, COUNT(*) AS total_participantes
                FROM participantes_pedido
                WHERE deleted_at IS NULL
                GROUP BY pedido_id
             ) pp ON pp.pedido_id = p.id
             LEFT JOIN (
                SELECT pedido_id, COUNT(*) AS total_itens
                FROM pedido_itens
                WHERE deleted_at IS NULL
                GROUP BY pedido_id
             ) pi ON pi.pedido_id = p.id
             WHERE p.deleted_at IS NULL
               AND (p.comprador_usuario_id = :usuario_id OR p.pagador_usuario_id = :usuario_id)
             ORDER BY p.id DESC'
        );

        $stmt->execute(array('usuario_id' => $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM pedidos
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function allForBackoffice()
    {
        $stmt = Database::connection()->query(
            'SELECT p.*
             FROM pedidos p
             WHERE p.deleted_at IS NULL
             ORDER BY p.id DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function historyForPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM status_pedidos_historico
             WHERE pedido_id = :pedido_id
             ORDER BY id DESC'
        );

        $stmt->execute(array('pedido_id' => $pedidoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pedidos
             (codigo, comprador_usuario_id, pagador_usuario_id, pagador_nome, pagador_cpf, pagador_email, pagador_telefone,
              tipo_pedido, status, subtotal, desconto_total, acrescimo_total, total,
              observacoes_internas, observacoes_publicas, canal_origem, created_at, updated_at, deleted_at)
             VALUES
             (:codigo, :comprador_usuario_id, :pagador_usuario_id, :pagador_nome, :pagador_cpf, :pagador_email, :pagador_telefone,
              :tipo_pedido, :status, :subtotal, :desconto_total, :acrescimo_total, :total,
              :observacoes_internas, :observacoes_publicas, :canal_origem, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'codigo' => $data['codigo'],
            'comprador_usuario_id' => isset($data['comprador_usuario_id']) ? $data['comprador_usuario_id'] : null,
            'pagador_usuario_id' => isset($data['pagador_usuario_id']) ? $data['pagador_usuario_id'] : null,
            'pagador_nome' => isset($data['pagador_nome']) ? $data['pagador_nome'] : null,
            'pagador_cpf' => isset($data['pagador_cpf']) ? $data['pagador_cpf'] : null,
            'pagador_email' => isset($data['pagador_email']) ? $data['pagador_email'] : null,
            'pagador_telefone' => isset($data['pagador_telefone']) ? $data['pagador_telefone'] : null,
            'tipo_pedido' => isset($data['tipo_pedido']) ? $data['tipo_pedido'] : 'propria',
            'status' => isset($data['status']) ? $data['status'] : 'rascunho',
            'subtotal' => isset($data['subtotal']) ? $data['subtotal'] : 0,
            'desconto_total' => isset($data['desconto_total']) ? $data['desconto_total'] : 0,
            'acrescimo_total' => isset($data['acrescimo_total']) ? $data['acrescimo_total'] : 0,
            'total' => isset($data['total']) ? $data['total'] : 0,
            'observacoes_internas' => isset($data['observacoes_internas']) ? $data['observacoes_internas'] : null,
            'observacoes_publicas' => isset($data['observacoes_publicas']) ? $data['observacoes_publicas'] : null,
            'canal_origem' => isset($data['canal_origem']) ? $data['canal_origem'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function updateStatus($pedidoId, $status)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pedidos
             SET status = :status,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'status' => $status,
            'id' => $pedidoId,
        ));
    }

    public function updateCupom($pedidoId, $cupomCodigo, $descontoTotal, $total)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pedidos
             SET cupom_codigo = :cupom_codigo,
                 desconto_total = :desconto_total,
                 total = :total,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'cupom_codigo' => $cupomCodigo,
            'desconto_total' => $descontoTotal,
            'total' => $total,
            'id' => $pedidoId,
        ));
    }

    public function markApproved($pedidoId, $usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pedidos
             SET status = "aprovado",
                 aprovado_por_usuario_id = :aprovado_por_usuario_id,
                 aprovado_em = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'aprovado_por_usuario_id' => $usuarioId,
            'id' => $pedidoId,
        ));
    }

    public function softDelete($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pedidos
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array('id' => $pedidoId));
    }

    public function markAwaitingPayment($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pedidos
             SET status = "aguardando_pagamento",
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array('id' => $pedidoId));
    }

    public function addStatusHistory($pedidoId, $statusAnterior, $statusNovo, $observacao = null, $alteradoPorUsuarioId = null)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO status_pedidos_historico
             (pedido_id, status_anterior, status_novo, observacao, alterado_por_usuario_id, created_at)
             VALUES
             (:pedido_id, :status_anterior, :status_novo, :observacao, :alterado_por_usuario_id, NOW())'
        );

        $stmt->execute(array(
            'pedido_id' => $pedidoId,
            'status_anterior' => $statusAnterior,
            'status_novo' => $statusNovo,
            'observacao' => $observacao,
            'alterado_por_usuario_id' => $alteradoPorUsuarioId,
        ));
    }
}

<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ComprovantePix
{
    public function findByPedido($pedidoId)
    {
        return $this->findCurrentByPedido($pedidoId);
    }

    public function findCurrentByPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM comprovantes_pix
             WHERE pedido_id = :pedido_id
               AND is_atual = 1
               AND deleted_at IS NULL
             ORDER BY versao DESC, id DESC
             LIMIT 1'
        );

        $stmt->execute(array('pedido_id' => $pedidoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM comprovantes_pix
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function versionsForPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM comprovantes_pix
             WHERE pedido_id = :pedido_id
               AND deleted_at IS NULL
             ORDER BY versao DESC, id DESC'
        );

        $stmt->execute(array('pedido_id' => $pedidoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function allForBackoffice()
    {
        $stmt = Database::connection()->query(
            'SELECT cp.*, p.codigo AS pedido_codigo, p.pagador_nome, p.pagador_email, p.pagador_telefone, p.total AS pedido_total,
                    p.status AS pedido_status, p.created_at AS pedido_created_at
             FROM comprovantes_pix cp
             INNER JOIN pedidos p ON p.id = cp.pedido_id
             WHERE cp.deleted_at IS NULL
               AND cp.is_atual = 1
             ORDER BY cp.id DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function pendentesForBackoffice()
    {
        $statusPendentes = array('pendente', 'em_analise');
        $placeholders = array();
        $params = array();

        foreach ($statusPendentes as $indice => $status) {
            $chave = 'status_' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $status;
        }

        $sql = 'SELECT cp.*,
                       p.codigo AS pedido_codigo,
                       p.pagador_nome,
                       p.pagador_email,
                       p.pagador_telefone,
                       p.total AS pedido_total,
                       p.status AS pedido_status,
                       p.created_at AS pedido_created_at,
                       GROUP_CONCAT(DISTINCT ce.nome ORDER BY ce.nome SEPARATOR ", ") AS cursos_nome,
                       GROUP_CONCAT(DISTINCT t.nome ORDER BY t.nome SEPARATOR ", ") AS turmas_nome,
                       COUNT(DISTINCT pi.id) AS total_itens
                FROM comprovantes_pix cp
                INNER JOIN pedidos p ON p.id = cp.pedido_id
                LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id AND pi.deleted_at IS NULL
                LEFT JOIN cursos_eventos ce ON ce.id = pi.curso_evento_id AND ce.deleted_at IS NULL
                LEFT JOIN turmas t ON t.id = pi.turma_id AND t.deleted_at IS NULL
                WHERE cp.deleted_at IS NULL
                  AND cp.is_atual = 1
                  AND p.status <> "aguardando_reenvio"
                  AND cp.status IN (' . implode(', ', $placeholders) . ')
                GROUP BY cp.id
                ORDER BY cp.enviado_em DESC, cp.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $versao = isset($data['versao']) ? (int) $data['versao'] : 1;

        $stmt = Database::connection()->prepare(
            'INSERT INTO comprovantes_pix
             (pedido_id, usuario_id, arquivo_caminho, arquivo_nome_original, arquivo_mime_type, arquivo_tamanho_bytes, valor_informado,
              enviado_em, status, analise_observacao, analisado_por_usuario_id, analisado_em, versao, is_atual, motivo_reenvio, created_at, updated_at, deleted_at)
             VALUES
             (:pedido_id, :usuario_id, :arquivo_caminho, :arquivo_nome_original, :arquivo_mime_type, :arquivo_tamanho_bytes, :valor_informado,
              :enviado_em, :status, :analise_observacao, :analisado_por_usuario_id, :analisado_em, :versao, 1, :motivo_reenvio, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'pedido_id' => $data['pedido_id'],
            'usuario_id' => isset($data['usuario_id']) ? $data['usuario_id'] : null,
            'arquivo_caminho' => $data['arquivo_caminho'],
            'arquivo_nome_original' => isset($data['arquivo_nome_original']) ? $data['arquivo_nome_original'] : null,
            'arquivo_mime_type' => isset($data['arquivo_mime_type']) ? $data['arquivo_mime_type'] : null,
            'arquivo_tamanho_bytes' => isset($data['arquivo_tamanho_bytes']) ? $data['arquivo_tamanho_bytes'] : null,
            'valor_informado' => isset($data['valor_informado']) ? $data['valor_informado'] : null,
            'enviado_em' => isset($data['enviado_em']) ? $data['enviado_em'] : date('Y-m-d H:i:s'),
            'status' => isset($data['status']) ? $data['status'] : 'pendente',
            'analise_observacao' => isset($data['analise_observacao']) ? $data['analise_observacao'] : null,
            'analisado_por_usuario_id' => isset($data['analisado_por_usuario_id']) ? $data['analisado_por_usuario_id'] : null,
            'analisado_em' => isset($data['analisado_em']) ? $data['analisado_em'] : null,
            'versao' => $versao,
            'motivo_reenvio' => isset($data['motivo_reenvio']) ? $data['motivo_reenvio'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function nextVersionForPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(MAX(versao), 0) + 1 AS proxima_versao
             FROM comprovantes_pix
             WHERE pedido_id = :pedido_id'
        );
        $stmt->execute(array('pedido_id' => $pedidoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['proxima_versao'] : 1;
    }

    public function setCurrentFlag($pedidoId, $isAtual)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE comprovantes_pix
             SET is_atual = :is_atual,
                 updated_at = NOW()
             WHERE pedido_id = :pedido_id'
        );

        $stmt->execute(array(
            'pedido_id' => $pedidoId,
            'is_atual' => $isAtual ? 1 : 0,
        ));
    }

    public function createVersion($pedidoId, array $data)
    {
        $this->setCurrentFlag($pedidoId, false);

        $data['pedido_id'] = $pedidoId;
        $data['versao'] = $this->nextVersionForPedido($pedidoId);

        return $this->create($data);
    }

    public function updateStatus($comprovanteId, $status, $observacao = null, $analisadoPorUsuarioId = null, $analisadoEm = null)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE comprovantes_pix
             SET status = :status,
                 analise_observacao = :analise_observacao,
                 analisado_por_usuario_id = :analisado_por_usuario_id,
                 analisado_em = :analisado_em,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'status' => $status,
            'analise_observacao' => $observacao,
            'analisado_por_usuario_id' => $analisadoPorUsuarioId,
            'analisado_em' => $analisadoEm,
            'id' => $comprovanteId,
        ));
    }

    public function softDelete($comprovanteId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE comprovantes_pix
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array('id' => $comprovanteId));
    }
}

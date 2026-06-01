<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Pedido
{
    public function findLatestByUsuarioForPrefill($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT pagador_nome, pagador_cpf, pagador_email, pagador_telefone, pagador_cidade, pagador_estado
             FROM pedidos
             WHERE deleted_at IS NULL
               AND (comprador_usuario_id = :usuario_id OR pagador_usuario_id = :usuario_id)
             ORDER BY id DESC
             LIMIT 1'
        );

        $stmt->execute(array('usuario_id' => (int) $usuarioId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

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

    public function pendentesParaMeusCursos($usuarioId, $limit = 5)
    {
        $limit = (int) $limit;
        if ($limit <= 0) {
            $limit = 5;
        }
        if ($limit > 20) {
            $limit = 20;
        }

        $statusPendentes = array(
            'rascunho',
            'aguardando_pagamento',
            'aguardando_pix',
            'checkout_criado',
            'em_aberto',
            'pending',
            'pendencia',
            'aguardando_reenvio',
            'comprovante_enviado',
            'em_analise',
        );

        $placeholders = array();
        $params = array(
            'usuario_id' => (int) $usuarioId,
        );

        foreach ($statusPendentes as $idx => $status) {
            $key = 'status_' . $idx;
            $placeholders[] = ':' . $key;
            $params[$key] = $status;
        }

        $sql = 'SELECT p.*,
                       pi.curso_evento_id,
                       pi.turma_id,
                       ce.nome AS curso_nome,
                       t.nome AS turma_nome
                FROM pedidos p
                LEFT JOIN pedido_itens pi
                    ON pi.pedido_id = p.id
                   AND pi.deleted_at IS NULL
                LEFT JOIN cursos_eventos ce
                    ON ce.id = pi.curso_evento_id
                   AND ce.deleted_at IS NULL
                LEFT JOIN turmas t
                    ON t.id = pi.turma_id
                   AND t.deleted_at IS NULL
                WHERE p.deleted_at IS NULL
                  AND (p.comprador_usuario_id = :usuario_id OR p.pagador_usuario_id = :usuario_id)
                  AND p.status IN (' . implode(', ', $placeholders) . ')
                ORDER BY p.id DESC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPedidoPendenteDoAlunoCurso($usuarioId, $cursoId, $turmaId = null)
    {
        $statusPendentes = array(
            'rascunho',
            'aguardando_pagamento',
            'aguardando_pix',
            'checkout_criado',
            'em_aberto',
            'pending',
            'pendencia',
            'aguardando_reenvio',
            'comprovante_enviado',
            'em_analise',
        );

        $placeholders = array();
        $params = array(
            'usuario_id' => (int) $usuarioId,
            'curso_evento_id' => (int) $cursoId,
        );

        foreach ($statusPendentes as $idx => $status) {
            $key = 'status_' . $idx;
            $placeholders[] = ':' . $key;
            $params[$key] = $status;
        }

        $sql = 'SELECT p.*,
                       pi.curso_evento_id,
                       pi.turma_id,
                       ce.nome AS curso_nome,
                       t.nome AS turma_nome
                FROM pedidos p
                INNER JOIN pedido_itens pi
                    ON pi.pedido_id = p.id
                   AND pi.deleted_at IS NULL
                INNER JOIN cursos_eventos ce
                    ON ce.id = pi.curso_evento_id
                   AND ce.deleted_at IS NULL
                LEFT JOIN turmas t
                    ON t.id = pi.turma_id
                   AND t.deleted_at IS NULL
                WHERE p.deleted_at IS NULL
                  AND (p.comprador_usuario_id = :usuario_id OR p.pagador_usuario_id = :usuario_id)
                  AND pi.curso_evento_id = :curso_evento_id
                  AND p.status IN (' . implode(', ', $placeholders) . ')';

        if ($turmaId !== null && (int) $turmaId > 0) {
            $sql .= ' AND pi.turma_id = :turma_id';
            $params['turma_id'] = (int) $turmaId;
        }

        $sql .= ' ORDER BY p.id DESC
                  LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findUltimoDoAlunoCurso($usuarioId, $cursoId, $turmaId = null)
    {
        $params = array(
            'usuario_id' => (int) $usuarioId,
            'curso_evento_id' => (int) $cursoId,
        );

        $sql = 'SELECT p.*,
                       pi.curso_evento_id,
                       pi.turma_id,
                       ce.nome AS curso_nome,
                       t.nome AS turma_nome
                FROM pedidos p
                INNER JOIN pedido_itens pi
                    ON pi.pedido_id = p.id
                   AND pi.deleted_at IS NULL
                INNER JOIN cursos_eventos ce
                    ON ce.id = pi.curso_evento_id
                   AND ce.deleted_at IS NULL
                LEFT JOIN turmas t
                    ON t.id = pi.turma_id
                   AND t.deleted_at IS NULL
                WHERE p.deleted_at IS NULL
                  AND (p.comprador_usuario_id = :usuario_id OR p.pagador_usuario_id = :usuario_id)
                  AND pi.curso_evento_id = :curso_evento_id';

        if ($turmaId !== null && (int) $turmaId > 0) {
            $sql .= ' AND pi.turma_id = :turma_id';
            $params['turma_id'] = (int) $turmaId;
        }

        $sql .= ' ORDER BY p.id DESC
                  LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT pedidos.*,
                    pc.titulo AS presente_campanha_titulo
             FROM pedidos
             LEFT JOIN presentes_campanhas pc ON pc.id = pedidos.presente_campanha_id
             WHERE pedidos.id = :id
               AND pedidos.deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function allForBackoffice()
    {
        $stmt = Database::connection()->query(
            'SELECT p.*,
                    pc.titulo AS presente_campanha_titulo
             FROM pedidos p
             LEFT JOIN presentes_campanhas pc ON pc.id = p.presente_campanha_id
             WHERE p.deleted_at IS NULL
             ORDER BY p.id DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function backofficeBaseSql(array $filters, array &$params)
    {
        $sql = ' FROM pedidos p
                 LEFT JOIN presentes_campanhas pc ON pc.id = p.presente_campanha_id
                 WHERE p.deleted_at IS NULL';

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $sql .= ' AND (
                        p.codigo LIKE :q
                        OR p.pagador_nome LIKE :q
                        OR p.pagador_email LIKE :q
                        OR p.pagador_cpf LIKE :q
                        OR p.status LIKE :q
                        OR EXISTS (
                            SELECT 1
                            FROM pedido_itens pi_q
                            INNER JOIN cursos_eventos ce_q ON ce_q.id = pi_q.curso_evento_id
                            WHERE pi_q.pedido_id = p.id
                              AND pi_q.deleted_at IS NULL
                              AND ce_q.deleted_at IS NULL
                              AND ce_q.nome LIKE :q
                        )
                    )';
            $params['q'] = '%' . $q . '%';
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '') {
            $sql .= ' AND p.status = :status';
            $params['status'] = $status;
        }

        $curso = isset($filters['curso']) ? trim((string) $filters['curso']) : '';
        if ($curso !== '') {
            $sql .= ' AND EXISTS (
                        SELECT 1
                        FROM pedido_itens pi_f
                        INNER JOIN cursos_eventos ce_f ON ce_f.id = pi_f.curso_evento_id
                        WHERE pi_f.pedido_id = p.id
                          AND pi_f.deleted_at IS NULL
                          AND ce_f.deleted_at IS NULL
                          AND ce_f.nome LIKE :curso
                    )';
            $params['curso'] = '%' . $curso . '%';
        }

        $de = isset($filters['de']) ? trim((string) $filters['de']) : '';
        if ($de !== '') {
            $sql .= ' AND DATE(p.created_at) >= :de';
            $params['de'] = $de;
        }

        $ate = isset($filters['ate']) ? trim((string) $filters['ate']) : '';
        if ($ate !== '') {
            $sql .= ' AND DATE(p.created_at) <= :ate';
            $params['ate'] = $ate;
        }

        return $sql;
    }

    public function countFilteredForBackoffice(array $filters = array())
    {
        $params = array();
        $sql = 'SELECT COUNT(*) AS total' . $this->backofficeBaseSql($filters, $params);
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return isset($row['total']) ? (int) $row['total'] : 0;
    }

    public function listFilteredForBackoffice(array $filters = array(), $limit = 20, $offset = 0)
    {
        $sortMap = array(
            'id' => 'p.id',
            'codigo' => 'p.codigo',
            'pagador_nome' => 'p.pagador_nome',
            'total' => 'p.total',
            'status' => 'p.status',
            'created_at' => 'p.created_at',
            'updated_at' => 'p.updated_at',
        );

        $sortBy = isset($filters['sort_by']) ? (string) $filters['sort_by'] : 'id';
        $sortDir = strtolower(isset($filters['sort_dir']) ? (string) $filters['sort_dir'] : 'desc');
        if (!isset($sortMap[$sortBy])) {
            $sortBy = 'id';
        }
        if ($sortDir !== 'asc') {
            $sortDir = 'desc';
        }

        $limit = (int) $limit;
        if ($limit <= 0) {
            $limit = 20;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $offset = (int) $offset;
        if ($offset < 0) {
            $offset = 0;
        }

        $params = array();
        $sql = 'SELECT p.*,
                       pc.titulo AS presente_campanha_titulo'
            . $this->backofficeBaseSql($filters, $params)
            . ' ORDER BY ' . $sortMap[$sortBy] . ' ' . strtoupper($sortDir) . ', p.id DESC
               LIMIT :limit OFFSET :offset';

        $stmt = Database::connection()->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function allDeletedForBackoffice(array $filters = array())
    {
        $sql = 'SELECT p.*,
                       GROUP_CONCAT(DISTINCT ce.nome ORDER BY ce.nome SEPARATOR ", ") AS cursos_nome,
                       COUNT(DISTINCT pi.id) AS total_itens,
                       lx.id AS lixeira_id,
                       lx.justificativa AS justificativa_exclusao,
                       lx.snapshot_dados AS snapshot_dados,
                       lx.created_at AS excluido_em,
                       u.nome AS excluido_por_nome
                FROM pedidos p
                LEFT JOIN pedido_itens pi
                    ON pi.pedido_id = p.id
                   AND pi.deleted_at IS NULL
                LEFT JOIN cursos_eventos ce
                    ON ce.id = pi.curso_evento_id
                   AND ce.deleted_at IS NULL
                LEFT JOIN lixeira lx
                    ON lx.entidade_tipo = "pedido"
                   AND lx.entidade_id = p.id
                LEFT JOIN usuarios u
                    ON u.id = lx.excluido_por_usuario_id
                WHERE p.deleted_at IS NOT NULL';

        $params = array();

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $sql .= ' AND (
                        p.codigo LIKE :q
                        OR p.pagador_nome LIKE :q
                        OR p.pagador_email LIKE :q
                        OR p.pagador_cpf LIKE :q
                        OR EXISTS (
                            SELECT 1
                            FROM pedido_itens pi_q
                            INNER JOIN cursos_eventos ce_q ON ce_q.id = pi_q.curso_evento_id
                            WHERE pi_q.pedido_id = p.id
                              AND pi_q.deleted_at IS NULL
                              AND ce_q.deleted_at IS NULL
                              AND ce_q.nome LIKE :q
                        )
                    )';
            $params['q'] = '%' . $q . '%';
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '') {
            $sql .= ' AND p.status = :status';
            $params['status'] = $status;
        }

        $curso = isset($filters['curso']) ? trim((string) $filters['curso']) : '';
        if ($curso !== '') {
            $sql .= ' AND EXISTS (
                        SELECT 1
                        FROM pedido_itens pi_f
                        INNER JOIN cursos_eventos ce_f ON ce_f.id = pi_f.curso_evento_id
                        WHERE pi_f.pedido_id = p.id
                          AND pi_f.deleted_at IS NULL
                          AND ce_f.deleted_at IS NULL
                          AND ce_f.nome LIKE :curso
                    )';
            $params['curso'] = '%' . $curso . '%';
        }

        $dataInicio = isset($filters['de']) ? trim((string) $filters['de']) : '';
        if ($dataInicio !== '') {
            $sql .= ' AND DATE(p.deleted_at) >= :data_inicio';
            $params['data_inicio'] = $dataInicio;
        }

        $dataFim = isset($filters['ate']) ? trim((string) $filters['ate']) : '';
        if ($dataFim !== '') {
            $sql .= ' AND DATE(p.deleted_at) <= :data_fim';
            $params['data_fim'] = $dataFim;
        }

        $sql .= ' GROUP BY p.id
                  ORDER BY p.deleted_at DESC, p.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

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

    public function latestStatusHistoryForStatus($pedidoId, $statusNovo)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM status_pedidos_historico
             WHERE pedido_id = :pedido_id
               AND status_novo = :status_novo
             ORDER BY id DESC
             LIMIT 1'
        );

        $stmt->execute(array(
            'pedido_id' => (int) $pedidoId,
            'status_novo' => $statusNovo,
        ));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByPaymentGatewayExternalId($gateway, $externalId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT pedidos.*,
                    pc.titulo AS presente_campanha_titulo
             FROM pedidos
             LEFT JOIN presentes_campanhas pc ON pc.id = pedidos.presente_campanha_id
             WHERE pedidos.payment_gateway = :payment_gateway
               AND pedidos.payment_external_id = :payment_external_id
               AND pedidos.deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array(
            'payment_gateway' => $gateway,
            'payment_external_id' => $externalId,
        ));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByPaymentGatewayCheckoutId($gateway, $checkoutId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT pedidos.*,
                    pc.titulo AS presente_campanha_titulo
             FROM pedidos
             LEFT JOIN presentes_campanhas pc ON pc.id = pedidos.presente_campanha_id
             WHERE pedidos.payment_gateway = :payment_gateway
               AND pedidos.payment_provider_checkout_id = :payment_provider_checkout_id
               AND pedidos.deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array(
            'payment_gateway' => $gateway,
            'payment_provider_checkout_id' => $checkoutId,
        ));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pedidos
             (codigo, comprador_usuario_id, pagador_usuario_id, pagador_nome, pagador_cpf, pagador_email, pagador_telefone,
              tipo_pedido, status, subtotal, desconto_total, acrescimo_total, total,
              observacoes_internas, observacoes_publicas, canal_origem, is_presente, presente_campanha_id, presente_titulo,
              presente_justificativa, presente_concedido_em, aprovado_por_usuario_id, aprovado_em,
              payment_gateway, payment_external_id, payment_provider_checkout_id, payment_provider_product_id,
              payment_provider_product_external_id, payment_provider_payment_url, payment_provider_receipt_url,
              payment_provider_status, payment_provider_amount, payment_provider_paid_amount, payment_provider_method,
              payment_provider_payload, payment_provider_updated_at, created_at, updated_at, deleted_at)
             VALUES
             (:codigo, :comprador_usuario_id, :pagador_usuario_id, :pagador_nome, :pagador_cpf, :pagador_email, :pagador_telefone,
              :tipo_pedido, :status, :subtotal, :desconto_total, :acrescimo_total, :total,
              :observacoes_internas, :observacoes_publicas, :canal_origem, :is_presente, :presente_campanha_id, :presente_titulo,
              :presente_justificativa, :presente_concedido_em, :aprovado_por_usuario_id, :aprovado_em,
              :payment_gateway, :payment_external_id, :payment_provider_checkout_id, :payment_provider_product_id,
              :payment_provider_product_external_id, :payment_provider_payment_url, :payment_provider_receipt_url,
              :payment_provider_status, :payment_provider_amount, :payment_provider_paid_amount, :payment_provider_method,
              :payment_provider_payload, :payment_provider_updated_at, NOW(), NOW(), NULL)'
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
            'is_presente' => !empty($data['is_presente']) ? 1 : 0,
            'presente_campanha_id' => isset($data['presente_campanha_id']) ? $data['presente_campanha_id'] : null,
            'presente_titulo' => isset($data['presente_titulo']) ? $data['presente_titulo'] : null,
            'presente_justificativa' => isset($data['presente_justificativa']) ? $data['presente_justificativa'] : null,
            'presente_concedido_em' => isset($data['presente_concedido_em']) ? $data['presente_concedido_em'] : null,
            'aprovado_por_usuario_id' => isset($data['aprovado_por_usuario_id']) ? $data['aprovado_por_usuario_id'] : null,
            'aprovado_em' => isset($data['aprovado_em']) ? $data['aprovado_em'] : null,
            'payment_gateway' => isset($data['payment_gateway']) ? $data['payment_gateway'] : null,
            'payment_external_id' => isset($data['payment_external_id']) ? $data['payment_external_id'] : null,
            'payment_provider_checkout_id' => isset($data['payment_provider_checkout_id']) ? $data['payment_provider_checkout_id'] : null,
            'payment_provider_product_id' => isset($data['payment_provider_product_id']) ? $data['payment_provider_product_id'] : null,
            'payment_provider_product_external_id' => isset($data['payment_provider_product_external_id']) ? $data['payment_provider_product_external_id'] : null,
            'payment_provider_payment_url' => isset($data['payment_provider_payment_url']) ? $data['payment_provider_payment_url'] : null,
            'payment_provider_receipt_url' => isset($data['payment_provider_receipt_url']) ? $data['payment_provider_receipt_url'] : null,
            'payment_provider_status' => isset($data['payment_provider_status']) ? $data['payment_provider_status'] : null,
            'payment_provider_amount' => isset($data['payment_provider_amount']) ? $data['payment_provider_amount'] : null,
            'payment_provider_paid_amount' => isset($data['payment_provider_paid_amount']) ? $data['payment_provider_paid_amount'] : null,
            'payment_provider_method' => isset($data['payment_provider_method']) ? $data['payment_provider_method'] : null,
            'payment_provider_payload' => isset($data['payment_provider_payload']) ? $data['payment_provider_payload'] : null,
            'payment_provider_updated_at' => isset($data['payment_provider_updated_at']) ? $data['payment_provider_updated_at'] : null,
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

        return $stmt->rowCount() >= 0;
    }

    public function updatePaymentGatewayData($pedidoId, array $data)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pedidos
             SET payment_gateway = :payment_gateway,
                 payment_external_id = :payment_external_id,
                 payment_provider_checkout_id = :payment_provider_checkout_id,
                 payment_provider_product_id = :payment_provider_product_id,
                 payment_provider_product_external_id = :payment_provider_product_external_id,
                 payment_provider_payment_url = :payment_provider_payment_url,
                 payment_provider_receipt_url = :payment_provider_receipt_url,
                 payment_provider_status = :payment_provider_status,
                 payment_provider_amount = :payment_provider_amount,
                 payment_provider_paid_amount = :payment_provider_paid_amount,
                 payment_provider_method = :payment_provider_method,
                 payment_provider_payload = :payment_provider_payload,
                 payment_provider_updated_at = :payment_provider_updated_at,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'payment_gateway' => isset($data['payment_gateway']) ? $data['payment_gateway'] : null,
            'payment_external_id' => isset($data['payment_external_id']) ? $data['payment_external_id'] : null,
            'payment_provider_checkout_id' => isset($data['payment_provider_checkout_id']) ? $data['payment_provider_checkout_id'] : null,
            'payment_provider_product_id' => isset($data['payment_provider_product_id']) ? $data['payment_provider_product_id'] : null,
            'payment_provider_product_external_id' => isset($data['payment_provider_product_external_id']) ? $data['payment_provider_product_external_id'] : null,
            'payment_provider_payment_url' => isset($data['payment_provider_payment_url']) ? $data['payment_provider_payment_url'] : null,
            'payment_provider_receipt_url' => isset($data['payment_provider_receipt_url']) ? $data['payment_provider_receipt_url'] : null,
            'payment_provider_status' => isset($data['payment_provider_status']) ? $data['payment_provider_status'] : null,
            'payment_provider_amount' => isset($data['payment_provider_amount']) ? $data['payment_provider_amount'] : null,
            'payment_provider_paid_amount' => isset($data['payment_provider_paid_amount']) ? $data['payment_provider_paid_amount'] : null,
            'payment_provider_method' => isset($data['payment_provider_method']) ? $data['payment_provider_method'] : null,
            'payment_provider_payload' => isset($data['payment_provider_payload']) ? $data['payment_provider_payload'] : null,
            'payment_provider_updated_at' => isset($data['payment_provider_updated_at']) ? $data['payment_provider_updated_at'] : null,
            'id' => $pedidoId,
        ));

        return $stmt->rowCount() >= 0;
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

    public function markPaid($pedidoId, $usuarioId = null)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pedidos
             SET status = "pago",
                 aprovado_por_usuario_id = COALESCE(aprovado_por_usuario_id, :aprovado_por_usuario_id),
                 aprovado_em = COALESCE(aprovado_em, NOW()),
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

<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Inscricao
{
    public function updateProgress($inscricaoId, $percentual, $aptoCertificado = null, $concluidaEm = null)
    {
        $sql = 'UPDATE inscricoes
                SET percentual_progresso = :percentual_progresso,
                    updated_at = NOW()';
        $params = array(
            'percentual_progresso' => $percentual,
            'id' => $inscricaoId,
        );

        if ($aptoCertificado !== null) {
            $sql .= ', apto_certificado = :apto_certificado';
            $params['apto_certificado'] = (int) $aptoCertificado;
        }

        if ($concluidaEm !== null) {
            $sql .= ', concluida_em = :concluida_em';
            $params['concluida_em'] = $concluidaEm;
        }

        $sql .= ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public function updateAcademico($inscricaoId, array $data)
    {
        $sql = 'UPDATE inscricoes
                SET percentual_progresso = COALESCE(:percentual_progresso, percentual_progresso),
                    presenca_percentual = COALESCE(:presenca_percentual, presenca_percentual),
                    nota_final = COALESCE(:nota_final, nota_final),
                    apto_certificado = COALESCE(:apto_certificado, apto_certificado),
                    concluida_em = COALESCE(:concluida_em, concluida_em),
                    updated_at = NOW()
                WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array(
            'id' => $inscricaoId,
            'percentual_progresso' => array_key_exists('percentual_progresso', $data) ? $data['percentual_progresso'] : null,
            'presenca_percentual' => array_key_exists('presenca_percentual', $data) ? $data['presenca_percentual'] : null,
            'nota_final' => array_key_exists('nota_final', $data) ? $data['nota_final'] : null,
            'apto_certificado' => array_key_exists('apto_certificado', $data) ? (int) $data['apto_certificado'] : null,
            'concluida_em' => array_key_exists('concluida_em', $data) ? $data['concluida_em'] : null,
        ));
    }

    public function forUsuarioAprovadas($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.*,
                    p.codigo AS pedido_codigo,
                    p.status AS pedido_status,
                    p.pagador_nome,
                    p.pagador_email,
                    pp.nome AS participante_nome,
                    pp.cpf AS participante_cpf,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    ce.tipo AS curso_tipo,
                    ce.modalidade AS curso_modalidade,
                    t.nome AS turma_nome,
                    t.codigo AS turma_codigo,
                    cp.status AS comprovante_status,
                    cp.versao AS comprovante_versao,
                    c.id AS certificado_id,
                    c.codigo AS certificado_codigo,
                    c.status AS certificado_status,
                    c.pdf_caminho AS certificado_pdf_caminho,
                    c.emitido_em AS certificado_emitido_em
             FROM inscricoes i
             INNER JOIN pedidos p ON p.id = i.pedido_id
             INNER JOIN participantes_pedido pp ON pp.id = i.participante_pedido_id
             INNER JOIN cursos_eventos ce ON ce.id = i.curso_evento_id
             LEFT JOIN turmas t ON t.id = i.turma_id
             LEFT JOIN comprovantes_pix cp ON cp.pedido_id = i.pedido_id AND cp.is_atual = 1 AND cp.deleted_at IS NULL
             LEFT JOIN certificados c ON c.inscricao_id = i.id AND c.deleted_at IS NULL AND c.status = "emitido"
             WHERE i.deleted_at IS NULL
               AND i.status NOT IN ("pendente", "cancelada", "reprovada")
               AND i.usuario_id = :usuario_id
               AND (p.status IN ("aprovado", "pago") OR cp.status = "aprovado")
             ORDER BY i.id DESC'
        );

        $stmt->execute(array('usuario_id' => $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function forUsuario($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.*,
                    p.codigo AS pedido_codigo,
                    p.status AS pedido_status,
                    p.pagador_nome,
                    p.pagador_email,
                    pp.nome AS participante_nome,
                    pp.cpf AS participante_cpf,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    ce.tipo AS curso_tipo,
                    ce.modalidade AS curso_modalidade,
                    t.nome AS turma_nome,
                    t.codigo AS turma_codigo,
                    cp.status AS comprovante_status,
                    cp.versao AS comprovante_versao,
                    c.id AS certificado_id,
                    c.codigo AS certificado_codigo,
                    c.status AS certificado_status,
                    c.pdf_caminho AS certificado_pdf_caminho,
                    c.emitido_em AS certificado_emitido_em
             FROM inscricoes i
             INNER JOIN pedidos p ON p.id = i.pedido_id
             INNER JOIN participantes_pedido pp ON pp.id = i.participante_pedido_id
             INNER JOIN cursos_eventos ce ON ce.id = i.curso_evento_id
             LEFT JOIN turmas t ON t.id = i.turma_id
             LEFT JOIN comprovantes_pix cp ON cp.pedido_id = i.pedido_id AND cp.is_atual = 1 AND cp.deleted_at IS NULL
             LEFT JOIN certificados c ON c.inscricao_id = i.id AND c.deleted_at IS NULL AND c.status = "emitido"
             WHERE i.deleted_at IS NULL
               AND i.status NOT IN ("pendente", "cancelada", "reprovada")
               AND i.usuario_id = :usuario_id
               AND (p.status IN ("aprovado", "pago") OR cp.status = "aprovado")
             ORDER BY i.id DESC'
        );

        $stmt->execute(array('usuario_id' => $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM inscricoes
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function forPedido($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM inscricoes
             WHERE pedido_id = :pedido_id
               AND deleted_at IS NULL
             ORDER BY id ASC'
        );

        $stmt->execute(array('pedido_id' => $pedidoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByPedidoItemAndParticipante($pedidoItemId, $participantePedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM inscricoes
             WHERE pedido_item_id = :pedido_item_id
               AND participante_pedido_id = :participante_pedido_id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array(
            'pedido_item_id' => $pedidoItemId,
            'participante_pedido_id' => $participantePedidoId,
        ));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function allForBackoffice()
    {
        $stmt = Database::connection()->query(
            'SELECT i.*, p.codigo AS pedido_codigo, p.pagador_nome, p.pagador_email, p.total AS pedido_total,
                    p.status AS pedido_status, pp.nome AS participante_nome, pp.cpf AS participante_cpf,
                    ce.nome AS curso_nome, t.nome AS turma_nome,
                    c.id AS certificado_id, c.codigo AS certificado_codigo, c.status AS certificado_status
             FROM inscricoes i
             INNER JOIN pedidos p ON p.id = i.pedido_id
             INNER JOIN participantes_pedido pp ON pp.id = i.participante_pedido_id
             INNER JOIN cursos_eventos ce ON ce.id = i.curso_evento_id
             LEFT JOIN turmas t ON t.id = i.turma_id
             LEFT JOIN certificados c ON c.inscricao_id = i.id AND c.deleted_at IS NULL AND c.status = "emitido"
             WHERE i.deleted_at IS NULL
             ORDER BY i.id DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listForContext($cursoId, $turmaId = null)
    {
        $sql = 'SELECT i.*, p.codigo AS pedido_codigo, p.pagador_nome, p.pagador_email, p.total AS pedido_total,
                    p.status AS pedido_status, pp.nome AS participante_nome, pp.cpf AS participante_cpf,
                    ce.nome AS curso_nome, t.nome AS turma_nome,
                    c.id AS certificado_id, c.codigo AS certificado_codigo, c.status AS certificado_status
             FROM inscricoes i
             INNER JOIN pedidos p ON p.id = i.pedido_id
             INNER JOIN participantes_pedido pp ON pp.id = i.participante_pedido_id
             INNER JOIN cursos_eventos ce ON ce.id = i.curso_evento_id
             LEFT JOIN turmas t ON t.id = i.turma_id
             LEFT JOIN certificados c ON c.inscricao_id = i.id AND c.deleted_at IS NULL AND c.status = "emitido"
             WHERE i.deleted_at IS NULL
               AND i.curso_evento_id = :curso_evento_id';

        $params = array('curso_evento_id' => $cursoId);

        if ($turmaId !== null) {
            $sql .= ' AND (i.turma_id = :turma_id OR i.turma_id IS NULL)';
            $params['turma_id'] = $turmaId;
        } else {
            $sql .= ' AND i.turma_id IS NULL';
        }

        $sql .= ' ORDER BY i.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function historyForInscricao($inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM status_inscricoes_historico
             WHERE inscricao_id = :inscricao_id
             ORDER BY id DESC'
        );

        $stmt->execute(array('inscricao_id' => $inscricaoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO inscricoes
             (pedido_id, pedido_item_id, participante_pedido_id, usuario_id, curso_evento_id, turma_id, status, confirmado_em, created_at, updated_at, deleted_at)
             VALUES
             (:pedido_id, :pedido_item_id, :participante_pedido_id, :usuario_id, :curso_evento_id, :turma_id, :status, :confirmado_em, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'pedido_id' => $data['pedido_id'],
            'pedido_item_id' => $data['pedido_item_id'],
            'participante_pedido_id' => $data['participante_pedido_id'],
            'usuario_id' => isset($data['usuario_id']) ? $data['usuario_id'] : null,
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'pendente',
            'confirmado_em' => isset($data['confirmado_em']) ? $data['confirmado_em'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function updateStatus($inscricaoId, $status, $confirmadoEm = null)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE inscricoes
             SET status = :status,
                 confirmado_em = :confirmado_em,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'status' => $status,
            'confirmado_em' => $confirmadoEm,
            'id' => $inscricaoId,
        ));
    }

    public function softDelete($inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE inscricoes
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array('id' => $inscricaoId));
    }

    public function addStatusHistory($inscricaoId, $statusAnterior, $statusNovo, $observacao = null, $alteradoPorUsuarioId = null)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO status_inscricoes_historico
             (inscricao_id, status_anterior, status_novo, observacao, alterado_por_usuario_id, created_at)
             VALUES
             (:inscricao_id, :status_anterior, :status_novo, :observacao, :alterado_por_usuario_id, NOW())'
        );

        $stmt->execute(array(
            'inscricao_id' => $inscricaoId,
            'status_anterior' => $statusAnterior,
            'status_novo' => $statusNovo,
            'observacao' => $observacao,
            'alterado_por_usuario_id' => $alteradoPorUsuarioId,
        ));
    }
}

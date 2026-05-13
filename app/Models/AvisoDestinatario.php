<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AvisoDestinatario
{
    public function existsDestinatario($avisoId, $usuarioId, $cursoEventoId = null)
    {
        $sql = 'SELECT id
                FROM avisos_destinatarios
                WHERE aviso_id = :aviso_id
                  AND usuario_id = :usuario_id
                  AND deleted_at IS NULL';
        $params = array(
            'aviso_id' => (int) $avisoId,
            'usuario_id' => (int) $usuarioId,
        );

        if ($cursoEventoId === null) {
            $sql .= ' AND curso_evento_id IS NULL';
        } else {
            $sql .= ' AND curso_evento_id = :curso_evento_id';
            $params['curso_evento_id'] = (int) $cursoEventoId;
        }

        $sql .= ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return !empty($row);
    }

    public function createMany($avisoId, array $destinatarios)
    {
        $pdo = Database::connection();
        $inserted = 0;
        $stmt = $pdo->prepare(
            'INSERT INTO avisos_destinatarios
             (aviso_id, usuario_id, curso_evento_id, inscricao_id, pedido_id, status, criado_em, deleted_at)
             VALUES
             (:aviso_id, :usuario_id, :curso_evento_id, :inscricao_id, :pedido_id, :status, NOW(), NULL)'
        );

        foreach ($destinatarios as $destinatario) {
            $usuarioId = isset($destinatario['usuario_id']) ? (int) $destinatario['usuario_id'] : 0;
            if ($usuarioId <= 0) {
                continue;
            }

            $cursoEventoId = array_key_exists('curso_evento_id', $destinatario) && $destinatario['curso_evento_id'] !== null ? (int) $destinatario['curso_evento_id'] : null;
            $inscricaoId = array_key_exists('inscricao_id', $destinatario) && $destinatario['inscricao_id'] !== null ? (int) $destinatario['inscricao_id'] : null;
            $pedidoId = array_key_exists('pedido_id', $destinatario) && $destinatario['pedido_id'] !== null ? (int) $destinatario['pedido_id'] : null;

            if ($this->existsDestinatario($avisoId, $usuarioId, $cursoEventoId)) {
                continue;
            }

            $stmt->execute(array(
                'aviso_id' => (int) $avisoId,
                'usuario_id' => $usuarioId,
                'curso_evento_id' => $cursoEventoId,
                'inscricao_id' => $inscricaoId,
                'pedido_id' => $pedidoId,
                'status' => isset($destinatario['status']) ? $destinatario['status'] : 'ativo',
            ));
            $inserted++;
        }

        return $inserted;
    }

    public function countByAviso($avisoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN visualizado_em IS NOT NULL THEN 1 ELSE 0 END) AS visualizados,
                    SUM(CASE WHEN ocultado_em IS NOT NULL THEN 1 ELSE 0 END) AS ocultados,
                    SUM(CASE WHEN status = "ativo" AND deleted_at IS NULL THEN 1 ELSE 0 END) AS ativos,
                    SUM(CASE WHEN status = "cancelado" AND deleted_at IS NULL THEN 1 ELSE 0 END) AS cancelados
             FROM avisos_destinatarios
             WHERE aviso_id = :aviso_id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array('aviso_id' => (int) $avisoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return array(
            'total' => !empty($row) ? (int) $row['total'] : 0,
            'visualizados' => !empty($row) ? (int) $row['visualizados'] : 0,
            'ocultados' => !empty($row) ? (int) $row['ocultados'] : 0,
            'ativos' => !empty($row) ? (int) $row['ativos'] : 0,
            'cancelados' => !empty($row) ? (int) $row['cancelados'] : 0,
        );
    }

    public function findByAviso($avisoId, array $filters = array())
    {
        $where = array('ad.aviso_id = :aviso_id', 'ad.deleted_at IS NULL');
        $params = array('aviso_id' => (int) $avisoId);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(u.nome LIKE :q OR u.email LIKE :q OR u.cpf LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        $statusFiltro = trim((string) ($filters['status'] ?? ''));
        if ($statusFiltro === 'visualizados') {
            $where[] = 'ad.visualizado_em IS NOT NULL';
        } elseif ($statusFiltro === 'nao_visualizados') {
            $where[] = 'ad.visualizado_em IS NULL';
            $where[] = 'ad.ocultado_em IS NULL';
        } elseif ($statusFiltro === 'ocultados') {
            $where[] = 'ad.ocultado_em IS NOT NULL';
        }

        $sql = 'SELECT ad.*,
                       u.nome AS usuario_nome,
                       u.email AS usuario_email,
                       u.cpf AS usuario_cpf,
                       ce.nome AS curso_nome,
                       ce.slug AS curso_slug
                FROM avisos_destinatarios ad
                INNER JOIN usuarios u ON u.id = ad.usuario_id
                LEFT JOIN cursos_eventos ce ON ce.id = ad.curso_evento_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ad.ocultado_em DESC, ad.visualizado_em DESC, ad.criado_em DESC, ad.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function avisosAtivosParaUsuario($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.*,
                    ad.id AS destinatario_id,
                    ad.curso_evento_id AS destinatario_curso_evento_id,
                    ad.inscricao_id,
                    ad.pedido_id,
                    ad.visualizado_em,
                    ad.ocultado_em,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug
             FROM avisos_destinatarios ad
             INNER JOIN avisos a ON a.id = ad.aviso_id
             LEFT JOIN cursos_eventos ce ON ce.id = COALESCE(ad.curso_evento_id, a.curso_evento_id)
             WHERE ad.usuario_id = :usuario_id
               AND ad.deleted_at IS NULL
               AND ad.status = "ativo"
               AND ad.ocultado_em IS NULL
               AND a.deleted_at IS NULL
               AND a.status = "enviado"
               AND (a.mostrar_inicio IS NULL OR a.mostrar_inicio <= NOW())
               AND (a.mostrar_fim IS NULL OR a.mostrar_fim >= NOW())
             ORDER BY a.destaque DESC, a.prioridade DESC, a.enviado_em DESC, a.criado_em DESC, a.id DESC'
        );

        $stmt->execute(array('usuario_id' => (int) $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarVisualizado($avisoId, $usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE avisos_destinatarios
             SET visualizado_em = COALESCE(visualizado_em, NOW())
             WHERE aviso_id = :aviso_id
               AND usuario_id = :usuario_id
               AND deleted_at IS NULL
               AND status = "ativo"
               AND ocultado_em IS NULL'
        );

        $stmt->execute(array(
            'aviso_id' => (int) $avisoId,
            'usuario_id' => (int) $usuarioId,
        ));
    }

    public function ocultar($avisoId, $usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE avisos_destinatarios
             SET status = "cancelado",
                 ocultado_em = NOW()
             WHERE aviso_id = :aviso_id
               AND usuario_id = :usuario_id
               AND deleted_at IS NULL'
        );

        $stmt->execute(array(
            'aviso_id' => (int) $avisoId,
            'usuario_id' => (int) $usuarioId,
        ));
    }
}

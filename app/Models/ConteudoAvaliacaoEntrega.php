<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoAvaliacaoEntrega
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_avaliacoes_entregas
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listPendentesByCursoIds(array $cursoIds, $limit = 50, $offset = 0)
    {
        $cursoIds = array_values(array_unique(array_map('intval', $cursoIds)));
        if (empty($cursoIds)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($cursoIds), '?'));
        $params = array_merge($cursoIds, array((int) $limit, (int) $offset));

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_avaliacoes_entregas
             WHERE deleted_at IS NULL
               AND status IN ("enviada","reenviada","devolvida")
               AND curso_evento_id IN (' . $placeholders . ')
             ORDER BY enviado_em IS NULL, enviado_em DESC, id DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPendentesByCursoIds(array $cursoIds)
    {
        $cursoIds = array_values(array_unique(array_map('intval', $cursoIds)));
        if (empty($cursoIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($cursoIds), '?'));
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total
             FROM conteudo_avaliacoes_entregas
             WHERE deleted_at IS NULL
               AND status IN ("enviada","reenviada","devolvida")
               AND curso_evento_id IN (' . $placeholders . ')'
        );
        $stmt->execute($cursoIds);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row) ? (int) $row['total'] : 0;
    }

    public function findLatestByContext($avaliacaoId, $alunoId, $inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_avaliacoes_entregas
             WHERE avaliacao_id = :avaliacao_id
               AND aluno_id = :aluno_id
               AND inscricao_id = :inscricao_id
               AND deleted_at IS NULL
             ORDER BY enviado_em IS NULL, enviado_em DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute(array(
            'avaliacao_id' => (int) $avaliacaoId,
            'aluno_id' => (int) $alunoId,
            'inscricao_id' => (int) $inscricaoId,
        ));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listarUltimasEntregasPorItensAluno(array $itemIds, $alunoId, $inscricaoId)
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        $alunoId = (int) $alunoId;
        $inscricaoId = (int) $inscricaoId;

        if (empty($itemIds) || $alunoId <= 0 || $inscricaoId <= 0) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $sql = 'SELECT *
                FROM conteudo_avaliacoes_entregas
                WHERE deleted_at IS NULL
                  AND aluno_id = ?
                  AND inscricao_id = ?
                  AND item_id IN (' . $placeholders . ')
                ORDER BY item_id ASC, tentativa DESC, id DESC';

        $params = array_merge(array($alunoId, $inscricaoId), $itemIds);
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = array();
        foreach ($rows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            if ($itemId <= 0 || isset($resultado[$itemId])) {
                continue;
            }
            $resultado[$itemId] = $row;
        }

        return $resultado;
    }

    public function listUltimasPorInscricao($alunoId, $inscricaoId)
    {
        $alunoId = (int) $alunoId;
        $inscricaoId = (int) $inscricaoId;
        if ($alunoId <= 0 || $inscricaoId <= 0) {
            return array();
        }

        $stmt = Database::connection()->prepare(
            'SELECT e1.*
             FROM conteudo_avaliacoes_entregas e1
             INNER JOIN (
                 SELECT MAX(id) AS id
                 FROM conteudo_avaliacoes_entregas
                 WHERE deleted_at IS NULL
                   AND status <> "cancelada"
                   AND aluno_id = :aluno_id
                   AND inscricao_id = :inscricao_id
                 GROUP BY avaliacao_id
             ) ult ON ult.id = e1.id
             WHERE e1.deleted_at IS NULL'
        );
        $stmt->execute(array('aluno_id' => $alunoId, 'inscricao_id' => $inscricaoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_avaliacoes_entregas
             (avaliacao_id, item_id, curso_evento_id, turma_id, inscricao_id, aluno_id, resposta, status, nota, feedback, corrigido_por, corrigido_em, enviado_em,
              prazo_liberado_ate, liberado_reenvio_por, liberado_reenvio_em, tentativa, created_at, updated_at, deleted_at)
             VALUES
             (:avaliacao_id, :item_id, :curso_evento_id, :turma_id, :inscricao_id, :aluno_id, :resposta, :status, :nota, :feedback, :corrigido_por, :corrigido_em, :enviado_em,
              :prazo_liberado_ate, :liberado_reenvio_por, :liberado_reenvio_em, :tentativa, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'avaliacao_id' => (int) $data['avaliacao_id'],
            'item_id' => (int) $data['item_id'],
            'curso_evento_id' => (int) $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'inscricao_id' => isset($data['inscricao_id']) ? $data['inscricao_id'] : null,
            'aluno_id' => (int) $data['aluno_id'],
            'resposta' => isset($data['resposta']) ? $data['resposta'] : null,
            'status' => isset($data['status']) ? (string) $data['status'] : 'enviada',
            'nota' => array_key_exists('nota', $data) ? $data['nota'] : null,
            'feedback' => isset($data['feedback']) ? $data['feedback'] : null,
            'corrigido_por' => isset($data['corrigido_por']) ? $data['corrigido_por'] : null,
            'corrigido_em' => isset($data['corrigido_em']) ? $data['corrigido_em'] : null,
            'enviado_em' => isset($data['enviado_em']) ? $data['enviado_em'] : null,
            'prazo_liberado_ate' => isset($data['prazo_liberado_ate']) ? $data['prazo_liberado_ate'] : null,
            'liberado_reenvio_por' => isset($data['liberado_reenvio_por']) ? $data['liberado_reenvio_por'] : null,
            'liberado_reenvio_em' => isset($data['liberado_reenvio_em']) ? $data['liberado_reenvio_em'] : null,
            'tentativa' => isset($data['tentativa']) ? (int) $data['tentativa'] : 1,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_avaliacoes_entregas
             SET resposta = :resposta,
                 status = :status,
                 nota = :nota,
                 feedback = :feedback,
                 corrigido_por = :corrigido_por,
                 corrigido_em = :corrigido_em,
                 enviado_em = :enviado_em,
                 prazo_liberado_ate = :prazo_liberado_ate,
                 liberado_reenvio_por = :liberado_reenvio_por,
                 liberado_reenvio_em = :liberado_reenvio_em,
                 tentativa = :tentativa,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'resposta' => isset($data['resposta']) ? $data['resposta'] : null,
            'status' => isset($data['status']) ? (string) $data['status'] : 'enviada',
            'nota' => array_key_exists('nota', $data) ? $data['nota'] : null,
            'feedback' => isset($data['feedback']) ? $data['feedback'] : null,
            'corrigido_por' => isset($data['corrigido_por']) ? $data['corrigido_por'] : null,
            'corrigido_em' => isset($data['corrigido_em']) ? $data['corrigido_em'] : null,
            'enviado_em' => isset($data['enviado_em']) ? $data['enviado_em'] : null,
            'prazo_liberado_ate' => isset($data['prazo_liberado_ate']) ? $data['prazo_liberado_ate'] : null,
            'liberado_reenvio_por' => isset($data['liberado_reenvio_por']) ? $data['liberado_reenvio_por'] : null,
            'liberado_reenvio_em' => isset($data['liberado_reenvio_em']) ? $data['liberado_reenvio_em'] : null,
            'tentativa' => isset($data['tentativa']) ? (int) $data['tentativa'] : 1,
            'id' => (int) $id,
        ));

        return $stmt->rowCount() > 0;
    }
}

<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class NotaAvaliacao
{
    public function findByContext($avaliacaoId, $inscricaoId, $usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM notas_avaliacoes
             WHERE avaliacao_id = :avaliacao_id
               AND inscricao_id = :inscricao_id
               AND usuario_id <=> :usuario_id
             LIMIT 1'
        );
        $stmt->execute(array(
            'avaliacao_id' => $avaliacaoId,
            'inscricao_id' => $inscricaoId,
            'usuario_id' => $usuarioId,
        ));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForInscricao($inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM notas_avaliacoes
             WHERE inscricao_id = :inscricao_id
               AND deleted_at IS NULL
             ORDER BY id DESC'
        );
        $stmt->execute(array('inscricao_id' => $inscricaoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsert(array $data)
    {
        $existente = $this->findByContext($data['avaliacao_id'], $data['inscricao_id'], $data['usuario_id']);
        $stmt = Database::connection()->prepare(
            'INSERT INTO notas_avaliacoes
             (avaliacao_id, inscricao_id, usuario_id, nota, percentual, status, observacao, corrigida_por_usuario_id, corrigida_em, created_at, updated_at, deleted_at)
             VALUES
             (:avaliacao_id, :inscricao_id, :usuario_id, :nota, :percentual, :status, :observacao, :corrigida_por_usuario_id, :corrigida_em, NOW(), NOW(), NULL)
             ON DUPLICATE KEY UPDATE
             nota = VALUES(nota),
             percentual = VALUES(percentual),
             status = VALUES(status),
             observacao = VALUES(observacao),
             corrigida_por_usuario_id = VALUES(corrigida_por_usuario_id),
             corrigida_em = VALUES(corrigida_em),
             updated_at = NOW()'
        );

        $stmt->execute(array(
            'avaliacao_id' => $data['avaliacao_id'],
            'inscricao_id' => $data['inscricao_id'],
            'usuario_id' => isset($data['usuario_id']) ? $data['usuario_id'] : null,
            'nota' => isset($data['nota']) ? $data['nota'] : 0,
            'percentual' => isset($data['percentual']) ? $data['percentual'] : 0,
            'status' => isset($data['status']) ? $data['status'] : 'pendente',
            'observacao' => isset($data['observacao']) ? $data['observacao'] : null,
            'corrigida_por_usuario_id' => isset($data['corrigida_por_usuario_id']) ? $data['corrigida_por_usuario_id'] : null,
            'corrigida_em' => isset($data['corrigida_em']) ? $data['corrigida_em'] : null,
        ));

        return $existente ? (int) $existente['id'] : (int) Database::connection()->lastInsertId();
    }
}

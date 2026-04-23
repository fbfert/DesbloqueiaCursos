<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ProgressoUsuarioAula
{
    public function findByContext($inscricaoId, $usuarioId, $aulaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM progresso_usuario_aulas
             WHERE inscricao_id = :inscricao_id
               AND usuario_id = :usuario_id
               AND aula_id = :aula_id
             LIMIT 1'
        );
        $stmt->execute(array(
            'inscricao_id' => $inscricaoId,
            'usuario_id' => $usuarioId,
            'aula_id' => $aulaId,
        ));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForInscricao($inscricaoId, $usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM progresso_usuario_aulas
             WHERE inscricao_id = :inscricao_id
               AND usuario_id = :usuario_id
             ORDER BY aula_id ASC'
        );
        $stmt->execute(array(
            'inscricao_id' => $inscricaoId,
            'usuario_id' => $usuarioId,
        ));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsert(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO progresso_usuario_aulas
             (inscricao_id, usuario_id, curso_evento_id, turma_id, aula_id, percentual, concluido, visualizado_em, concluido_em, created_at, updated_at)
             VALUES
             (:inscricao_id, :usuario_id, :curso_evento_id, :turma_id, :aula_id, :percentual, :concluido, :visualizado_em, :concluido_em, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
             percentual = VALUES(percentual),
             concluido = VALUES(concluido),
             visualizado_em = VALUES(visualizado_em),
             concluido_em = VALUES(concluido_em),
             updated_at = NOW()'
        );

        $stmt->execute(array(
            'inscricao_id' => $data['inscricao_id'],
            'usuario_id' => $data['usuario_id'],
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'aula_id' => $data['aula_id'],
            'percentual' => isset($data['percentual']) ? (float) $data['percentual'] : 0,
            'concluido' => !empty($data['concluido']) ? 1 : 0,
            'visualizado_em' => isset($data['visualizado_em']) ? $data['visualizado_em'] : null,
            'concluido_em' => isset($data['concluido_em']) ? $data['concluido_em'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }
}

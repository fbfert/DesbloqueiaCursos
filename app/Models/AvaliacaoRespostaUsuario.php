<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AvaliacaoRespostaUsuario
{
    public function findByContext($avaliacaoId, $perguntaId, $inscricaoId, $usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM avaliacao_respostas_usuario
             WHERE avaliacao_id = :avaliacao_id
               AND pergunta_id = :pergunta_id
               AND inscricao_id = :inscricao_id
               AND usuario_id <=> :usuario_id
             LIMIT 1'
        );
        $stmt->execute(array(
            'avaliacao_id' => $avaliacaoId,
            'pergunta_id' => $perguntaId,
            'inscricao_id' => $inscricaoId,
            'usuario_id' => $usuarioId,
        ));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForAvaliacao($avaliacaoId, $inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM avaliacao_respostas_usuario
             WHERE avaliacao_id = :avaliacao_id
               AND inscricao_id = :inscricao_id
               AND deleted_at IS NULL
             ORDER BY pergunta_id ASC, id ASC'
        );
        $stmt->execute(array('avaliacao_id' => $avaliacaoId, 'inscricao_id' => $inscricaoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsert(array $data)
    {
        $existente = $this->findByContext($data['avaliacao_id'], $data['pergunta_id'], $data['inscricao_id'], $data['usuario_id']);
        $stmt = Database::connection()->prepare(
            'INSERT INTO avaliacao_respostas_usuario
             (avaliacao_id, pergunta_id, inscricao_id, usuario_id, resposta_texto, resposta_json, pontuacao, corrigida_por_usuario_id, corrigida_em, created_at, updated_at, deleted_at)
             VALUES
             (:avaliacao_id, :pergunta_id, :inscricao_id, :usuario_id, :resposta_texto, :resposta_json, :pontuacao, :corrigida_por_usuario_id, :corrigida_em, NOW(), NOW(), NULL)
             ON DUPLICATE KEY UPDATE
             resposta_texto = VALUES(resposta_texto),
             resposta_json = VALUES(resposta_json),
             pontuacao = VALUES(pontuacao),
             corrigida_por_usuario_id = VALUES(corrigida_por_usuario_id),
             corrigida_em = VALUES(corrigida_em),
             updated_at = NOW()'
        );

        $stmt->execute(array(
            'avaliacao_id' => $data['avaliacao_id'],
            'pergunta_id' => $data['pergunta_id'],
            'inscricao_id' => $data['inscricao_id'],
            'usuario_id' => isset($data['usuario_id']) ? $data['usuario_id'] : null,
            'resposta_texto' => isset($data['resposta_texto']) ? $data['resposta_texto'] : null,
            'resposta_json' => isset($data['resposta_json']) ? $data['resposta_json'] : null,
            'pontuacao' => isset($data['pontuacao']) ? $data['pontuacao'] : null,
            'corrigida_por_usuario_id' => isset($data['corrigida_por_usuario_id']) ? $data['corrigida_por_usuario_id'] : null,
            'corrigida_em' => isset($data['corrigida_em']) ? $data['corrigida_em'] : null,
        ));

        return $existente ? (int) $existente['id'] : (int) Database::connection()->lastInsertId();
    }

    public function softDeleteForAvaliacao($avaliacaoId, $inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE avaliacao_respostas_usuario
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE avaliacao_id = :avaliacao_id
               AND inscricao_id = :inscricao_id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array('avaliacao_id' => $avaliacaoId, 'inscricao_id' => $inscricaoId));
    }
}

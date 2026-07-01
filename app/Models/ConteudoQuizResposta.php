<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoQuizResposta
{
    public function findByTentativaEPergunta($tentativaId, $perguntaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_respostas
             WHERE tentativa_id = :tentativa_id AND pergunta_id = :pergunta_id
               AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array(
            'tentativa_id' => (int) $tentativaId,
            'pergunta_id'  => (int) $perguntaId,
        ));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listForTentativa($tentativaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_respostas
             WHERE tentativa_id = :tentativa_id AND deleted_at IS NULL
             ORDER BY id ASC'
        );
        $stmt->execute(array('tentativa_id' => (int) $tentativaId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsert(array $data)
    {
        $existing = $this->findByTentativaEPergunta(
            (int) ($data['tentativa_id'] ?? 0),
            (int) ($data['pergunta_id'] ?? 0)
        );

        if ($existing) {
            $stmt = Database::connection()->prepare(
                'UPDATE conteudo_quiz_respostas SET
                  alternativa_id = :alternativa_id,
                  resposta_json = :resposta_json,
                  correta = :correta,
                  pontos_obtidos = :pontos_obtidos,
                  pergunta_snapshot_json = :pergunta_snapshot_json,
                  updated_at = NOW()
                 WHERE id = :id AND deleted_at IS NULL'
            );
            $params = $this->buildParams($data);
            unset($params['tentativa_id'], $params['pergunta_id']);
            $params['id'] = (int) $existing['id'];
            $stmt->execute($params);
            return (int) $existing['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_quiz_respostas
             (tentativa_id, pergunta_id, alternativa_id, resposta_json, correta,
              pontos_obtidos, pergunta_snapshot_json, created_at, updated_at)
             VALUES
             (:tentativa_id, :pergunta_id, :alternativa_id, :resposta_json, :correta,
              :pontos_obtidos, :pergunta_snapshot_json, NOW(), NOW())'
        );
        $stmt->execute($this->buildParams($data));
        return (int) Database::connection()->lastInsertId();
    }

    private function buildParams(array $data)
    {
        $respostaJson = isset($data['resposta_json']) ? $data['resposta_json'] : null;
        if (is_array($respostaJson)) {
            $respostaJson = json_encode($respostaJson, JSON_UNESCAPED_UNICODE);
        }
        $perguntaSnapshot = isset($data['pergunta_snapshot_json']) ? $data['pergunta_snapshot_json'] : null;
        if (is_array($perguntaSnapshot)) {
            $perguntaSnapshot = json_encode($perguntaSnapshot, JSON_UNESCAPED_UNICODE);
        }

        return array(
            'tentativa_id'          => (int) ($data['tentativa_id'] ?? 0),
            'pergunta_id'           => (int) ($data['pergunta_id'] ?? 0),
            'alternativa_id'        => isset($data['alternativa_id']) && $data['alternativa_id'] ? (int) $data['alternativa_id'] : null,
            'resposta_json'         => $respostaJson,
            'correta'               => !empty($data['correta']) ? 1 : 0,
            'pontos_obtidos'        => (float) ($data['pontos_obtidos'] ?? 0),
            'pergunta_snapshot_json'=> $perguntaSnapshot,
        );
    }
}

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

    /**
     * Grava a resposta da tentativa.
     *
     * No UPDATE apenas as colunas presentes em $data sao alteradas: assim a
     * correcao das objetivas nao apaga o texto da discursiva nem a marcacao
     * de revisao feita pelo aluno.
     */
    public function upsert(array $data)
    {
        $existing = $this->findByTentativaEPergunta(
            (int) ($data['tentativa_id'] ?? 0),
            (int) ($data['pergunta_id'] ?? 0)
        );

        $params = $this->buildParams($data);

        if ($existing) {
            $atualizaveis = array(
                'bloco_id', 'alternativa_id', 'resposta_json', 'texto_resposta',
                'marcada_para_revisao', 'conta_para_percentual', 'tipo',
                'correta', 'pontos_obtidos', 'pergunta_snapshot_json',
            );

            $sets      = array();
            $bindings  = array('id' => (int) $existing['id']);
            foreach ($atualizaveis as $coluna) {
                if (!array_key_exists($coluna, $data)) {
                    continue;
                }
                $sets[]             = $coluna . ' = :' . $coluna;
                $bindings[$coluna]  = $params[$coluna];
            }

            if (count($sets) === 0) {
                return (int) $existing['id'];
            }

            $stmt = Database::connection()->prepare(
                'UPDATE conteudo_quiz_respostas SET ' . implode(', ', $sets) . ', updated_at = NOW()
                 WHERE id = :id AND deleted_at IS NULL'
            );
            $stmt->execute($bindings);
            return (int) $existing['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_quiz_respostas
             (tentativa_id, pergunta_id, bloco_id, tipo, alternativa_id, resposta_json,
              texto_resposta, marcada_para_revisao, conta_para_percentual, correta,
              pontos_obtidos, pergunta_snapshot_json, created_at, updated_at)
             VALUES
             (:tentativa_id, :pergunta_id, :bloco_id, :tipo, :alternativa_id, :resposta_json,
              :texto_resposta, :marcada_para_revisao, :conta_para_percentual, :correta,
              :pontos_obtidos, :pergunta_snapshot_json, NOW(), NOW())'
        );
        $stmt->execute($params);
        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Marca/desmarca a questao para revisao sem alterar a resposta.
     */
    public function marcarRevisao($tentativaId, $perguntaId, $marcada)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_respostas
             SET marcada_para_revisao = :marcada, updated_at = NOW()
             WHERE tentativa_id = :tentativa_id AND pergunta_id = :pergunta_id AND deleted_at IS NULL'
        );
        $stmt->execute(array(
            'tentativa_id' => (int) $tentativaId,
            'pergunta_id'  => (int) $perguntaId,
            'marcada'      => !empty($marcada) ? 1 : 0,
        ));
        return $stmt->rowCount();
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

        $textoResposta = isset($data['texto_resposta']) ? $data['texto_resposta'] : null;
        if (is_string($textoResposta) && trim($textoResposta) === '') {
            $textoResposta = null;
        }

        return array(
            'tentativa_id'          => (int) ($data['tentativa_id'] ?? 0),
            'pergunta_id'           => (int) ($data['pergunta_id'] ?? 0),
            'bloco_id'              => !empty($data['bloco_id']) ? (int) $data['bloco_id'] : null,
            'tipo'                  => isset($data['tipo']) && $data['tipo'] !== '' ? (string) $data['tipo'] : 'multipla_escolha',
            'alternativa_id'        => isset($data['alternativa_id']) && $data['alternativa_id'] ? (int) $data['alternativa_id'] : null,
            'resposta_json'         => $respostaJson,
            'texto_resposta'        => $textoResposta,
            'marcada_para_revisao'  => !empty($data['marcada_para_revisao']) ? 1 : 0,
            'conta_para_percentual' => isset($data['conta_para_percentual']) ? (int) (bool) $data['conta_para_percentual'] : 1,
            'correta'               => !empty($data['correta']) ? 1 : 0,
            'pontos_obtidos'        => (float) ($data['pontos_obtidos'] ?? 0),
            'pergunta_snapshot_json'=> $perguntaSnapshot,
        );
    }
}

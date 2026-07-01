<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoQuiz
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quizzes WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByItemId($itemId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quizzes WHERE item_id = :item_id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('item_id' => (int) $itemId));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_quizzes
             (item_id, instrucoes, tentativas_maximas, percentual_minimo,
              exige_aprovacao, exibir_resultado_apos_envio, exibir_gabarito_apos_envio,
              exibir_comentarios_apos_envio, embaralhar_perguntas, embaralhar_alternativas,
              created_at, updated_at)
             VALUES
             (:item_id, :instrucoes, :tentativas_maximas, :percentual_minimo,
              :exige_aprovacao, :exibir_resultado_apos_envio, :exibir_gabarito_apos_envio,
              :exibir_comentarios_apos_envio, :embaralhar_perguntas, :embaralhar_alternativas,
              NOW(), NOW())'
        );
        $stmt->execute($this->buildParams($data));
        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quizzes SET
              instrucoes = :instrucoes,
              tentativas_maximas = :tentativas_maximas,
              percentual_minimo = :percentual_minimo,
              exige_aprovacao = :exige_aprovacao,
              exibir_resultado_apos_envio = :exibir_resultado_apos_envio,
              exibir_gabarito_apos_envio = :exibir_gabarito_apos_envio,
              exibir_comentarios_apos_envio = :exibir_comentarios_apos_envio,
              embaralhar_perguntas = :embaralhar_perguntas,
              embaralhar_alternativas = :embaralhar_alternativas,
              updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $params = $this->buildParams($data);
        $params['id'] = (int) $id;
        unset($params['item_id']);
        $stmt->execute($params);
    }

    public function upsertByItemId($itemId, array $data)
    {
        $existing = $this->findByItemId($itemId);
        $data['item_id'] = (int) $itemId;
        if ($existing) {
            $this->update($data, (int) $existing['id']);
            return (int) $existing['id'];
        }
        return $this->create($data);
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quizzes SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(array('id' => (int) $id));
    }

    private function buildParams(array $data)
    {
        return array(
            'item_id'                       => (int) ($data['item_id'] ?? 0),
            'instrucoes'                    => isset($data['instrucoes']) && $data['instrucoes'] !== '' ? (string) $data['instrucoes'] : null,
            'tentativas_maximas'            => isset($data['tentativas_maximas']) && $data['tentativas_maximas'] !== '' && $data['tentativas_maximas'] !== null ? (int) $data['tentativas_maximas'] : null,
            'percentual_minimo'             => isset($data['percentual_minimo']) ? (float) $data['percentual_minimo'] : 0.00,
            'exige_aprovacao'               => !empty($data['exige_aprovacao']) ? 1 : 0,
            'exibir_resultado_apos_envio'   => isset($data['exibir_resultado_apos_envio']) ? (int) (bool) $data['exibir_resultado_apos_envio'] : 1,
            'exibir_gabarito_apos_envio'    => isset($data['exibir_gabarito_apos_envio']) ? (int) (bool) $data['exibir_gabarito_apos_envio'] : 1,
            'exibir_comentarios_apos_envio' => isset($data['exibir_comentarios_apos_envio']) ? (int) (bool) $data['exibir_comentarios_apos_envio'] : 1,
            'embaralhar_perguntas'          => !empty($data['embaralhar_perguntas']) ? 1 : 0,
            'embaralhar_alternativas'       => !empty($data['embaralhar_alternativas']) ? 1 : 0,
        );
    }
}

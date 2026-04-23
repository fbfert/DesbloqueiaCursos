<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AvaliacaoPergunta
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare('SELECT * FROM avaliacao_perguntas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForAvaliacao($avaliacaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM avaliacao_perguntas
             WHERE avaliacao_id = :avaliacao_id
               AND deleted_at IS NULL
             ORDER BY ordem ASC, id ASC'
        );
        $stmt->execute(array('avaliacao_id' => $avaliacaoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO avaliacao_perguntas
             (avaliacao_id, enunciado, tipo_resposta, opcoes_json, obrigatoria, ordem, created_at, updated_at, deleted_at)
             VALUES
             (:avaliacao_id, :enunciado, :tipo_resposta, :opcoes_json, :obrigatoria, :ordem, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'avaliacao_id' => $data['avaliacao_id'],
            'enunciado' => $data['enunciado'],
            'tipo_resposta' => isset($data['tipo_resposta']) ? $data['tipo_resposta'] : 'dissertativa',
            'opcoes_json' => isset($data['opcoes_json']) ? $data['opcoes_json'] : null,
            'obrigatoria' => !empty($data['obrigatoria']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE avaliacao_perguntas
             SET avaliacao_id = :avaliacao_id,
                 enunciado = :enunciado,
                 tipo_resposta = :tipo_resposta,
                 opcoes_json = :opcoes_json,
                 obrigatoria = :obrigatoria,
                 ordem = :ordem,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'avaliacao_id' => $data['avaliacao_id'],
            'enunciado' => $data['enunciado'],
            'tipo_resposta' => isset($data['tipo_resposta']) ? $data['tipo_resposta'] : 'dissertativa',
            'opcoes_json' => isset($data['opcoes_json']) ? $data['opcoes_json'] : null,
            'obrigatoria' => !empty($data['obrigatoria']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
            'id' => $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare('UPDATE avaliacao_perguntas SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id));
    }
}

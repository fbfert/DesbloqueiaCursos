<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoQuizAlternativa
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_alternativas WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listForPergunta($perguntaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_alternativas
             WHERE pergunta_id = :pergunta_id AND deleted_at IS NULL
             ORDER BY ordem ASC, id ASC'
        );
        $stmt->execute(array('pergunta_id' => (int) $perguntaId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countCorretas($perguntaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM conteudo_quiz_alternativas
             WHERE pergunta_id = :pergunta_id AND correta = 1 AND deleted_at IS NULL'
        );
        $stmt->execute(array('pergunta_id' => (int) $perguntaId));
        return (int) $stmt->fetchColumn();
    }

    public function countForPergunta($perguntaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM conteudo_quiz_alternativas
             WHERE pergunta_id = :pergunta_id AND deleted_at IS NULL'
        );
        $stmt->execute(array('pergunta_id' => (int) $perguntaId));
        return (int) $stmt->fetchColumn();
    }

    public function findByPerguntaAndId($perguntaId, $id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_alternativas
             WHERE id = :id AND pergunta_id = :pergunta_id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id, 'pergunta_id' => (int) $perguntaId));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function nextOrderForPergunta($perguntaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(MAX(ordem), 0) + 1 FROM conteudo_quiz_alternativas
             WHERE pergunta_id = :pergunta_id AND deleted_at IS NULL'
        );
        $stmt->execute(array('pergunta_id' => (int) $perguntaId));
        return (int) $stmt->fetchColumn();
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_quiz_alternativas
             (pergunta_id, texto, correta, ordem, created_at, updated_at)
             VALUES
             (:pergunta_id, :texto, :correta, :ordem, NOW(), NOW())'
        );
        $stmt->execute($this->buildParams($data));
        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_alternativas SET
              texto = :texto,
              correta = :correta,
              ordem = :ordem,
              updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $params = $this->buildParams($data);
        unset($params['pergunta_id']);
        $params['id'] = (int) $id;
        $stmt->execute($params);
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_alternativas SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(array('id' => (int) $id));
    }

    public function updateOrdem($id, $ordem)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_alternativas SET ordem = :ordem, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(array('id' => (int) $id, 'ordem' => (int) $ordem));
    }

    public function clearCorretas($perguntaId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_alternativas SET correta = 0, updated_at = NOW()
             WHERE pergunta_id = :pergunta_id AND deleted_at IS NULL'
        );
        $stmt->execute(array('pergunta_id' => (int) $perguntaId));
    }

    private function buildParams(array $data)
    {
        return array(
            'pergunta_id' => (int) ($data['pergunta_id'] ?? 0),
            'texto'       => (string) ($data['texto'] ?? ''),
            'correta'     => !empty($data['correta']) ? 1 : 0,
            'ordem'       => isset($data['ordem']) ? (int) $data['ordem'] : 0,
        );
    }
}

<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoQuizPergunta
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_perguntas WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listForQuiz($quizId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_perguntas
             WHERE quiz_id = :quiz_id AND deleted_at IS NULL
             ORDER BY ordem ASC, id ASC'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countForQuiz($quizId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM conteudo_quiz_perguntas
             WHERE quiz_id = :quiz_id AND deleted_at IS NULL'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId));
        return (int) $stmt->fetchColumn();
    }

    public function nextOrderForQuiz($quizId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(MAX(ordem), 0) + 1 FROM conteudo_quiz_perguntas
             WHERE quiz_id = :quiz_id AND deleted_at IS NULL'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId));
        return (int) $stmt->fetchColumn();
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_quiz_perguntas
             (quiz_id, enunciado, tipo, explicacao, peso, obrigatoria, ordem, created_at, updated_at)
             VALUES
             (:quiz_id, :enunciado, :tipo, :explicacao, :peso, :obrigatoria, :ordem, NOW(), NOW())'
        );
        $stmt->execute($this->buildParams($data));
        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_perguntas SET
              enunciado = :enunciado,
              tipo = :tipo,
              explicacao = :explicacao,
              peso = :peso,
              obrigatoria = :obrigatoria,
              ordem = :ordem,
              updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $params = $this->buildParams($data);
        unset($params['quiz_id']);
        $params['id'] = (int) $id;
        $stmt->execute($params);
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_perguntas SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(array('id' => (int) $id));
    }

    public function updateOrdem($id, $ordem)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_perguntas SET ordem = :ordem, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(array('id' => (int) $id, 'ordem' => (int) $ordem));
    }

    private function buildParams(array $data)
    {
        return array(
            'quiz_id'    => (int) ($data['quiz_id'] ?? 0),
            'enunciado'  => (string) ($data['enunciado'] ?? ''),
            'tipo'       => isset($data['tipo']) && $data['tipo'] !== '' ? (string) $data['tipo'] : 'multipla_escolha',
            'explicacao' => isset($data['explicacao']) && $data['explicacao'] !== '' ? (string) $data['explicacao'] : null,
            'peso'       => isset($data['peso']) && $data['peso'] !== '' ? (float) $data['peso'] : 1.00,
            'obrigatoria'=> !empty($data['obrigatoria']) ? 1 : 0,
            'ordem'      => isset($data['ordem']) ? (int) $data['ordem'] : 0,
        );
    }
}

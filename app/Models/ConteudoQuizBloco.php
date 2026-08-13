<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoQuizBloco
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_blocos WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByCodigo($quizId, $codigo)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_blocos
             WHERE quiz_id = :quiz_id AND codigo = :codigo AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId, 'codigo' => (string) $codigo));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listForQuiz($quizId, $apenasAtivos = false)
    {
        $sql = 'SELECT * FROM conteudo_quiz_blocos
                WHERE quiz_id = :quiz_id AND deleted_at IS NULL';
        if ($apenasAtivos) {
            $sql .= ' AND status = \'ativo\'';
        }
        $sql .= ' ORDER BY ordem ASC, id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array('quiz_id' => (int) $quizId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countForQuiz($quizId, $apenasAtivos = false)
    {
        $sql = 'SELECT COUNT(*) FROM conteudo_quiz_blocos
                WHERE quiz_id = :quiz_id AND deleted_at IS NULL';
        if ($apenasAtivos) {
            $sql .= ' AND status = \'ativo\'';
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array('quiz_id' => (int) $quizId));
        return (int) $stmt->fetchColumn();
    }

    public function nextOrderForQuiz($quizId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(MAX(ordem), 0) + 1 FROM conteudo_quiz_blocos
             WHERE quiz_id = :quiz_id AND deleted_at IS NULL'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId));
        return (int) $stmt->fetchColumn();
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_quiz_blocos
             (quiz_id, codigo, titulo, descricao, tipo_questao, quantidade_sortear,
              distribuicao_dificuldade_json, conta_para_percentual, obrigatorio_para_envio,
              ordem, status, created_at, updated_at)
             VALUES
             (:quiz_id, :codigo, :titulo, :descricao, :tipo_questao, :quantidade_sortear,
              :distribuicao_dificuldade_json, :conta_para_percentual, :obrigatorio_para_envio,
              :ordem, :status, NOW(), NOW())'
        );
        $stmt->execute($this->buildParams($data));
        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_blocos SET
              codigo = :codigo,
              titulo = :titulo,
              descricao = :descricao,
              tipo_questao = :tipo_questao,
              quantidade_sortear = :quantidade_sortear,
              distribuicao_dificuldade_json = :distribuicao_dificuldade_json,
              conta_para_percentual = :conta_para_percentual,
              obrigatorio_para_envio = :obrigatorio_para_envio,
              ordem = :ordem,
              status = :status,
              updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $params = $this->buildParams($data);
        unset($params['quiz_id']);
        $params['id'] = (int) $id;
        $stmt->execute($params);
    }

    public function updateOrdem($id, $ordem)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_blocos SET ordem = :ordem, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(array('id' => (int) $id, 'ordem' => (int) $ordem));
    }

    public function updateStatus($id, $status)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_blocos SET status = :status, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(array('id' => (int) $id, 'status' => (string) $status));
    }

    /**
     * Exclusao logica. O codigo recebe um sufixo para liberar a chave unica
     * (quiz_id, codigo) e permitir recriar um bloco com o mesmo codigo.
     */
    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_blocos
             SET codigo = CONCAT(LEFT(codigo, 40), \'_excluido_\', id),
                 deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(array('id' => (int) $id));
    }

    private function buildParams(array $data)
    {
        $distribuicao = isset($data['distribuicao_dificuldade_json']) ? $data['distribuicao_dificuldade_json'] : null;
        if (is_array($distribuicao)) {
            $distribuicao = count($distribuicao) > 0 ? json_encode($distribuicao, JSON_UNESCAPED_UNICODE) : null;
        }
        if (is_string($distribuicao) && trim($distribuicao) === '') {
            $distribuicao = null;
        }

        return array(
            'quiz_id'                       => (int) ($data['quiz_id'] ?? 0),
            'codigo'                        => (string) ($data['codigo'] ?? ''),
            'titulo'                        => (string) ($data['titulo'] ?? ''),
            'descricao'                     => isset($data['descricao']) && $data['descricao'] !== '' ? (string) $data['descricao'] : null,
            'tipo_questao'                  => isset($data['tipo_questao']) && $data['tipo_questao'] !== '' ? (string) $data['tipo_questao'] : 'multipla_escolha',
            'quantidade_sortear'            => (int) ($data['quantidade_sortear'] ?? 0),
            'distribuicao_dificuldade_json' => $distribuicao,
            'conta_para_percentual'         => isset($data['conta_para_percentual']) ? (int) (bool) $data['conta_para_percentual'] : 1,
            'obrigatorio_para_envio'        => isset($data['obrigatorio_para_envio']) ? (int) (bool) $data['obrigatorio_para_envio'] : 1,
            'ordem'                         => (int) ($data['ordem'] ?? 0),
            'status'                        => isset($data['status']) && $data['status'] !== '' ? (string) $data['status'] : 'ativo',
        );
    }
}

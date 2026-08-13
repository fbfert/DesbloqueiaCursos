<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoQuizCorrecaoDiscursiva
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_correcoes_discursivas
             WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByTentativaEPergunta($tentativaId, $perguntaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_correcoes_discursivas
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
            'SELECT * FROM conteudo_quiz_correcoes_discursivas
             WHERE tentativa_id = :tentativa_id AND deleted_at IS NULL
             ORDER BY id ASC'
        );
        $stmt->execute(array('tentativa_id' => (int) $tentativaId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPendentesPorQuiz($quizId, $cursoId = null, $turmaId = null)
    {
        $sql = 'SELECT COUNT(*)
                FROM conteudo_quiz_correcoes_discursivas c
                INNER JOIN conteudo_quiz_tentativas t ON t.id = c.tentativa_id
                WHERE t.quiz_id = :quiz_id
                  AND c.status = \'pendente\'
                  AND c.deleted_at IS NULL
                  AND t.deleted_at IS NULL';
        $params = array('quiz_id' => (int) $quizId);
        if ($cursoId !== null && (int) $cursoId > 0) {
            $sql .= ' AND t.curso_evento_id = :curso_id';
            $params['curso_id'] = (int) $cursoId;
        }
        if ($turmaId !== null && (int) $turmaId > 0) {
            $sql .= ' AND t.turma_id = :turma_id';
            $params['turma_id'] = (int) $turmaId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Fila de correcao das discursivas de um quiz.
     *
     * @param array $filtros ['status' => 'pendente'|'corrigida', 'turma_id' => int, 'aluno_id' => int]
     */
    public function listFila($quizId, $cursoId, array $filtros = array())
    {
        $sql = 'SELECT c.*,
                       t.aluno_id, t.inscricao_id, t.turma_id, t.numero_tentativa,
                       t.enviada_em, t.curso_evento_id,
                       u.nome AS aluno_nome, u.email AS aluno_email,
                       p.enunciado AS pergunta_enunciado,
                       r.texto_resposta AS resposta_texto,
                       corr.nome AS corretor_nome
                FROM conteudo_quiz_correcoes_discursivas c
                INNER JOIN conteudo_quiz_tentativas t ON t.id = c.tentativa_id
                INNER JOIN usuarios u ON u.id = t.aluno_id
                INNER JOIN conteudo_quiz_perguntas p ON p.id = c.pergunta_id
                LEFT JOIN conteudo_quiz_respostas r ON r.id = c.resposta_id
                LEFT JOIN usuarios corr ON corr.id = c.corretor_id
                WHERE t.quiz_id = :quiz_id
                  AND t.curso_evento_id = :curso_id
                  AND c.deleted_at IS NULL
                  AND t.deleted_at IS NULL';
        $params = array('quiz_id' => (int) $quizId, 'curso_id' => (int) $cursoId);

        if (!empty($filtros['status'])) {
            $sql .= ' AND c.status = :status';
            $params['status'] = (string) $filtros['status'];
        }
        if (!empty($filtros['turma_id'])) {
            $sql .= ' AND t.turma_id = :turma_id';
            $params['turma_id'] = (int) $filtros['turma_id'];
        }
        if (!empty($filtros['aluno_id'])) {
            $sql .= ' AND t.aluno_id = :aluno_id';
            $params['aluno_id'] = (int) $filtros['aluno_id'];
        }

        $sql .= ' ORDER BY (c.status = \'pendente\') DESC, t.enviada_em ASC, c.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_quiz_correcoes_discursivas
             (tentativa_id, resposta_id, pergunta_id, bloco_id, status, nota, nota_maxima,
              rubrica, feedback, origem, corretor_id, corretor_referencia, corrigida_em,
              created_at, updated_at)
             VALUES
             (:tentativa_id, :resposta_id, :pergunta_id, :bloco_id, :status, :nota, :nota_maxima,
              :rubrica, :feedback, :origem, :corretor_id, :corretor_referencia, :corrigida_em,
              NOW(), NOW())'
        );
        $stmt->execute($this->buildParams($data));
        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_correcoes_discursivas SET
              resposta_id = :resposta_id,
              bloco_id = :bloco_id,
              status = :status,
              nota = :nota,
              nota_maxima = :nota_maxima,
              rubrica = :rubrica,
              feedback = :feedback,
              origem = :origem,
              corretor_id = :corretor_id,
              corretor_referencia = :corretor_referencia,
              corrigida_em = :corrigida_em,
              updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $params = $this->buildParams($data);
        unset($params['tentativa_id'], $params['pergunta_id']);
        $params['id'] = (int) $id;
        $stmt->execute($params);
    }

    /**
     * Cria a correcao pendente se ainda nao existir para a tentativa/pergunta.
     */
    public function garantirPendente(array $data)
    {
        $existente = $this->findByTentativaEPergunta(
            (int) ($data['tentativa_id'] ?? 0),
            (int) ($data['pergunta_id'] ?? 0)
        );
        if ($existente) {
            return (int) $existente['id'];
        }
        $data['status'] = 'pendente';
        return $this->create($data);
    }

    public function registrarHistorico(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_quiz_correcoes_discursivas_historico
             (correcao_id, nota_anterior, nota_nova, status_anterior, status_novo,
              rubrica_anterior, rubrica_nova, feedback_anterior, feedback_novo,
              origem, usuario_id, created_at)
             VALUES
             (:correcao_id, :nota_anterior, :nota_nova, :status_anterior, :status_novo,
              :rubrica_anterior, :rubrica_nova, :feedback_anterior, :feedback_novo,
              :origem, :usuario_id, NOW())'
        );
        $stmt->execute(array(
            'correcao_id'       => (int) ($data['correcao_id'] ?? 0),
            'nota_anterior'     => isset($data['nota_anterior']) && $data['nota_anterior'] !== null ? (float) $data['nota_anterior'] : null,
            'nota_nova'         => isset($data['nota_nova']) && $data['nota_nova'] !== null ? (float) $data['nota_nova'] : null,
            'status_anterior'   => isset($data['status_anterior']) ? (string) $data['status_anterior'] : null,
            'status_novo'       => isset($data['status_novo']) ? (string) $data['status_novo'] : null,
            'rubrica_anterior'  => isset($data['rubrica_anterior']) ? $data['rubrica_anterior'] : null,
            'rubrica_nova'      => isset($data['rubrica_nova']) ? $data['rubrica_nova'] : null,
            'feedback_anterior' => isset($data['feedback_anterior']) ? $data['feedback_anterior'] : null,
            'feedback_novo'     => isset($data['feedback_novo']) ? $data['feedback_novo'] : null,
            'origem'            => isset($data['origem']) && $data['origem'] !== '' ? (string) $data['origem'] : 'manual',
            'usuario_id'        => !empty($data['usuario_id']) ? (int) $data['usuario_id'] : null,
        ));
        return (int) Database::connection()->lastInsertId();
    }

    public function listHistorico($correcaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT h.*, u.nome AS usuario_nome
             FROM conteudo_quiz_correcoes_discursivas_historico h
             LEFT JOIN usuarios u ON u.id = h.usuario_id
             WHERE h.correcao_id = :correcao_id
             ORDER BY h.id DESC'
        );
        $stmt->execute(array('correcao_id' => (int) $correcaoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildParams(array $data)
    {
        return array(
            'tentativa_id'        => (int) ($data['tentativa_id'] ?? 0),
            'resposta_id'         => !empty($data['resposta_id']) ? (int) $data['resposta_id'] : null,
            'pergunta_id'         => (int) ($data['pergunta_id'] ?? 0),
            'bloco_id'            => !empty($data['bloco_id']) ? (int) $data['bloco_id'] : null,
            'status'              => isset($data['status']) && $data['status'] !== '' ? (string) $data['status'] : 'pendente',
            'nota'                => isset($data['nota']) && $data['nota'] !== null && $data['nota'] !== '' ? (float) $data['nota'] : null,
            'nota_maxima'         => isset($data['nota_maxima']) && $data['nota_maxima'] !== null && $data['nota_maxima'] !== '' ? (float) $data['nota_maxima'] : 10.00,
            'rubrica'             => isset($data['rubrica']) && $data['rubrica'] !== '' ? (string) $data['rubrica'] : null,
            'feedback'            => isset($data['feedback']) && $data['feedback'] !== '' ? (string) $data['feedback'] : null,
            'origem'              => isset($data['origem']) && $data['origem'] !== '' ? (string) $data['origem'] : 'manual',
            'corretor_id'         => !empty($data['corretor_id']) ? (int) $data['corretor_id'] : null,
            'corretor_referencia' => isset($data['corretor_referencia']) && $data['corretor_referencia'] !== '' ? (string) $data['corretor_referencia'] : null,
            'corrigida_em'        => isset($data['corrigida_em']) && $data['corrigida_em'] !== '' ? $data['corrigida_em'] : null,
        );
    }
}

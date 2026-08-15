<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoQuizTentativa
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_tentativas WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findEmAndamento($quizId, $inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_tentativas
             WHERE quiz_id = :quiz_id AND inscricao_id = :inscricao_id
               AND status = \'em_andamento\' AND deleted_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId, 'inscricao_id' => (int) $inscricaoId));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findLatest($quizId, $inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_tentativas
             WHERE quiz_id = :quiz_id AND inscricao_id = :inscricao_id
               AND deleted_at IS NULL
             ORDER BY numero_tentativa DESC, id DESC LIMIT 1'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId, 'inscricao_id' => (int) $inscricaoId));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function countForInscricao($quizId, $inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM conteudo_quiz_tentativas
             WHERE quiz_id = :quiz_id AND inscricao_id = :inscricao_id
               AND status != \'cancelada\' AND deleted_at IS NULL'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId, 'inscricao_id' => (int) $inscricaoId));
        return (int) $stmt->fetchColumn();
    }

    public function nextNumeroTentativa($quizId, $inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(MAX(numero_tentativa), 0) + 1 FROM conteudo_quiz_tentativas
             WHERE quiz_id = :quiz_id AND inscricao_id = :inscricao_id AND deleted_at IS NULL'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId, 'inscricao_id' => (int) $inscricaoId));
        return (int) $stmt->fetchColumn();
    }

    public function listForInscricao($quizId, $inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_tentativas
             WHERE quiz_id = :quiz_id AND inscricao_id = :inscricao_id
               AND deleted_at IS NULL
             ORDER BY numero_tentativa DESC, id DESC'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId, 'inscricao_id' => (int) $inscricaoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_quiz_tentativas
             (quiz_id, curso_evento_id, turma_id, inscricao_id, aluno_id,
              numero_tentativa, status, total_perguntas, total_objetivas, total_discursivas,
              total_acertos, pontos_obtidos, pontos_totais, percentual, aprovado,
              discursiva_status, sorteio_com_repeticao, sorteio_auditoria_json,
              quiz_snapshot_json, duracao_minutos, expira_em, encerrada_por_tempo,
              tempo_utilizado_segundos, iniciada_em, ultima_atividade_em, enviada_em, corrigida_em,
              created_at, updated_at)
             VALUES
             (:quiz_id, :curso_evento_id, :turma_id, :inscricao_id, :aluno_id,
              :numero_tentativa, :status, :total_perguntas, :total_objetivas, :total_discursivas,
              :total_acertos, :pontos_obtidos, :pontos_totais, :percentual, :aprovado,
              :discursiva_status, :sorteio_com_repeticao, :sorteio_auditoria_json,
              :quiz_snapshot_json, :duracao_minutos, :expira_em, :encerrada_por_tempo,
              :tempo_utilizado_segundos, :iniciada_em, :ultima_atividade_em, :enviada_em, :corrigida_em,
              NOW(), NOW())'
        );
        $stmt->execute($this->buildParams($data));
        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_tentativas SET
              status = :status,
              total_perguntas = :total_perguntas,
              total_objetivas = :total_objetivas,
              total_discursivas = :total_discursivas,
              total_acertos = :total_acertos,
              pontos_obtidos = :pontos_obtidos,
              pontos_totais = :pontos_totais,
              percentual = :percentual,
              aprovado = :aprovado,
              discursiva_status = :discursiva_status,
              quiz_snapshot_json = :quiz_snapshot_json,
              encerrada_por_tempo = :encerrada_por_tempo,
              tempo_utilizado_segundos = :tempo_utilizado_segundos,
              ultima_atividade_em = :ultima_atividade_em,
              enviada_em = :enviada_em,
              corrigida_em = :corrigida_em,
              updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $params = $this->buildParams($data);
        unset($params['quiz_id'], $params['curso_evento_id'], $params['turma_id'],
              $params['inscricao_id'], $params['aluno_id'], $params['numero_tentativa'],
              $params['iniciada_em'], $params['duracao_minutos'], $params['expira_em'],
              $params['sorteio_com_repeticao'], $params['sorteio_auditoria_json']);
        $params['id'] = (int) $id;
        $stmt->execute($params);
    }

    /**
     * Marca atividade do aluno na tentativa (usada pelos rascunhos).
     */
    public function tocarAtividade($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_tentativas SET ultima_atividade_em = NOW(), updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(array('id' => (int) $id));
    }

    public function atualizarStatusDiscursiva($id, $status)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_tentativas SET discursiva_status = :status, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(array('id' => (int) $id, 'status' => (string) $status));
    }

    /**
     * Melhor tentativa (maior percentual objetivo) de uma inscricao.
     */
    public function findMelhorTentativa($quizId, $inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_tentativas
             WHERE quiz_id = :quiz_id AND inscricao_id = :inscricao_id
               AND status IN (\'enviada\', \'corrigida\') AND deleted_at IS NULL
             ORDER BY aprovado DESC, percentual DESC, numero_tentativa ASC
             LIMIT 1'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId, 'inscricao_id' => (int) $inscricaoId));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Existe alguma tentativa aprovada para a inscricao?
     */
    public function existeAprovada($quizId, $inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM conteudo_quiz_tentativas
             WHERE quiz_id = :quiz_id AND inscricao_id = :inscricao_id
               AND aprovado = 1 AND deleted_at IS NULL'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId, 'inscricao_id' => (int) $inscricaoId));
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Tentativas em andamento cujo prazo ja venceu.
     *
     * O corte usa o relogio do PHP, e nao NOW(), porque `expira_em` foi
     * gravado pelo PHP: neste servidor o PHP roda em UTC e o MySQL em horario
     * local, tres horas atras. Comparar com NOW() fazia o cron so enxergar a
     * tentativa vencida tres horas depois do prazo real, atrasando na mesma
     * medida o envio automatico de quem fechou o navegador.
     */
    public function listExpiradas($limite = 50)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_tentativas
             WHERE status = \'em_andamento\'
               AND expira_em IS NOT NULL
               AND expira_em <= :agora
               AND deleted_at IS NULL
             ORDER BY expira_em ASC
             LIMIT ' . max(1, (int) $limite)
        );
        $stmt->execute(array('agora' => date('Y-m-d H:i:s')));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listForRelatorio($quizId, $cursoId, $turmaId = null, array $filtros = array())
    {
        $sql = 'SELECT t.*, u.nome AS aluno_nome, u.email AS aluno_email,
                       u.cpf AS aluno_cpf
                FROM conteudo_quiz_tentativas t
                INNER JOIN usuarios u ON u.id = t.aluno_id
                WHERE t.quiz_id = :quiz_id
                  AND t.curso_evento_id = :curso_evento_id
                  AND t.deleted_at IS NULL';
        $params = array('quiz_id' => (int) $quizId, 'curso_evento_id' => (int) $cursoId);

        if ($turmaId !== null && (int) $turmaId > 0) {
            $sql .= ' AND t.turma_id = :turma_id';
            $params['turma_id'] = (int) $turmaId;
        }
        if (!empty($filtros['aluno_id'])) {
            $sql .= ' AND t.aluno_id = :aluno_id';
            $params['aluno_id'] = (int) $filtros['aluno_id'];
        }
        if (!empty($filtros['status'])) {
            $sql .= ' AND t.status = :status';
            $params['status'] = (string) $filtros['status'];
        }
        if (isset($filtros['aprovado']) && $filtros['aprovado'] !== '') {
            $sql .= ' AND t.aprovado = :aprovado';
            $params['aprovado'] = (int) $filtros['aprovado'];
        }

        $sql .= ' ORDER BY t.numero_tentativa DESC, t.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildParams(array $data)
    {
        $snapshot = isset($data['quiz_snapshot_json']) ? $data['quiz_snapshot_json'] : null;
        if (is_array($snapshot)) {
            $snapshot = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        }

        $auditoria = isset($data['sorteio_auditoria_json']) ? $data['sorteio_auditoria_json'] : null;
        if (is_array($auditoria)) {
            $auditoria = count($auditoria) > 0 ? json_encode($auditoria, JSON_UNESCAPED_UNICODE) : null;
        }

        return array(
            'quiz_id'            => (int) ($data['quiz_id'] ?? 0),
            'curso_evento_id'    => (int) ($data['curso_evento_id'] ?? 0),
            'turma_id'           => isset($data['turma_id']) && $data['turma_id'] ? (int) $data['turma_id'] : null,
            'inscricao_id'       => (int) ($data['inscricao_id'] ?? 0),
            'aluno_id'           => (int) ($data['aluno_id'] ?? 0),
            'numero_tentativa'   => (int) ($data['numero_tentativa'] ?? 1),
            'status'             => (string) ($data['status'] ?? 'em_andamento'),
            'total_perguntas'    => (int) ($data['total_perguntas'] ?? 0),
            'total_acertos'      => (int) ($data['total_acertos'] ?? 0),
            'pontos_obtidos'     => (float) ($data['pontos_obtidos'] ?? 0),
            'pontos_totais'      => (float) ($data['pontos_totais'] ?? 0),
            'percentual'         => (float) ($data['percentual'] ?? 0),
            'aprovado'           => isset($data['aprovado']) && $data['aprovado'] !== null ? (int) (bool) $data['aprovado'] : null,
            'quiz_snapshot_json' => $snapshot,
            'total_objetivas'    => (int) ($data['total_objetivas'] ?? 0),
            'total_discursivas'  => (int) ($data['total_discursivas'] ?? 0),
            'discursiva_status'  => isset($data['discursiva_status']) && $data['discursiva_status'] !== '' ? (string) $data['discursiva_status'] : 'nao_aplicavel',
            'sorteio_com_repeticao'    => !empty($data['sorteio_com_repeticao']) ? 1 : 0,
            'sorteio_auditoria_json'   => $auditoria,
            'duracao_minutos'          => isset($data['duracao_minutos']) && $data['duracao_minutos'] !== '' && $data['duracao_minutos'] !== null ? (int) $data['duracao_minutos'] : null,
            'expira_em'                => isset($data['expira_em']) && $data['expira_em'] !== '' ? $data['expira_em'] : null,
            'encerrada_por_tempo'      => !empty($data['encerrada_por_tempo']) ? 1 : 0,
            'tempo_utilizado_segundos' => isset($data['tempo_utilizado_segundos']) && $data['tempo_utilizado_segundos'] !== '' && $data['tempo_utilizado_segundos'] !== null ? (int) $data['tempo_utilizado_segundos'] : null,
            'iniciada_em'        => isset($data['iniciada_em']) ? $data['iniciada_em'] : null,
            'ultima_atividade_em'=> isset($data['ultima_atividade_em']) && $data['ultima_atividade_em'] !== '' ? $data['ultima_atividade_em'] : null,
            'enviada_em'         => isset($data['enviada_em']) ? $data['enviada_em'] : null,
            'corrigida_em'       => isset($data['corrigida_em']) ? $data['corrigida_em'] : null,
        );
    }
}

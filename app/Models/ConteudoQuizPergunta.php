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

    /**
     * Questoes disponiveis para sorteio em um bloco (banco de questoes).
     * Somente itens ativos, do tipo do bloco e nao excluidos.
     */
    public function listDisponiveisParaSorteio($quizId, $blocoId, $tipo = 'multipla_escolha')
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, quiz_id, bloco_id, enunciado, tipo, dificuldade, tema, status,
                    explicacao, rubrica, nota_maxima, peso, obrigatoria, ordem
             FROM conteudo_quiz_perguntas
             WHERE quiz_id = :quiz_id
               AND bloco_id = :bloco_id
               AND tipo = :tipo
               AND status = \'ativo\'
               AND deleted_at IS NULL
             ORDER BY id ASC'
        );
        $stmt->execute(array(
            'quiz_id'  => (int) $quizId,
            'bloco_id' => (int) $blocoId,
            'tipo'     => (string) $tipo,
        ));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listagem do banco de questoes com filtros do admin.
     *
     * @param array $filtros ['bloco_id'=>int|'sem_bloco', 'dificuldade'=>string,
     *                        'tema'=>string, 'tipo'=>string, 'status'=>string, 'busca'=>string]
     */
    public function listBanco($quizId, array $filtros = array())
    {
        $sql = 'SELECT p.*, b.codigo AS bloco_codigo, b.titulo AS bloco_titulo
                FROM conteudo_quiz_perguntas p
                LEFT JOIN conteudo_quiz_blocos b ON b.id = p.bloco_id AND b.deleted_at IS NULL
                WHERE p.quiz_id = :quiz_id AND p.deleted_at IS NULL';
        $params = array('quiz_id' => (int) $quizId);

        if (isset($filtros['bloco_id']) && $filtros['bloco_id'] !== '' && $filtros['bloco_id'] !== null) {
            if ((string) $filtros['bloco_id'] === 'sem_bloco') {
                $sql .= ' AND p.bloco_id IS NULL';
            } else {
                $sql .= ' AND p.bloco_id = :bloco_id';
                $params['bloco_id'] = (int) $filtros['bloco_id'];
            }
        }
        if (!empty($filtros['dificuldade'])) {
            $sql .= ' AND p.dificuldade = :dificuldade';
            $params['dificuldade'] = (string) $filtros['dificuldade'];
        }
        if (!empty($filtros['tema'])) {
            $sql .= ' AND p.tema = :tema';
            $params['tema'] = (string) $filtros['tema'];
        }
        if (!empty($filtros['tipo'])) {
            $sql .= ' AND p.tipo = :tipo';
            $params['tipo'] = (string) $filtros['tipo'];
        }
        if (!empty($filtros['status'])) {
            $sql .= ' AND p.status = :status';
            $params['status'] = (string) $filtros['status'];
        }
        if (!empty($filtros['busca'])) {
            $sql .= ' AND p.enunciado LIKE :busca';
            $params['busca'] = '%' . str_replace(array('%', '_'), array('\%', '\_'), (string) $filtros['busca']) . '%';
        }

        $sql .= ' ORDER BY b.ordem ASC, b.id ASC, p.ordem ASC, p.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contagem do banco por bloco/dificuldade, usada na validacao de publicacao.
     *
     * @return array [bloco_id => ['total' => n, 'facil' => n, 'media' => n, 'dificil' => n]]
     */
    public function resumoBancoPorBloco($quizId, $tipo = null)
    {
        $sql = 'SELECT COALESCE(bloco_id, 0) AS bloco_id, dificuldade, tipo, COUNT(*) AS total
                FROM conteudo_quiz_perguntas
                WHERE quiz_id = :quiz_id AND status = \'ativo\' AND deleted_at IS NULL';
        $params = array('quiz_id' => (int) $quizId);
        if ($tipo !== null && $tipo !== '') {
            $sql .= ' AND tipo = :tipo';
            $params['tipo'] = (string) $tipo;
        }
        $sql .= ' GROUP BY COALESCE(bloco_id, 0), dificuldade, tipo';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $resumo = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $blocoId = (int) $linha['bloco_id'];
            if (!isset($resumo[$blocoId])) {
                $resumo[$blocoId] = array('total' => 0, 'facil' => 0, 'media' => 0, 'dificil' => 0);
            }
            $quantidade = (int) $linha['total'];
            $resumo[$blocoId]['total'] += $quantidade;
            $dificuldade = (string) $linha['dificuldade'];
            if (isset($resumo[$blocoId][$dificuldade])) {
                $resumo[$blocoId][$dificuldade] += $quantidade;
            }
        }
        return $resumo;
    }

    /**
     * @return string[] Temas distintos cadastrados no banco do quiz.
     */
    public function listTemas($quizId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT tema FROM conteudo_quiz_perguntas
             WHERE quiz_id = :quiz_id AND tema IS NOT NULL AND tema <> \'\' AND deleted_at IS NULL
             ORDER BY tema ASC'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId));

        $temas = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $temas[] = (string) $linha['tema'];
        }
        return $temas;
    }

    public function countForBloco($blocoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM conteudo_quiz_perguntas
             WHERE bloco_id = :bloco_id AND deleted_at IS NULL'
        );
        $stmt->execute(array('bloco_id' => (int) $blocoId));
        return (int) $stmt->fetchColumn();
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
             (quiz_id, bloco_id, enunciado, tipo, dificuldade, tema, status, referencia,
              explicacao, rubrica, nota_maxima, peso, obrigatoria, ordem, created_at, updated_at)
             VALUES
             (:quiz_id, :bloco_id, :enunciado, :tipo, :dificuldade, :tema, :status, :referencia,
              :explicacao, :rubrica, :nota_maxima, :peso, :obrigatoria, :ordem, NOW(), NOW())'
        );
        $stmt->execute($this->buildParams($data));
        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_perguntas SET
              bloco_id = :bloco_id,
              enunciado = :enunciado,
              tipo = :tipo,
              dificuldade = :dificuldade,
              tema = :tema,
              status = :status,
              referencia = :referencia,
              explicacao = :explicacao,
              rubrica = :rubrica,
              nota_maxima = :nota_maxima,
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
            // NULL = questao legada, fora do banco por blocos.
            'bloco_id'   => !empty($data['bloco_id']) ? (int) $data['bloco_id'] : null,
            'enunciado'  => (string) ($data['enunciado'] ?? ''),
            'tipo'       => isset($data['tipo']) && $data['tipo'] !== '' ? (string) $data['tipo'] : 'multipla_escolha',
            'dificuldade'=> isset($data['dificuldade']) && $data['dificuldade'] !== '' ? (string) $data['dificuldade'] : 'media',
            'tema'       => isset($data['tema']) && $data['tema'] !== '' ? (string) $data['tema'] : null,
            'status'     => isset($data['status']) && $data['status'] !== '' ? (string) $data['status'] : 'ativo',
            'referencia' => isset($data['referencia']) && $data['referencia'] !== '' ? (string) $data['referencia'] : null,
            'explicacao' => isset($data['explicacao']) && $data['explicacao'] !== '' ? (string) $data['explicacao'] : null,
            'rubrica'    => isset($data['rubrica']) && $data['rubrica'] !== '' ? (string) $data['rubrica'] : null,
            'nota_maxima'=> isset($data['nota_maxima']) && $data['nota_maxima'] !== '' && $data['nota_maxima'] !== null ? (float) $data['nota_maxima'] : null,
            'peso'       => isset($data['peso']) && $data['peso'] !== '' ? (float) $data['peso'] : 1.00,
            'obrigatoria'=> !empty($data['obrigatoria']) ? 1 : 0,
            'ordem'      => isset($data['ordem']) ? (int) $data['ordem'] : 0,
        );
    }
}

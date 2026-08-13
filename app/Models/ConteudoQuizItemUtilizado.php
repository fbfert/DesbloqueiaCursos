<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Historico de questoes ja sorteadas para uma inscricao, usado para evitar
 * repeticao entre tentativas e para auditar reaproveitamentos.
 */
class ConteudoQuizItemUtilizado
{
    /**
     * @return int[] IDs de perguntas ja usadas pela inscricao neste quiz.
     */
    public function listPerguntaIds($quizId, $inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT pergunta_id FROM conteudo_quiz_itens_utilizados
             WHERE quiz_id = :quiz_id AND inscricao_id = :inscricao_id'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId, 'inscricao_id' => (int) $inscricaoId));

        $ids = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $ids[] = (int) $linha['pergunta_id'];
        }
        return $ids;
    }

    public function listForTentativa($tentativaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_itens_utilizados
             WHERE tentativa_id = :tentativa_id ORDER BY id ASC'
        );
        $stmt->execute(array('tentativa_id' => (int) $tentativaId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countReutilizadas($quizId, $inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM conteudo_quiz_itens_utilizados
             WHERE quiz_id = :quiz_id AND inscricao_id = :inscricao_id AND reutilizada = 1'
        );
        $stmt->execute(array('quiz_id' => (int) $quizId, 'inscricao_id' => (int) $inscricaoId));
        return (int) $stmt->fetchColumn();
    }

    /**
     * Registra em lote os itens sorteados de uma tentativa.
     *
     * @param array $itens Lista de arrays com pergunta_id, bloco_id, reutilizada.
     */
    public function registrarLote(array $contexto, array $itens)
    {
        if (count($itens) === 0) {
            return 0;
        }

        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO conteudo_quiz_itens_utilizados
             (quiz_id, inscricao_id, aluno_id, tentativa_id, numero_tentativa,
              pergunta_id, bloco_id, reutilizada, created_at)
             VALUES
             (:quiz_id, :inscricao_id, :aluno_id, :tentativa_id, :numero_tentativa,
              :pergunta_id, :bloco_id, :reutilizada, NOW())'
        );

        $gravados = 0;
        foreach ($itens as $item) {
            $perguntaId = (int) ($item['pergunta_id'] ?? 0);
            if ($perguntaId <= 0) {
                continue;
            }
            $stmt->execute(array(
                'quiz_id'          => (int) ($contexto['quiz_id'] ?? 0),
                'inscricao_id'     => (int) ($contexto['inscricao_id'] ?? 0),
                'aluno_id'         => (int) ($contexto['aluno_id'] ?? 0),
                'tentativa_id'     => (int) ($contexto['tentativa_id'] ?? 0),
                'numero_tentativa' => (int) ($contexto['numero_tentativa'] ?? 1),
                'pergunta_id'      => $perguntaId,
                'bloco_id'         => !empty($item['bloco_id']) ? (int) $item['bloco_id'] : null,
                'reutilizada'      => !empty($item['reutilizada']) ? 1 : 0,
            ));
            $gravados += $stmt->rowCount();
        }

        return $gravados;
    }
}

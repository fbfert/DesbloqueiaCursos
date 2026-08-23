<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Feedback do aluno sobre uma resposta da Norminha.
 *
 * O Model NÃO valida propriedade: quem chama precisa ter confirmado, via
 * NorminhaMensagem::buscarDoUsuario(), que a mensagem pertence a uma conversa
 * do usuário autenticado. A separação é deliberada — o Model não tem acesso à
 * sessão e não deve fingir que tem.
 *
 * O usuario_id recebido aqui vem SEMPRE de Session::get('usuario_id'), nunca
 * do corpo da requisição.
 */
class NorminhaFeedback
{
    /**
     * Grava ou atualiza o voto. O aluno pode mudar de ideia, não acumular votos:
     * o UNIQUE (mensagem_id, usuario_id) e o ON DUPLICATE KEY UPDATE garantem
     * uma linha por par, sem corrida entre duas abas.
     */
    public function registrar($mensagemId, $usuarioId, $util, $comentario = null)
    {
        $mensagemId = (int) $mensagemId;
        $usuarioId = (int) $usuarioId;
        if ($mensagemId <= 0 || $usuarioId <= 0) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO norminha_feedback
                (mensagem_id, usuario_id, util, comentario, created_at, updated_at)
             VALUES
                (:mensagem_id, :usuario_id, :util, :comentario, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                util = VALUES(util),
                comentario = VALUES(comentario),
                updated_at = NOW()'
        );

        $stmt->execute(array(
            'mensagem_id' => $mensagemId,
            'usuario_id' => $usuarioId,
            'util' => !empty($util) ? 1 : 0,
            'comentario' => $this->comentarioLimitado($comentario),
        ));

        return true;
    }

    public function buscar($mensagemId, $usuarioId)
    {
        $mensagemId = (int) $mensagemId;
        $usuarioId = (int) $usuarioId;
        if ($mensagemId <= 0 || $usuarioId <= 0) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, mensagem_id, usuario_id, util, comentario, created_at, updated_at
             FROM norminha_feedback
             WHERE mensagem_id = :mensagem_id
               AND usuario_id = :usuario_id
             LIMIT 1'
        );
        $stmt->execute(array('mensagem_id' => $mensagemId, 'usuario_id' => $usuarioId));

        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ?: null;
    }

    /** Útil x não útil no período (telemetria, Etapa 8). */
    public function resumoPorPeriodo($de, $ate)
    {
        $stmt = Database::connection()->prepare(
            'SELECT util, COUNT(*) AS total
             FROM norminha_feedback
             WHERE created_at >= :de
               AND created_at < :ate
             GROUP BY util'
        );
        $stmt->execute(array('de' => (string) $de, 'ate' => (string) $ate));

        $resumo = array('uteis' => 0, 'nao_uteis' => 0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $chave = !empty($linha['util']) ? 'uteis' : 'nao_uteis';
            $resumo[$chave] = (int) $linha['total'];
        }

        return $resumo;
    }

    private function comentarioLimitado($comentario)
    {
        if ($comentario === null) {
            return null;
        }

        $comentario = trim((string) $comentario);
        if ($comentario === '') {
            return null;
        }

        return function_exists('mb_substr') ? mb_substr($comentario, 0, 1000, 'UTF-8') : substr($comentario, 0, 1000);
    }
}

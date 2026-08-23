<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Mensagens da Norminha.
 *
 * Guarda a conversa inteira, inclusive o que foi resolvido sem IA. O campo
 * `resolved_by` é o dado que justifica (ou não) ligar a camada de IA:
 * 'unresolved' marca a pergunta que o PHP não soube responder.
 *
 * O texto do aluno é gravado ÍNTEGRO. Sanitizar no armazenamento destruiria a
 * telemetria (é preciso ler a pergunta real) e daria falsa segurança: o escape
 * é responsabilidade de quem renderiza, e a proteção contra injeção é o
 * prepared statement, não a filtragem do conteúdo.
 *
 * FUSO: datas sempre por NOW(), o relógio do banco. Ver NorminhaConversa.
 */
class NorminhaMensagem
{
    const PAPEIS = array('user', 'assistant', 'tool');
    const RESOLUCOES = array('php', 'ai', 'hybrid', 'unresolved');

    /** Insere uma mensagem e devolve o id. */
    public function inserir($conversaId, $papel, $mensagem, array $meta = array())
    {
        $conversaId = (int) $conversaId;
        $papel = (string) $papel;

        if ($conversaId <= 0 || !in_array($papel, self::PAPEIS, true)) {
            return null;
        }

        $resolvedBy = isset($meta['resolved_by']) ? (string) $meta['resolved_by'] : null;
        if ($resolvedBy !== null && !in_array($resolvedBy, self::RESOLUCOES, true)) {
            $resolvedBy = null;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO norminha_mensagens
                (conversa_id, usuario_id, papel, mensagem, intencao, resolved_by, tool_name,
                 openai_response_id, modelo_ia, input_tokens, cached_input_tokens, output_tokens,
                 latencia_ms, status, error_code, created_at)
             VALUES
                (:conversa_id, :usuario_id, :papel, :mensagem, :intencao, :resolved_by, :tool_name,
                 :openai_response_id, :modelo_ia, :input_tokens, :cached_input_tokens, :output_tokens,
                 :latencia_ms, :status, :error_code, NOW())'
        );

        $stmt->execute(array(
            'conversa_id' => $conversaId,
            'usuario_id' => $this->idOuNulo(isset($meta['usuario_id']) ? $meta['usuario_id'] : null),
            'papel' => $papel,
            'mensagem' => (string) $mensagem,
            'intencao' => $this->textoOuNulo(isset($meta['intencao']) ? $meta['intencao'] : null, 80),
            'resolved_by' => $resolvedBy,
            'tool_name' => $this->textoOuNulo(isset($meta['tool_name']) ? $meta['tool_name'] : null, 100),
            'openai_response_id' => $this->textoOuNulo(isset($meta['openai_response_id']) ? $meta['openai_response_id'] : null, 120),
            'modelo_ia' => $this->textoOuNulo(isset($meta['modelo_ia']) ? $meta['modelo_ia'] : null, 80),
            'input_tokens' => $this->inteiroOuNulo(isset($meta['input_tokens']) ? $meta['input_tokens'] : null),
            'cached_input_tokens' => $this->inteiroOuNulo(isset($meta['cached_input_tokens']) ? $meta['cached_input_tokens'] : null),
            'output_tokens' => $this->inteiroOuNulo(isset($meta['output_tokens']) ? $meta['output_tokens'] : null),
            'latencia_ms' => $this->inteiroOuNulo(isset($meta['latencia_ms']) ? $meta['latencia_ms'] : null),
            'status' => $this->textoOuNulo(isset($meta['status']) ? $meta['status'] : 'ok', 20) ?: 'ok',
            'error_code' => $this->textoOuNulo(isset($meta['error_code']) ? $meta['error_code'] : null, 80),
        ));

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Últimas N mensagens de uma conversa, em ordem cronológica.
     *
     * O JOIN com norminha_conversas filtrando por usuario_id é o que garante a
     * propriedade: um conversa_id de outra pessoa devolve lista vazia, e não
     * as mensagens dela.
     *
     * A subquery pega as N MAIS RECENTES e a query externa as reordena para
     * leitura. Sem isso, o LIMIT devolveria as N mais ANTIGAS.
     */
    public function ultimasDaConversa($conversaId, $usuarioId, $limite = 12, array $papeis = array('user', 'assistant'))
    {
        $conversaId = (int) $conversaId;
        $usuarioId = (int) $usuarioId;
        if ($conversaId <= 0 || $usuarioId <= 0) {
            return array();
        }

        $limite = max(1, min(100, (int) $limite));

        // Papéis vêm de constante do código, nunca do usuário; ainda assim são
        // filtrados contra a whitelist antes de virar placeholders.
        $papeis = array_values(array_intersect($papeis, self::PAPEIS));
        if (!$papeis) {
            return array();
        }

        $marcadores = array();
        $parametros = array('conversa_id' => $conversaId, 'usuario_id' => $usuarioId);
        foreach ($papeis as $indice => $papel) {
            $chave = 'papel_' . $indice;
            $marcadores[] = ':' . $chave;
            $parametros[$chave] = $papel;
        }

        $stmt = Database::connection()->prepare(
            'SELECT * FROM (
                 SELECT m.id, m.papel, m.mensagem, m.intencao, m.resolved_by, m.created_at
                 FROM norminha_mensagens m
                 INNER JOIN norminha_conversas c
                         ON c.id = m.conversa_id
                        AND c.usuario_id = :usuario_id
                 WHERE m.conversa_id = :conversa_id
                   AND m.papel IN (' . implode(', ', $marcadores) . ')
                   AND m.status = "ok"
                 ORDER BY m.id DESC
                 LIMIT ' . $limite . '
             ) AS ultimas
             ORDER BY ultimas.id ASC'
        );
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca uma mensagem garantindo que ela pertence a uma conversa do usuário.
     *
     * É o que a camada de serviço usa antes de aceitar feedback: sem esta
     * checagem, o aluno poderia avaliar a mensagem de outro.
     */
    public function buscarDoUsuario($mensagemId, $usuarioId)
    {
        $mensagemId = (int) $mensagemId;
        $usuarioId = (int) $usuarioId;
        if ($mensagemId <= 0 || $usuarioId <= 0) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT m.id, m.conversa_id, m.papel, m.resolved_by, m.created_at
             FROM norminha_mensagens m
             INNER JOIN norminha_conversas c
                     ON c.id = m.conversa_id
                    AND c.usuario_id = :usuario_id
             WHERE m.id = :id
             LIMIT 1'
        );
        $stmt->execute(array('id' => $mensagemId, 'usuario_id' => $usuarioId));

        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ?: null;
    }

    /**
     * Contagem por origem da resposta nos últimos N dias.
     *
     * ESTA é a forma segura de perguntar "e nos últimos 7 dias?". A janela é
     * calculada em SQL, no mesmo relógio que gravou created_at.
     *
     * Calcular a data em PHP e passá-la para contarPorResolucao() produziria o
     * dia errado toda noite: o PHP roda em UTC e o MySQL em UTC−3, então das 21h
     * à meia-noite date('Y-m-d') já devolve o dia seguinte. Silencioso, diário
     * e invisível em teste com janela larga.
     */
    public function contarPorResolucaoUltimosDias($dias = 7)
    {
        $dias = max(1, min(365, (int) $dias));

        $stmt = Database::connection()->prepare(
            'SELECT resolved_by, COUNT(*) AS total
             FROM norminha_mensagens
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $dias . ' DAY)
               AND resolved_by IS NOT NULL
             GROUP BY resolved_by'
        );
        $stmt->execute();

        $contagem = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $contagem[$linha['resolved_by']] = (int) $linha['total'];
        }

        return $contagem;
    }

    /**
     * Contagem por origem da resposta em um período explícito.
     *
     * ⚠️ $de e $ate precisam ser hora de parede LOCAL — vindos de um formulário
     * do admin ou do próprio banco. NUNCA calcule com date() do PHP: ele roda em
     * UTC e o banco em UTC−3. Para janelas relativas use
     * contarPorResolucaoUltimosDias(), que resolve tudo em SQL.
     *
     * Usa idx_norminha_mensagens_resolucao (resolved_by, created_at) e exige
     * período, para não varrer a tabela inteira.
     */
    public function contarPorResolucao($de, $ate)
    {
        $stmt = Database::connection()->prepare(
            'SELECT resolved_by, COUNT(*) AS total
             FROM norminha_mensagens
             WHERE created_at >= :de
               AND created_at < :ate
               AND resolved_by IS NOT NULL
             GROUP BY resolved_by'
        );
        $stmt->execute(array('de' => (string) $de, 'ate' => (string) $ate));

        $contagem = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $contagem[$linha['resolved_by']] = (int) $linha['total'];
        }

        return $contagem;
    }

    private function idOuNulo($valor)
    {
        $valor = (int) $valor;

        return $valor > 0 ? $valor : null;
    }

    private function inteiroOuNulo($valor)
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) $valor;
    }

    private function textoOuNulo($valor, $maximo)
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        return function_exists('mb_substr') ? mb_substr($valor, 0, $maximo, 'UTF-8') : substr($valor, 0, $maximo);
    }
}

<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Telemetria da Norminha — os números que decidem se a Onda 1 se paga.
 *
 * O QUE ESTE SERVICE RESPONDE, e por que cada um importa:
 *
 *   quanto se usa        — se ninguém abre o chat, o resto é irrelevante
 *   php x unresolved     — o tamanho do buraco que a IA preencheria
 *   quais fast-paths     — o que já resolvemos de graça
 *   AS PERGUNTAS         — a única forma de saber se o buraco é de CONTEÚDO
 *                          ou de navegação. Se for navegação, a resposta certa
 *                          é mais um fast-path, não um modelo de linguagem
 *   feedback             — se o que respondemos serve
 *   bloqueios            — se o rate limit está estorvando gente legítima
 *
 * FUSO: a janela é calculada em SQL, no mesmo relógio que gravou os dados. O
 * PHP roda em UTC e o MySQL em UTC−3, então uma data vinda de date() erraria o
 * dia inteiro toda noite — em silêncio, com o painel mostrando zero.
 * Ver docs/2026-08-15-fuso-horario-php-mysql.md.
 *
 * ÍNDICES: toda consulta filtra por período e usa os índices da migration 074.
 * Nenhuma varre a tabela inteira.
 *
 * PRIVACIDADE: o texto da pergunta é devolvido porque é o dado central desta
 * etapa — sem ler o que o aluno perguntou, não se decide nada. Mas ele é
 * CONTEÚDO NÃO CONFIÁVEL e a view precisa escapá-lo. Nome, e-mail e CPF não
 * saem daqui; o usuario_id vai junto apenas para permitir suporte pontual.
 */
class NorminhaTelemetriaService
{
    const DIAS_PADRAO = 14;
    const LIMITE_PERGUNTAS = 100;

    /** @param int $dias janela em dias, calculada em SQL */
    public function panorama($dias = self::DIAS_PADRAO)
    {
        $dias = $this->dias($dias);

        return array(
            'dias' => $dias,
            'volume' => $this->volume($dias),
            'resolucao' => $this->porResolucao($dias),
            'intencoes' => $this->topIntencoes($dias),
            'feedback' => $this->feedback($dias),
            'bloqueios' => $this->bloqueios($dias),
        );
    }

    /** Conversas, mensagens e alunos distintos no período. */
    public function volume($dias = self::DIAS_PADRAO)
    {
        $dias = $this->dias($dias);

        $conversas = $this->umValor(
            'SELECT COUNT(*) AS n FROM norminha_conversas
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $dias . ' DAY)'
        );

        $linha = $this->umaLinha(
            'SELECT COUNT(*) AS mensagens,
                    COUNT(DISTINCT usuario_id) AS alunos
             FROM norminha_mensagens
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $dias . ' DAY)
               AND papel = "user"'
        );

        $mensagens = (int) ($linha['mensagens'] ?? 0);
        $alunos = (int) ($linha['alunos'] ?? 0);

        return array(
            'conversas' => $conversas,
            'mensagens' => $mensagens,
            'alunos' => $alunos,
            // Média por aluno ativo diz mais que o total: dez alunos com uma
            // mensagem cada é adoção; um aluno com dez é curiosidade.
            'mensagens_por_aluno' => $alunos > 0 ? round($mensagens / $alunos, 1) : 0.0,
        );
    }

    /**
     * Distribuição por origem da resposta.
     *
     * Na Onda 0 só existem 'php' e 'unresolved'. A proporção de unresolved é o
     * número que justifica (ou não) ligar a IA.
     */
    public function porResolucao($dias = self::DIAS_PADRAO)
    {
        $dias = $this->dias($dias);

        $linhas = $this->varias(
            'SELECT resolved_by, COUNT(*) AS n
             FROM norminha_mensagens
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $dias . ' DAY)
               AND resolved_by IS NOT NULL
             GROUP BY resolved_by'
        );

        $contagem = array('php' => 0, 'unresolved' => 0, 'ai' => 0, 'hybrid' => 0);
        $total = 0;
        foreach ($linhas as $l) {
            $chave = (string) $l['resolved_by'];
            $contagem[$chave] = (int) $l['n'];
            $total += (int) $l['n'];
        }

        $percentuais = array();
        foreach ($contagem as $chave => $n) {
            $percentuais[$chave] = $total > 0 ? round($n * 100 / $total, 1) : 0.0;
        }

        return array('contagem' => $contagem, 'total' => $total, 'percentual' => $percentuais);
    }

    /** O que os fast-paths resolveram, do mais pedido ao menos. */
    public function topIntencoes($dias = self::DIAS_PADRAO)
    {
        $dias = $this->dias($dias);

        return $this->varias(
            'SELECT intencao, COUNT(*) AS n
             FROM norminha_mensagens
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $dias . ' DAY)
               AND resolved_by = "php"
               AND intencao IS NOT NULL
             GROUP BY intencao
             ORDER BY n DESC
             LIMIT 20'
        );
    }

    /**
     * As perguntas que o PHP não resolveu, com o contexto acadêmico.
     *
     * Esta é A entrega da etapa. Ler cinquenta destas responde a pergunta que
     * nenhum gráfico responde: o aluno está pedindo CONTEÚDO ("não entendi
     * isso") ou NAVEGAÇÃO ("como emito boleto")? Se for navegação, o certo é
     * escrever mais um fast-path — mais barato, mais rápido e sem alucinação.
     */
    public function perguntasNaoResolvidas($dias = self::DIAS_PADRAO, $limite = 50, $cursoId = null)
    {
        $dias = $this->dias($dias);
        $limite = max(1, min(self::LIMITE_PERGUNTAS, (int) $limite));

        $filtroCurso = '';
        $params = array();
        if ($cursoId !== null && (int) $cursoId > 0) {
            $filtroCurso = ' AND c.curso_evento_id = :curso_id';
            $params['curso_id'] = (int) $cursoId;
        }

        // A pergunta do aluno é a mensagem 'user' imediatamente anterior à
        // resposta 'unresolved', na mesma conversa. Subquery em vez de window
        // function, por compatibilidade com MySQL 5.7.
        return $this->varias(
            'SELECT r.id AS resposta_id,
                    r.created_at,
                    c.usuario_id,
                    c.curso_evento_id,
                    c.inscricao_id,
                    c.contexto,
                    c.rota,
                    ce.nome AS curso_nome,
                    (SELECT p.mensagem
                       FROM norminha_mensagens p
                      WHERE p.conversa_id = r.conversa_id
                        AND p.papel = "user"
                        AND p.id < r.id
                      ORDER BY p.id DESC
                      LIMIT 1) AS pergunta
             FROM norminha_mensagens r
             INNER JOIN norminha_conversas c ON c.id = r.conversa_id
             LEFT JOIN cursos_eventos ce ON ce.id = c.curso_evento_id
             WHERE r.created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $dias . ' DAY)
               AND r.resolved_by = "unresolved"' . $filtroCurso . '
             ORDER BY r.id DESC
             LIMIT ' . $limite,
            $params
        );
    }

    /** Útil x não útil, e a taxa de resposta. */
    public function feedback($dias = self::DIAS_PADRAO)
    {
        $dias = $this->dias($dias);

        $linhas = $this->varias(
            'SELECT util, COUNT(*) AS n
             FROM norminha_feedback
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $dias . ' DAY)
             GROUP BY util'
        );

        $uteis = 0;
        $naoUteis = 0;
        foreach ($linhas as $l) {
            if (!empty($l['util'])) {
                $uteis = (int) $l['n'];
            } else {
                $naoUteis = (int) $l['n'];
            }
        }
        $total = $uteis + $naoUteis;

        $respostas = $this->umValor(
            'SELECT COUNT(*) AS n FROM norminha_mensagens
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $dias . ' DAY)
               AND papel = "assistant"'
        );

        return array(
            'uteis' => $uteis,
            'nao_uteis' => $naoUteis,
            'total' => $total,
            'percentual_util' => $total > 0 ? round($uteis * 100 / $total, 1) : null,
            // Poucos votos tornam o percentual ruído. A view precisa saber disso.
            'cobertura' => $respostas > 0 ? round($total * 100 / $respostas, 1) : 0.0,
        );
    }

    /** Bloqueios por rate limit — se estiver alto, o limite está apertado demais. */
    public function bloqueios($dias = self::DIAS_PADRAO)
    {
        $dias = $this->dias($dias);

        $linha = $this->umaLinha(
            'SELECT COALESCE(SUM(bloqueios_dia), 0) AS total,
                    COUNT(DISTINCT CASE WHEN bloqueios_dia > 0 THEN usuario_id END) AS alunos
             FROM norminha_uso
             WHERE dia >= DATE_SUB(CURDATE(), INTERVAL ' . $dias . ' DAY)'
        );

        return array(
            'total' => (int) ($linha['total'] ?? 0),
            'alunos' => (int) ($linha['alunos'] ?? 0),
        );
    }

    /** Cursos com mais perguntas não resolvidas — onde a dor se concentra. */
    public function cursosComDuvida($dias = self::DIAS_PADRAO, $limite = 10)
    {
        $dias = $this->dias($dias);
        $limite = max(1, min(50, (int) $limite));

        return $this->varias(
            'SELECT c.curso_evento_id, ce.nome AS curso_nome, COUNT(*) AS n
             FROM norminha_mensagens r
             INNER JOIN norminha_conversas c ON c.id = r.conversa_id
             LEFT JOIN cursos_eventos ce ON ce.id = c.curso_evento_id
             WHERE r.created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $dias . ' DAY)
               AND r.resolved_by = "unresolved"
               AND c.curso_evento_id IS NOT NULL
             GROUP BY c.curso_evento_id, ce.nome
             ORDER BY n DESC
             LIMIT ' . $limite
        );
    }

    // -----------------------------------------------------------------

    /** Interpolado no SQL (INTERVAL não aceita placeholder): forçado a inteiro. */
    private function dias($dias)
    {
        return max(1, min(365, (int) $dias));
    }

    private function varias($sql, array $params = array())
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function umaLinha($sql, array $params = array())
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: array();
    }

    private function umValor($sql, array $params = array())
    {
        $linha = $this->umaLinha($sql, $params);

        return $linha ? (int) reset($linha) : 0;
    }
}

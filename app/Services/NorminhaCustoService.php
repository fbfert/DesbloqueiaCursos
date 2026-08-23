<?php

namespace App\Services;

use App\Core\Database;
use App\Support\NorminhaModelos;
use PDO;

/**
 * Quanto a Norminha gastou, e se ainda pode gastar.
 *
 * NÃO EXISTE TABELA NOVA AQUI, DE PROPÓSITO
 *
 * Cada resposta da IA já é gravada em `norminha_mensagens` com o modelo e as
 * três contagens de token (entrada, entrada em cache, saída). O custo é função
 * desses números com o preço do catálogo: uma segunda tabela de contabilidade
 * seria uma cópia que um dia diverge da origem.
 *
 * TODA JANELA DE TEMPO É CALCULADA EM SQL
 *
 * O PHP deste servidor roda em UTC e o MySQL em UTC−3. Uma data "de hoje"
 * calculada em PHP e comparada contra uma coluna gravada pelo banco consulta o
 * dia errado das 21h à meia-noite. Este projeto já teve um bloqueio de login
 * inerte por exatamente isso. Por isso aqui não há `date()`: quem decide que
 * dia é hoje é o mesmo relógio que gravou a linha.
 *
 * O PAPEL GRAVADO É 'assistant', EM INGLÊS
 *
 * NorminhaMensagem::PAPEIS aceita 'user', 'assistant' e 'tool'. Este serviço
 * nasceu filtrando por 'assistente' e, por isso, contava ZERO respostas — o
 * teto de gasto nunca dispararia. Descoberto em 23/08/2026, com a IA já ligada
 * em produção.
 *
 * O TETO É UM FREIO, NÃO UMA CERCA
 *
 * Ele conta o que já foi gasto e recusa a próxima chamada quando o mês estourou.
 * Uma resposta em andamento ainda é paga, e a contagem depende do preço do
 * catálogo estar correto. O teto de verdade, que o provedor garante, é o do
 * painel da OpenAI — este aqui não substitui aquele, avisa antes.
 */
class NorminhaCustoService
{
    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::connection();
    }

    /** Dólares gastos no mês corrente, pelo relógio do banco. */
    public function gastoDoMes()
    {
        $sql = 'SELECT COALESCE(SUM(' . $this->expressao() . '), 0)
                  FROM norminha_mensagens
                 WHERE papel = "assistant"
                   AND modelo_ia IS NOT NULL
                   AND YEAR(created_at) = YEAR(CURDATE())
                   AND MONTH(created_at) = MONTH(CURDATE())';

        return (float) $this->pdo->query($sql)->fetchColumn();
    }

    /**
     * O teto permite mais uma chamada?
     *
     * Teto <= 0 significa "sem teto" — a decisão de não ter freio é do
     * administrador, e fica registrada como tal em vez de virar um zero mudo.
     */
    public function dentroDoTeto($tetoUsd)
    {
        $teto = (float) $tetoUsd;
        if ($teto <= 0) {
            return array('permitido' => true, 'motivo' => 'sem_teto', 'gasto' => $this->gastoDoMes(), 'teto' => 0.0);
        }

        $gasto = $this->gastoDoMes();

        return array(
            'permitido' => $gasto < $teto,
            'motivo' => $gasto < $teto ? 'dentro' : 'teto_atingido',
            'gasto' => $gasto,
            'teto' => $teto,
            'restante' => max(0.0, $teto - $gasto),
            'percentual' => $teto > 0 ? min(100.0, ($gasto / $teto) * 100.0) : 0.0,
        );
    }

    /**
     * Consumo de hoje, dos últimos 7 dias e do mês.
     *
     * As três janelas saem de uma consulta só, para que os números sejam do
     * mesmo instante. Somar em consultas separadas deixaria "hoje" maior que "o
     * mês" se uma resposta entrasse no meio.
     */
    public function resumo()
    {
        $custo = $this->expressao();
        $sql = 'SELECT
                  SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS respostas_hoje,
                  SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS respostas_semana,
                  COUNT(*) AS respostas_mes,
                  COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN ' . $custo . ' ELSE 0 END), 0) AS custo_hoje,
                  COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN ' . $custo . ' ELSE 0 END), 0) AS custo_semana,
                  COALESCE(SUM(' . $custo . '), 0) AS custo_mes,
                  COALESCE(SUM(input_tokens), 0) AS entrada_mes,
                  COALESCE(SUM(cached_input_tokens), 0) AS cache_mes,
                  COALESCE(SUM(output_tokens), 0) AS saida_mes,
                  SUM(CASE WHEN modelo_ia IS NOT NULL AND ' . $custo . ' IS NULL THEN 1 ELSE 0 END) AS sem_preco
                FROM norminha_mensagens
               WHERE papel = "assistant"
                 AND modelo_ia IS NOT NULL
                 AND YEAR(created_at) = YEAR(CURDATE())
                 AND MONTH(created_at) = MONTH(CURDATE())';

        $r = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            $r = array();
        }

        $numero = function ($chave) use ($r) {
            return isset($r[$chave]) ? $r[$chave] : 0;
        };

        return array(
            'respostas_hoje' => (int) $numero('respostas_hoje'),
            'respostas_semana' => (int) $numero('respostas_semana'),
            'respostas_mes' => (int) $numero('respostas_mes'),
            'custo_hoje' => (float) $numero('custo_hoje'),
            'custo_semana' => (float) $numero('custo_semana'),
            'custo_mes' => (float) $numero('custo_mes'),
            'entrada_mes' => (int) $numero('entrada_mes'),
            'cache_mes' => (int) $numero('cache_mes'),
            'saida_mes' => (int) $numero('saida_mes'),
            // Respostas de um modelo fora do catálogo: o custo delas não entra
            // na soma, e esconder isso faria o total parecer completo.
            'sem_preco' => (int) $numero('sem_preco'),
        );
    }

    /** Gasto por modelo no mês, para ver onde o dinheiro foi. */
    public function porModelo()
    {
        // Sem COALESCE de proposito: modelo fora do catalogo devolve NULL, e a
        // tela mostra "sem preco". Um zero ali seria lido como "nao custou nada".
        $sql = 'SELECT modelo_ia,
                       COUNT(*) AS respostas,
                       SUM(' . $this->expressao() . ') AS custo
                  FROM norminha_mensagens
                 WHERE papel = "assistant"
                   AND modelo_ia IS NOT NULL
                   AND YEAR(created_at) = YEAR(CURDATE())
                   AND MONTH(created_at) = MONTH(CURDATE())
                 GROUP BY modelo_ia
                 ORDER BY custo DESC';

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    private function expressao()
    {
        return NorminhaModelos::expressaoSqlCusto(
            'modelo_ia', 'input_tokens', 'cached_input_tokens', 'output_tokens'
        );
    }
}

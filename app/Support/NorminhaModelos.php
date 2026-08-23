<?php

namespace App\Support;

/**
 * Catálogo de modelos da OpenAI oferecidos na tela de administração.
 *
 * POR QUE UMA LISTA FECHADA, E NÃO UM CAMPO DE TEXTO
 *
 * Um campo livre deixa o administrador digitar um nome de modelo que não
 * existe. O erro só apareceria na primeira pergunta de um aluno, como uma falha
 * de rede sem explicação. Com lista fechada, o que não está aqui não é aceito —
 * e o preço fica visível na hora da escolha, que é quando a decisão é tomada.
 *
 * PREÇOS
 *
 * Em dólares por milhão de tokens, conferidos na documentação oficial em
 * 23/08/2026. Servem para duas coisas: mostrar o custo na tela e alimentar o
 * teto de gasto mensal.
 *
 * O preço de entrada em cache é ~10x menor que o de entrada normal. Isso não é
 * detalhe: a Norminha reenvia o mesmo prompt de sistema a cada pergunta, e é
 * justamente essa parte que o provedor mantém em cache. Ignorar o desconto
 * superestimaria o gasto em várias vezes.
 *
 * Preço muda. Quem alterar esta tabela deve reconferir na documentação e
 * atualizar a data acima — o teto de gasto confia nestes números.
 */
class NorminhaModelos
{
    const CONFERIDO_EM = '2026-08-23';

    /**
     * @return array<string,array> modelo => dados
     */
    public static function catalogo()
    {
        return array(
            'gpt-5.6-luna' => array(
                'rotulo' => 'GPT-5.6 Luna',
                'faixa' => 'Econômico',
                'entrada' => 0.20,
                'entrada_cache' => 0.02,
                'saida' => 1.20,
                'aceita_temperatura' => false,
                'recomendado' => true,
                'nota' => 'Suficiente para tutoria apoiada em conteúdo já recuperado do curso. '
                    . 'Dez vezes mais barato que o equilibrado.',
            ),
            'gpt-5-mini' => array(
                'rotulo' => 'GPT-5 Mini',
                'faixa' => 'Econômico',
                'entrada' => 0.25,
                'entrada_cache' => 0.025,
                'saida' => 2.00,
                'aceita_temperatura' => false,
                'recomendado' => false,
                'nota' => 'Alternativa da geração anterior, preço parecido com o Luna.',
            ),
            'gpt-5-nano' => array(
                'rotulo' => 'GPT-5 Nano',
                'faixa' => 'Mínimo',
                'entrada' => 0.05,
                'entrada_cache' => 0.005,
                'saida' => 0.40,
                'aceita_temperatura' => false,
                'recomendado' => false,
                'nota' => 'O mais barato da lista. Respostas mais curtas e menos elaboradas.',
            ),
            'gpt-5.6-terra' => array(
                'rotulo' => 'GPT-5.6 Terra',
                'faixa' => 'Equilibrado',
                'entrada' => 2.00,
                'entrada_cache' => 0.20,
                'saida' => 12.00,
                'aceita_temperatura' => false,
                'recomendado' => false,
                'nota' => 'Explicações mais elaboradas. Dez vezes o custo do Luna.',
            ),
            'gpt-5.6-sol' => array(
                'rotulo' => 'GPT-5.6 Sol',
                'faixa' => 'Avançado',
                'entrada' => 4.00,
                'entrada_cache' => 0.40,
                'saida' => 20.00,
                'aceita_temperatura' => false,
                'recomendado' => false,
                'nota' => 'Pensado para trabalho profissional complexo. Provavelmente mais do '
                    . 'que uma tutoria de curso livre precisa.',
            ),
        );
    }

    /**
     * O modelo aceita o parâmetro `temperature`?
     *
     * Toda a família GPT-5 recusa, com HTTP 400 e a mensagem "Unsupported
     * parameter: 'temperature' is not supported with this model". Foi assim que
     * a integração falhou em 23/08/2026 nos quatro modelos testados — parecia
     * problema de conta e era um parâmetro a mais.
     *
     * Modelo fora do catálogo devolve true: não sabemos, e não cabe a nós
     * remover em silêncio um parâmetro que alguém configurou de propósito.
     */
    public static function aceitaTemperatura($modelo)
    {
        $d = self::dados($modelo);

        return $d === null ? true : !empty($d['aceita_temperatura']);
    }

    /** O modelo existe no catálogo? */
    public static function existe($modelo)
    {
        return array_key_exists((string) $modelo, self::catalogo());
    }

    /**
     * Reduz o nome do provedor ao nome do catálogo.
     *
     * Pede-se `gpt-5-mini` e a resposta volta assinada `gpt-5-mini-2025-08-07`:
     * a OpenAI resolve o alias para o instantâneo datado, e é esse nome que fica
     * gravado em `norminha_mensagens`. Sem esta redução, TODA resposta seria
     * contabilizada como "modelo desconhecido", o custo do mês ficaria zerado e
     * o teto de gasto nunca dispararia — um freio que parece existir e não
     * segura nada. Descoberto em 23/08/2026, na primeira chamada real.
     */
    public static function normalizar($modelo)
    {
        $modelo = trim((string) $modelo);
        if ($modelo === '' || array_key_exists($modelo, self::catalogo())) {
            return $modelo;
        }

        // Só o sufixo de data sai. Qualquer outra variação continua sendo outro
        // modelo, e deve aparecer como tal.
        $semData = preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $modelo);

        return array_key_exists($semData, self::catalogo()) ? $semData : $modelo;
    }

    /** Dados de um modelo, ou null. */
    public static function dados($modelo)
    {
        $c = self::catalogo();
        $chave = self::normalizar($modelo);

        return isset($c[$chave]) ? $c[$chave] : null;
    }

    /**
     * Custo em dólares de uma resposta, dadas as contagens de tokens.
     *
     * Modelo desconhecido devolve null, e não zero: um custo zerado seria lido
     * como "de graça" e furaria o teto de gasto sem avisar.
     */
    public static function custo($modelo, $entrada, $entradaCache, $saida)
    {
        $d = self::dados($modelo);
        if ($d === null) {
            return null;
        }

        // Os tokens em cache vêm DENTRO de input_tokens na Responses API; se
        // fossem cobrados duas vezes, o gasto sairia inflado.
        $entrada = max(0, (int) $entrada);
        $entradaCache = max(0, min((int) $entradaCache, $entrada));
        $entradaCheia = $entrada - $entradaCache;

        return ($entradaCheia * $d['entrada']
              + $entradaCache * $d['entrada_cache']
              + max(0, (int) $saida) * $d['saida']) / 1000000.0;
    }

    /** Expressão SQL que converte tokens em dólares, por modelo. Usada no somatório. */
    public static function expressaoSqlCusto($colModelo, $colEntrada, $colCache, $colSaida)
    {
        $casos = array();
        foreach (self::catalogo() as $id => $d) {
            // Casa o nome do catálogo E o instantâneo datado que o provedor
            // devolve (gpt-5-mini-2025-08-07). A expressão regular prende a
            // data ao fim para que "gpt-5-mini-turbo", se existir um dia, não
            // seja confundido com este.
            $condicao = sprintf(
                "(%s = '%s' OR %s REGEXP '^%s-[0-9]{4}-[0-9]{2}-[0-9]{2}$')",
                $colModelo, $id, $colModelo, preg_quote($id, '/')
            );

            $casos[] = sprintf(
                "WHEN %s THEN ((GREATEST(%s,0) - LEAST(GREATEST(%s,0), GREATEST(%s,0))) * %F "
                . "+ LEAST(GREATEST(%s,0), GREATEST(%s,0)) * %F + GREATEST(%s,0) * %F) / 1000000",
                $condicao,
                $colEntrada, $colCache, $colEntrada, $d['entrada'],
                $colCache, $colEntrada, $d['entrada_cache'],
                $colSaida, $d['saida']
            );
        }

        // Modelo fora do catálogo entra como NULL, para aparecer como lacuna no
        // relatório em vez de sumir dentro de um zero.
        return 'CASE ' . implode(' ', $casos) . ' ELSE NULL END';
    }
}

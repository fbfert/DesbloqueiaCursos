<?php

namespace App\Services\Quiz;

/**
 * Fonte de aleatoriedade do sorteio de questoes.
 *
 * Existe para que o sorteio seja testavel de forma determinista: em producao
 * usa-se QuizRandomizerSeguro (random_int), nos testes QuizRandomizerSemente
 * (semente fixa). Nenhuma consulta usa ORDER BY RAND().
 */
interface QuizRandomizerInterface
{
    /**
     * Devolve uma nova lista com os itens em ordem aleatoria.
     *
     * @param array $itens Lista indexada.
     * @return array
     */
    public function embaralhar(array $itens);

    /**
     * Devolve ate $quantidade itens escolhidos aleatoriamente, sem repeticao.
     *
     * @param array $itens      Lista indexada.
     * @param int   $quantidade Quantidade desejada.
     * @return array
     */
    public function selecionar(array $itens, $quantidade);
}

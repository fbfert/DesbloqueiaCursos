<?php

namespace App\Services\Quiz;

/**
 * Contrato de correcao de questao discursiva.
 *
 * Existe para que a correcao automatizada (por IA, via API) possa ser
 * adicionada no futuro sem alterar o servico de quiz: bastara criar uma nova
 * implementacao desta interface e registra-la no ponto de uso.
 *
 * IMPORTANTE: esta entrega NAO inclui integracao externa. Nao ha chamada de
 * API, credencial nem dependencia de fornecedor em nenhuma implementacao
 * presente no repositorio.
 */
interface CorretorDiscursivaInterface
{
    /**
     * Identificador curto do corretor, gravado em
     * conteudo_quiz_correcoes_discursivas.corretor_referencia.
     *
     * @return string
     */
    public function referencia();

    /**
     * Origem registrada na correcao: 'manual' ou 'automatica'.
     *
     * @return string
     */
    public function origem();

    /**
     * Indica se o corretor consegue avaliar o contexto recebido.
     * Um corretor automatizado pode recusar respostas fora do formato
     * esperado, devolvendo false e deixando o item na fila manual.
     *
     * @param array $contexto Ver corrigir().
     * @return bool
     */
    public function suporta(array $contexto);

    /**
     * Avalia a resposta discursiva.
     *
     * $contexto:
     *   - pergunta_id   int
     *   - enunciado     string
     *   - rubrica       string|null
     *   - nota_maxima   float
     *   - resposta      string  Texto puro do aluno
     *   - nota          float|null  Nota informada (correcao manual)
     *   - feedback      string|null
     *   - corretor_id   int|null    Usuario que corrigiu (correcao manual)
     *
     * Retorno:
     *   - ok         bool
     *   - message    string|null  Motivo quando ok = false
     *   - nota       float|null
     *   - rubrica    string|null
     *   - feedback   string|null
     *
     * @param array $contexto
     * @return array
     */
    public function corrigir(array $contexto);
}

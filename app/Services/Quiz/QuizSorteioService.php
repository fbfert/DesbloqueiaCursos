<?php

namespace App\Services\Quiz;

/**
 * Sorteio de questoes por blocos.
 *
 * Servico de dominio puro: nao acessa banco de dados nem sessao. Recebe a
 * configuracao dos blocos, o pool de questoes de cada bloco e os itens ja
 * utilizados pela inscricao, e devolve o conjunto sorteado com auditoria.
 *
 * Regras implementadas:
 *  - sorteia a quantidade configurada em cada bloco (nada e fixado no codigo);
 *  - respeita a distribuicao de dificuldade do bloco com arredondamento
 *    determinista (metodo do maior resto);
 *  - prioriza questoes ineditas para a inscricao;
 *  - quando faltam ineditas, completa com itens ja usados e registra a
 *    ocorrencia de forma auditavel;
 *  - quando o proprio banco e insuficiente, registra 'banco_insuficiente'.
 */
class QuizSorteioService
{
    const DIFICULDADES = array('facil', 'media', 'dificil');
    const DIFICULDADE_PADRAO = 'media';

    private $randomizer;

    public function __construct(?QuizRandomizerInterface $randomizer = null)
    {
        $this->randomizer = $randomizer ?: new QuizRandomizerSeguro();
    }

    /**
     * @param array $blocos           Blocos ativos do quiz (arrays com id, codigo, quantidade_sortear, ...).
     * @param array $poolPorBloco     [bloco_id => lista de perguntas do banco daquele bloco].
     * @param array $idsJaUtilizados  Lista de pergunta_id ja usados pela inscricao.
     * @param array $opcoes           ['embaralhar_perguntas' => bool]
     * @return array
     */
    public function sortear(array $blocos, array $poolPorBloco, array $idsJaUtilizados = array(), array $opcoes = array())
    {
        $embaralharPerguntas = !empty($opcoes['embaralhar_perguntas']);

        $usados = array();
        foreach ($idsJaUtilizados as $idUsado) {
            $usados[(int) $idUsado] = true;
        }

        $blocosOrdenados = $this->ordenarBlocos($blocos);

        $perguntasSorteadas = array();
        $resumoBlocos       = array();
        $auditoria          = array();
        $comRepeticao       = false;
        $completo           = true;

        foreach ($blocosOrdenados as $bloco) {
            $blocoId    = (int) ($bloco['id'] ?? 0);
            $codigo     = (string) ($bloco['codigo'] ?? '');
            $quantidade = (int) ($bloco['quantidade_sortear'] ?? 0);
            $pool       = isset($poolPorBloco[$blocoId]) && is_array($poolPorBloco[$blocoId])
                ? array_values($poolPorBloco[$blocoId])
                : array();

            $resultadoBloco = $this->sortearBloco($bloco, $pool, $usados);

            foreach ($resultadoBloco['selecionadas'] as $selecionada) {
                $perguntasSorteadas[] = array(
                    'pergunta'    => $selecionada['pergunta'],
                    'bloco'       => $bloco,
                    'reutilizada' => !empty($selecionada['reutilizada']),
                );
                // Itens sorteados nesta tentativa nao podem sair de novo em
                // outro bloco da mesma tentativa.
                $usados[(int) ($selecionada['pergunta']['id'] ?? 0)] = true;
            }

            foreach ($resultadoBloco['auditoria'] as $ocorrencia) {
                $auditoria[] = $ocorrencia;
            }

            if (!empty($resultadoBloco['com_repeticao'])) {
                $comRepeticao = true;
            }
            if (count($resultadoBloco['selecionadas']) < $quantidade) {
                $completo = false;
            }

            $resumoBlocos[] = array(
                'id'                        => $blocoId,
                'codigo'                    => $codigo,
                'titulo'                    => (string) ($bloco['titulo'] ?? ''),
                'tipo_questao'              => (string) ($bloco['tipo_questao'] ?? 'multipla_escolha'),
                'conta_para_percentual'     => !empty($bloco['conta_para_percentual']) ? 1 : 0,
                'obrigatorio_para_envio'    => isset($bloco['obrigatorio_para_envio']) ? (int) (bool) $bloco['obrigatorio_para_envio'] : 1,
                'ordem'                     => (int) ($bloco['ordem'] ?? 0),
                'quantidade_solicitada'     => $quantidade,
                'quantidade_sorteada'       => count($resultadoBloco['selecionadas']),
                'distribuicao_solicitada'   => $resultadoBloco['distribuicao_solicitada'],
                'distribuicao_efetiva'      => $resultadoBloco['distribuicao_efetiva'],
                'reutilizadas'              => $resultadoBloco['total_reutilizadas'],
            );
        }

        if ($embaralharPerguntas) {
            $perguntasSorteadas = $this->embaralharDentroDosBlocos($perguntasSorteadas);
        }

        $ordem = 1;
        foreach ($perguntasSorteadas as &$item) {
            $item['ordem_apresentacao'] = $ordem++;
        }
        unset($item);

        return array(
            'perguntas'     => $perguntasSorteadas,
            'blocos'        => $resumoBlocos,
            'auditoria'     => $auditoria,
            'com_repeticao' => $comRepeticao,
            'completo'      => $completo,
        );
    }

    /**
     * Distribui $total vagas entre as dificuldades conforme percentuais,
     * usando o metodo do maior resto (determinista e sem perder vagas).
     *
     * @param int   $total
     * @param array $percentuais ['facil' => 20, 'media' => 60, 'dificil' => 20]
     * @return array ['facil' => n, 'media' => n, 'dificil' => n]
     */
    public function distribuirCotas($total, array $percentuais)
    {
        $total = (int) $total;
        $cotas = array();
        foreach (self::DIFICULDADES as $dificuldade) {
            $cotas[$dificuldade] = 0;
        }
        if ($total <= 0) {
            return $cotas;
        }

        $soma = 0.0;
        foreach (self::DIFICULDADES as $dificuldade) {
            $valor = isset($percentuais[$dificuldade]) ? (float) $percentuais[$dificuldade] : 0.0;
            if ($valor < 0) {
                $valor = 0.0;
            }
            $percentuais[$dificuldade] = $valor;
            $soma += $valor;
        }

        if ($soma <= 0) {
            $cotas[self::DIFICULDADE_PADRAO] = $total;
            return $cotas;
        }

        $restos    = array();
        $atribuido = 0;
        foreach (self::DIFICULDADES as $indice => $dificuldade) {
            $exato               = ($total * $percentuais[$dificuldade]) / $soma;
            $base                = (int) floor($exato);
            $cotas[$dificuldade] = $base;
            $atribuido          += $base;
            $restos[]            = array(
                'dificuldade' => $dificuldade,
                'resto'       => $exato - $base,
                'indice'      => $indice,
            );
        }

        // Maior resto primeiro; empate resolvido pela ordem fixa das
        // dificuldades, garantindo arredondamento determinista.
        usort($restos, function ($a, $b) {
            if ($a['resto'] === $b['resto']) {
                return $a['indice'] < $b['indice'] ? -1 : 1;
            }
            return $a['resto'] > $b['resto'] ? -1 : 1;
        });

        $faltam = $total - $atribuido;
        $i      = 0;
        while ($faltam > 0 && count($restos) > 0) {
            $cotas[$restos[$i % count($restos)]['dificuldade']]++;
            $faltam--;
            $i++;
        }

        return $cotas;
    }

    /**
     * Converte o JSON/array de distribuicao em percentuais utilizaveis.
     *
     * @param mixed $valor
     * @return array|null NULL quando o bloco nao define distribuicao.
     */
    public function normalizarDistribuicao($valor)
    {
        if (is_string($valor)) {
            $valor = trim($valor);
            if ($valor === '') {
                return null;
            }
            $valor = json_decode($valor, true);
        }
        if (!is_array($valor) || count($valor) === 0) {
            return null;
        }

        $percentuais = array();
        $soma        = 0.0;
        foreach (self::DIFICULDADES as $dificuldade) {
            $item                      = isset($valor[$dificuldade]) ? (float) $valor[$dificuldade] : 0.0;
            $percentuais[$dificuldade] = $item > 0 ? $item : 0.0;
            $soma                     += $percentuais[$dificuldade];
        }

        return $soma > 0 ? $percentuais : null;
    }

    public function normalizarDificuldade($valor)
    {
        $valor = strtolower(trim((string) $valor));
        return in_array($valor, self::DIFICULDADES, true) ? $valor : self::DIFICULDADE_PADRAO;
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private function sortearBloco(array $bloco, array $pool, array $usados)
    {
        $codigo     = (string) ($bloco['codigo'] ?? '');
        $quantidade = (int) ($bloco['quantidade_sortear'] ?? 0);

        $auditoria          = array();
        $selecionadas       = array();
        $totalReutilizadas  = 0;
        $comRepeticao       = false;

        $distribuicao = $this->normalizarDistribuicao(
            isset($bloco['distribuicao_dificuldade']) ? $bloco['distribuicao_dificuldade'] : null
        );

        if ($quantidade <= 0) {
            return array(
                'selecionadas'            => array(),
                'auditoria'               => $auditoria,
                'com_repeticao'           => false,
                'total_reutilizadas'      => 0,
                'distribuicao_solicitada' => $distribuicao,
                'distribuicao_efetiva'    => array(),
            );
        }

        if (count($pool) < $quantidade) {
            $auditoria[] = array(
                'tipo'          => 'banco_insuficiente',
                'bloco_codigo'  => $codigo,
                'solicitado'    => $quantidade,
                'disponivel'    => count($pool),
            );
        }

        $porDificuldade = array();
        foreach (self::DIFICULDADES as $dificuldade) {
            $porDificuldade[$dificuldade] = array();
        }
        foreach ($pool as $pergunta) {
            $dificuldade = $this->normalizarDificuldade($pergunta['dificuldade'] ?? null);
            $porDificuldade[$dificuldade][] = $pergunta;
        }

        $escolhidosIds = array();

        if ($distribuicao !== null) {
            $cotas = $this->distribuirCotas($quantidade, $distribuicao);
            foreach (self::DIFICULDADES as $dificuldade) {
                $cota = (int) $cotas[$dificuldade];
                if ($cota <= 0) {
                    continue;
                }
                $resultado = $this->escolherDoGrupo($porDificuldade[$dificuldade], $cota, $usados, $escolhidosIds);
                foreach ($resultado['itens'] as $item) {
                    $selecionadas[] = $item;
                    $escolhidosIds[(int) ($item['pergunta']['id'] ?? 0)] = true;
                    if (!empty($item['reutilizada'])) {
                        $totalReutilizadas++;
                        $comRepeticao = true;
                    }
                }
                if ($resultado['reutilizadas'] > 0) {
                    $auditoria[] = array(
                        'tipo'         => 'reutilizacao_por_falta_de_ineditas',
                        'bloco_codigo' => $codigo,
                        'dificuldade'  => $dificuldade,
                        'quantidade'   => $resultado['reutilizadas'],
                    );
                }
                $faltou = $cota - count($resultado['itens']);
                if ($faltou > 0) {
                    $auditoria[] = array(
                        'tipo'         => 'distribuicao_ajustada',
                        'bloco_codigo' => $codigo,
                        'dificuldade'  => $dificuldade,
                        'faltou'       => $faltou,
                    );
                }
            }
        }

        // Completa o que faltou (sem distribuicao configurada, ou por deficit
        // de alguma faixa de dificuldade) usando todo o pool restante.
        $faltantes = $quantidade - count($selecionadas);
        if ($faltantes > 0) {
            $resultado = $this->escolherDoGrupo($pool, $faltantes, $usados, $escolhidosIds);
            foreach ($resultado['itens'] as $item) {
                $selecionadas[] = $item;
                $escolhidosIds[(int) ($item['pergunta']['id'] ?? 0)] = true;
                if (!empty($item['reutilizada'])) {
                    $totalReutilizadas++;
                    $comRepeticao = true;
                }
            }
            if ($resultado['reutilizadas'] > 0) {
                $auditoria[] = array(
                    'tipo'         => 'reutilizacao_por_falta_de_ineditas',
                    'bloco_codigo' => $codigo,
                    'dificuldade'  => 'qualquer',
                    'quantidade'   => $resultado['reutilizadas'],
                );
            }
        }

        $distribuicaoEfetiva = array();
        foreach (self::DIFICULDADES as $dificuldade) {
            $distribuicaoEfetiva[$dificuldade] = 0;
        }
        foreach ($selecionadas as $item) {
            $dificuldade = $this->normalizarDificuldade($item['pergunta']['dificuldade'] ?? null);
            $distribuicaoEfetiva[$dificuldade]++;
        }

        return array(
            'selecionadas'            => $selecionadas,
            'auditoria'               => $auditoria,
            'com_repeticao'           => $comRepeticao,
            'total_reutilizadas'      => $totalReutilizadas,
            'distribuicao_solicitada' => $distribuicao,
            'distribuicao_efetiva'    => $distribuicaoEfetiva,
        );
    }

    /**
     * Escolhe ate $quantidade itens do grupo, priorizando os ineditos.
     */
    private function escolherDoGrupo(array $grupo, $quantidade, array $usados, array $escolhidosIds)
    {
        $quantidade = (int) $quantidade;
        if ($quantidade <= 0) {
            return array('itens' => array(), 'reutilizadas' => 0);
        }

        $ineditas = array();
        $usadas   = array();
        foreach ($grupo as $pergunta) {
            $id = (int) ($pergunta['id'] ?? 0);
            if ($id <= 0 || isset($escolhidosIds[$id])) {
                continue;
            }
            if (isset($usados[$id])) {
                $usadas[] = $pergunta;
            } else {
                $ineditas[] = $pergunta;
            }
        }

        $itens        = array();
        $reutilizadas = 0;

        foreach ($this->randomizer->selecionar($ineditas, $quantidade) as $pergunta) {
            $itens[] = array('pergunta' => $pergunta, 'reutilizada' => false);
        }

        $faltam = $quantidade - count($itens);
        if ($faltam > 0 && count($usadas) > 0) {
            foreach ($this->randomizer->selecionar($usadas, $faltam) as $pergunta) {
                $itens[] = array('pergunta' => $pergunta, 'reutilizada' => true);
                $reutilizadas++;
            }
        }

        return array('itens' => $itens, 'reutilizadas' => $reutilizadas);
    }

    private function ordenarBlocos(array $blocos)
    {
        $lista = array_values($blocos);
        usort($lista, function ($a, $b) {
            $ordemA = (int) ($a['ordem'] ?? 0);
            $ordemB = (int) ($b['ordem'] ?? 0);
            if ($ordemA !== $ordemB) {
                return $ordemA < $ordemB ? -1 : 1;
            }
            $idA = (int) ($a['id'] ?? 0);
            $idB = (int) ($b['id'] ?? 0);
            if ($idA === $idB) {
                return 0;
            }
            return $idA < $idB ? -1 : 1;
        });
        return $lista;
    }

    /**
     * Embaralha as questoes dentro de cada bloco, preservando a ordem dos
     * blocos (a prova continua organizada por bloco).
     */
    private function embaralharDentroDosBlocos(array $perguntas)
    {
        $grupos = array();
        $ordem  = array();
        foreach ($perguntas as $item) {
            $chave = (int) ($item['bloco']['id'] ?? 0);
            if (!isset($grupos[$chave])) {
                $grupos[$chave] = array();
                $ordem[]        = $chave;
            }
            $grupos[$chave][] = $item;
        }

        $resultado = array();
        foreach ($ordem as $chave) {
            foreach ($this->randomizer->embaralhar($grupos[$chave]) as $item) {
                $resultado[] = $item;
            }
        }
        return $resultado;
    }
}

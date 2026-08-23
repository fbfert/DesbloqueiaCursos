<?php

namespace App\Services;

use App\Core\Logger;
use App\Support\NorminhaCredenciais;

/**
 * Camada de IA da Norminha: monta o pedido, roda o laço de ferramentas e
 * devolve texto fundamentado.
 *
 * POR QUE ISTO É UMA CLASSE SEPARADA
 * O NorminhaService da Etapa 4 já tinha o ponto de injeção pronto
 * (`$geradorIA`) e um teste provando que a Onda 0 nunca o aciona. Este service
 * é o objeto que se encaixa ali. O orquestrador não precisou ser reescrito —
 * era exatamente o que o desenho da Etapa 4 pretendia.
 *
 * A WHITELIST NÃO É DINÂMICA
 * Nada de `$service->$nome()`. O mapa abaixo lista os nomes aceitos e o método
 * correspondente. Nome fora do mapa é erro registrado, sem chamada. Um modelo
 * que invente `deletar_matricula` não encontra caminho.
 *
 * OS ARGUMENTOS SÃO REVALIDADOS EM PHP
 * Mesmo com `strict: true` no schema, o que chega é decodificado, tem os tipos
 * conferidos e o escopo reamarrado à sessão. O `usuario_id` NUNCA é parâmetro
 * de ferramenta: ele vem da sessão e é injetado aqui.
 *
 * O LAÇO TEM FIM
 * Três ciclos, no máximo. Estourou, encerra com o que tiver — um laço sem teto
 * é uma fatura sem teto.
 */
class NorminhaIaService
{
    const MAX_CICLOS = 3;
    const MAX_CHARS_TOOL_OUTPUT = 3000;

    private $openai;
    private $tools;
    private $knowledge;
    private $prompt;

    /** Contadores de diagnóstico, por chamada. */
    private $ciclos = 0;
    private $chamadasDeFerramenta = 0;

    /** @var NorminhaCustoService */
    private $custo;

    /**
     * Resultado da checagem de teto, memorizado por instância.
     *
     * disponivel() é chamado mais de uma vez no mesmo pedido; sem isto, cada
     * chamada faria uma agregação no banco para responder a mesma pergunta.
     */
    private $tetoAvaliado = null;

    public function __construct(
        OpenAIService $openai = null,
        NorminhaToolsService $tools = null,
        NorminhaKnowledgeService $knowledge = null,
        NorminhaPromptService $prompt = null,
        NorminhaCustoService $custo = null
    ) {
        $this->openai = $openai ?: new OpenAIService();
        $this->tools = $tools ?: new NorminhaToolsService();
        $this->knowledge = $knowledge ?: new NorminhaKnowledgeService();
        $this->prompt = $prompt ?: new NorminhaPromptService();
        // Injetavel para que o caminho de falha do teto seja testavel: sem
        // isso nao ha como provar que ele falha FECHANDO.
        $this->custo = $custo ?: new NorminhaCustoService();
    }

    public function disponivel()
    {
        if (!$this->openai->isEnabled() || !$this->openai->hasApiKey()) {
            return false;
        }

        return $this->dentroDoTetoDeGasto();
    }

    /**
     * O teto mensal de gasto ainda permite chamar a IA?
     *
     * Estourado o teto, a IA some e o chat volta ao modo determinístico: o aluno
     * continua obtendo progresso, retomada e certificado, e as perguntas livres
     * passam a receber "ainda não consigo responder isso". Nenhuma tela de erro,
     * nenhuma cobrança inesperada.
     *
     * FALHA FECHANDO. Se a contagem não puder ser feita — banco fora, consulta
     * quebrada —, a resposta é "não pode gastar". O inverso liberaria despesa
     * ilimitada justamente no momento em que ninguém está conseguindo medi-la.
     */
    private function dentroDoTetoDeGasto()
    {
        if ($this->tetoAvaliado !== null) {
            return $this->tetoAvaliado;
        }

        try {
            $resultado = $this->custo->dentroDoTeto(NorminhaCredenciais::tetoMensalUsd());
            if (empty($resultado['permitido'])) {
                Logger::warning('norminha.ia.teto_mensal_atingido', array(
                    'gasto' => round((float) $resultado['gasto'], 4),
                    'teto' => round((float) $resultado['teto'], 2),
                ));
            }
            $this->tetoAvaliado = !empty($resultado['permitido']);
        } catch (\Throwable $e) {
            Logger::error('norminha.ia.teto_indisponivel', array('message' => $e->getMessage()));
            $this->tetoAvaliado = false;
        }

        return $this->tetoAvaliado;
    }

    /**
     * Assinatura esperada pelo NorminhaService (Etapa 4).
     *
     * @return array|null null quando não há resposta utilizável — o orquestrador
     *                    então segue pelo caminho `unresolved`, sem inventar texto
     */
    public function gerar($mensagem, array $contextoParaModelo, array $opcoes = array())
    {
        $this->ciclos = 0;
        $this->chamadasDeFerramenta = 0;

        if (!$this->disponivel()) {
            return null;
        }

        $usuarioId = isset($opcoes['usuario_id']) ? (int) $opcoes['usuario_id'] : 0;
        $contextoInterno = isset($opcoes['contexto']) ? $opcoes['contexto'] : $contextoParaModelo;
        if ($usuarioId <= 0) {
            return null;
        }

        // Guardrail antes de qualquer token: pedido de gabarito em prova nem
        // chega ao provedor.
        $recusa = $this->prompt->recusarAntesDoModelo($contextoInterno, $mensagem);
        if ($recusa !== null) {
            Logger::info('norminha.ia.recusa_guardrail', array(
                'motivo' => $recusa['motivo'],
                'curso_evento_id' => $contextoInterno['curso_evento_id'] ?? null,
            ));

            return array(
                'message' => $recusa['mensagem'],
                'resolved_by' => 'php',
                'intent' => 'guardrail_avaliacao',
                'sources' => array(),
                'usage' => array(),
            );
        }

        $evidencia = $this->knowledge->evidenciaPara($usuarioId, $contextoInterno, $mensagem);

        $input = $this->prompt->montarInput(
            $contextoParaModelo,
            $evidencia,
            $mensagem,
            isset($opcoes['historico']) && is_array($opcoes['historico']) ? $opcoes['historico'] : array()
        );

        $instrucoes = $this->prompt->instrucoes(isset($opcoes['prompt_complementar']) ? $opcoes['prompt_complementar'] : null);
        $usouFerramenta = false;
        $usoAcumulado = array('input_tokens' => 0, 'cached_input_tokens' => 0, 'output_tokens' => 0);
        $ultimo = null;

        for ($this->ciclos = 1; $this->ciclos <= self::MAX_CICLOS; $this->ciclos++) {
            $resposta = $this->openai->gerar(array(
                'instructions' => $instrucoes,
                'input' => $input,
                'tools' => $this->schemas(),
                'tool_choice' => 'auto',
                'parallel_tool_calls' => false,
                'max_output_tokens' => isset($opcoes['max_output_tokens']) ? $opcoes['max_output_tokens'] : null,
            ));

            $this->acumularUso($usoAcumulado, $resposta);
            $ultimo = $resposta;

            if (empty($resposta['ok'])) {
                Logger::warning('norminha.ia.falha', array(
                    'error_code' => $resposta['error_code'],
                    'status' => $resposta['status'],
                    'ciclo' => $this->ciclos,
                ));

                return null; // não se inventa resposta para encobrir falha
            }

            if (empty($resposta['function_calls'])) {
                return $this->montarRetorno($resposta, $evidencia, $usouFerramenta, $usoAcumulado);
            }

            // Há ferramenta a executar: o item da chamada volta ao input, e o
            // resultado vai junto com o mesmo call_id.
            $usouFerramenta = true;
            foreach ($resposta['function_calls'] as $chamada) {
                $input[] = $chamada['item'];
                $input[] = array(
                    'type' => 'function_call_output',
                    'call_id' => $chamada['call_id'],
                    'output' => $this->executar($chamada, $usuarioId, $contextoInterno),
                );
            }
        }

        // Estourou os ciclos: encerra com o texto que houver, sem novo pedido.
        Logger::warning('norminha.ia.ciclos_excedidos', array('max' => self::MAX_CICLOS));

        if ($ultimo && !empty($ultimo['ok']) && trim((string) $ultimo['message']) !== '') {
            return $this->montarRetorno($ultimo, $evidencia, true, $usoAcumulado);
        }

        return null;
    }

    public function diagnostico()
    {
        return array(
            'ciclos' => $this->ciclos,
            'chamadas_de_ferramenta' => $this->chamadasDeFerramenta,
        );
    }

    // =================================================================
    // Ferramentas
    // =================================================================

    /**
     * Mapa explícito nome → executor. É a whitelist.
     * Acrescentar ferramenta aqui é uma decisão consciente; não há descoberta
     * automática de método.
     */
    private function executores()
    {
        $tools = $this->tools;

        return array(
            'get_student_progress' => function ($args, $usuarioId, $ctx) use ($tools) {
                return $tools->getStudentProgress($usuarioId, $args['inscricao_id']);
            },
            'get_resume_point' => function ($args, $usuarioId, $ctx) use ($tools) {
                return $tools->getResumePoint($usuarioId, $args['inscricao_id']);
            },
            'get_next_learning_item' => function ($args, $usuarioId, $ctx) use ($tools) {
                return $tools->getNextLearningItem($usuarioId, $args['inscricao_id']);
            },
            'get_certificate_status' => function ($args, $usuarioId, $ctx) use ($tools) {
                return $tools->getCertificateStatus($usuarioId, $args['inscricao_id']);
            },
            'get_current_lesson_context' => function ($args, $usuarioId, $ctx) use ($tools) {
                return $tools->getCurrentLessonContext($usuarioId, array(
                    'inscricao_id' => $args['inscricao_id'],
                    'item_id' => isset($ctx['item_atual']['id']) ? $ctx['item_atual']['id'] : null,
                ));
            },
        );
    }

    /** Schemas estritos. `usuario_id` não é — e nunca será — parâmetro. */
    private function schemas()
    {
        $inscricao = array(
            'type' => 'object',
            'properties' => array(
                'inscricao_id' => array(
                    'type' => array('integer', 'null'),
                    'description' => 'Inscrição sugerida pelo contexto; o backend validará a propriedade.',
                ),
            ),
            'required' => array('inscricao_id'),
            'additionalProperties' => false,
        );

        $descricoes = array(
            'get_student_progress' => 'Obtém o progresso real da inscrição do aluno autenticado.',
            'get_resume_point' => 'Onde o aluno parou. Devolve `origem`: ultimo_acesso, proximo_item ou curso_concluido.',
            'get_next_learning_item' => 'Próximo item não concluído, na ordem do curso.',
            'get_certificate_status' => 'Situação do certificado e os motivos de pendência.',
            'get_current_lesson_context' => 'Metadados da aula atual, sem o conteúdo.',
        );

        $schemas = array();
        foreach ($descricoes as $nome => $descricao) {
            $schemas[] = array(
                'type' => 'function',
                'name' => $nome,
                'description' => $descricao,
                'strict' => true,
                'parameters' => $inscricao,
            );
        }

        return $schemas;
    }

    /**
     * Executa uma chamada, sempre com o usuário da SESSÃO.
     * Devolve string JSON — é o que `function_call_output` espera.
     */
    private function executar(array $chamada, $usuarioId, array $contexto)
    {
        $nome = isset($chamada['name']) ? (string) $chamada['name'] : '';
        $executores = $this->executores();

        if (!isset($executores[$nome])) {
            Logger::warning('norminha.ia.ferramenta_desconhecida', array('nome' => substr($nome, 0, 60)));

            return json_encode(array('ok' => false, 'erro' => 'ferramenta_nao_disponivel'));
        }

        $args = $this->validarArgumentos($chamada, $contexto);
        $this->chamadasDeFerramenta++;

        try {
            $resultado = $executores[$nome]($args, $usuarioId, $contexto);
        } catch (\Throwable $e) {
            Logger::error('norminha.ia.ferramenta_falhou', array(
                'nome' => $nome, 'message' => $e->getMessage(),
            ));

            return json_encode(array('ok' => false, 'erro' => 'ferramenta_indisponivel'));
        }

        return $this->limitarSaida($resultado);
    }

    /**
     * Revalida os argumentos mesmo com strict schema.
     *
     * O schema é do provedor; a garantia tem de ser nossa. E o escopo é sempre
     * reamarrado: uma inscrição que o modelo invente não sobrevive ao
     * NorminhaContextService, que valida contra a sessão — mas aqui já se
     * prefere a do contexto validado.
     */
    private function validarArgumentos(array $chamada, array $contexto)
    {
        $bruto = isset($chamada['arguments']) ? (string) $chamada['arguments'] : '';
        $args = json_decode($bruto, true);
        if (!is_array($args)) {
            $args = array();
        }

        $inscricao = null;
        if (isset($args['inscricao_id']) && is_numeric($args['inscricao_id']) && (int) $args['inscricao_id'] > 0) {
            $inscricao = (int) $args['inscricao_id'];
        }
        // O contexto validado tem precedência sobre o palpite do modelo.
        if (!empty($contexto['inscricao_id'])) {
            $inscricao = (int) $contexto['inscricao_id'];
        }

        // Só a chave conhecida sobrevive: campo extra em schema strict é
        // descartado aqui também.
        return array('inscricao_id' => $inscricao);
    }

    private function limitarSaida($resultado)
    {
        $json = json_encode($resultado, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return json_encode(array('ok' => false, 'erro' => 'resultado_ilegivel'));
        }

        if (strlen($json) <= self::MAX_CHARS_TOOL_OUTPUT) {
            return $json;
        }

        // Truncar JSON quebraria o parse do modelo: devolve-se um aviso válido.
        Logger::warning('norminha.ia.saida_de_ferramenta_grande', array('bytes' => strlen($json)));

        return json_encode(array(
            'ok' => true,
            'aviso' => 'resultado longo demais para exibir por inteiro',
            'resumo' => mb_substr(strip_tags((string) $json), 0, 500, 'UTF-8'),
        ), JSON_UNESCAPED_UNICODE);
    }

    // =================================================================

    private function montarRetorno(array $resposta, array $evidencia, $usouFerramenta, array $uso)
    {
        // `hybrid` quando houve ferramenta ou evidência; `ai` só quando foi
        // resposta puramente linguística — que deve ser rara e é sinal de que a
        // evidência não chegou.
        $resolvedBy = ($usouFerramenta || !empty($evidencia['tem_evidencia'])) ? 'hybrid' : 'ai';

        return array(
            'message' => (string) $resposta['message'],
            'resolved_by' => $resolvedBy,
            'intent' => 'tutoria_conteudo',
            'sources' => isset($evidencia['fontes']) ? $evidencia['fontes'] : array(),
            'usage' => array_merge($uso, array(
                'response_id' => $resposta['response_id'],
                'model' => $resposta['model'],
                'latencia_ms' => $resposta['latencia_ms'],
                'ciclos' => $this->ciclos,
                'chamadas_de_ferramenta' => $this->chamadasDeFerramenta,
            )),
        );
    }

    private function acumularUso(array &$acumulado, array $resposta)
    {
        foreach (array('input_tokens', 'cached_input_tokens', 'output_tokens') as $chave) {
            if (isset($resposta['usage'][$chave]) && $resposta['usage'][$chave] !== null) {
                $acumulado[$chave] += (int) $resposta['usage'][$chave];
            }
        }
    }
}

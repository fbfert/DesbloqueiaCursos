<?php

namespace App\Services;

use App\Core\Logger;

/**
 * Integração com a Responses API da OpenAI (POST /v1/responses).
 *
 * A estrutura espelha a do antigo ClaudeService, por coerência com o projeto.
 * Aquele service foi removido na Etapa 18; o histórico está em
 * specs/0001-integracao-api-claude/.
 *
 * ISOLAMENTO
 * Este service não conhece a Norminha. Ele recebe uma estrutura pronta, fala
 * HTTP e devolve um array PHP estável. Quem monta prompt, escolhe ferramenta e
 * decide o que fazer com a resposta é o NorminhaService, na Etapa 12.
 *
 * O QUE NUNCA SAI DAQUI
 * A chave de API não entra em log, em exceção, em resposta nem em tela. Os logs
 * carregam evento, status HTTP, modelo, latência, contagem de tokens e ids
 * técnicos — nunca o prompt, nunca o conteúdo enviado, nunca PII.
 *
 * DESLIGADO POR PADRÃO
 * `OPENAI_ENABLED=false` impede QUALQUER requisição, antes de montar payload ou
 * abrir socket. É a última linha entre um bug e a fatura, junto do hard cap
 * configurado no painel do provedor.
 *
 * PARSING SEM SDK
 * A Responses API devolve um array `output` com itens heterogêneos: mensagens
 * com blocos de conteúdo, chamadas de função, e itens de raciocínio que devem
 * ser ignorados. Nada aqui depende de helper de SDK (`output_text` e afins):
 * o JSON real é percorrido, porque um campo de conveniência que suma numa
 * versão nova quebraria em silêncio.
 */
class OpenAIService
{
    const ENDPOINT = '/v1/responses';
    const CONNECT_TIMEOUT = 10;

    private $config;

    public function __construct(array $configOverride = null)
    {
        $ai = require BASE_PATH . '/config/ai.php';
        $this->config = $configOverride !== null
            ? $configOverride
            : (isset($ai['openai']) ? $ai['openai'] : array());
    }

    public function isEnabled()
    {
        return !empty($this->config['enabled']);
    }

    public function hasApiKey()
    {
        return isset($this->config['api_key']) && trim((string) $this->config['api_key']) !== '';
    }

    /** Modelo efetivo, para diagnóstico no admin. Nunca devolve a chave. */
    public function diagnostico()
    {
        return array(
            'habilitado' => $this->isEnabled(),
            'chave_configurada' => $this->hasApiKey(),
            'modelo' => isset($this->config['model']) ? (string) $this->config['model'] : null,
            'base_url' => isset($this->config['base_url']) ? (string) $this->config['base_url'] : null,
            'store' => !empty($this->config['store']),
            'timeout' => (int) ($this->config['timeout'] ?? 30),
        );
    }

    /**
     * Gera uma resposta.
     *
     * @param array $pedido instructions, input, tools, tool_choice,
     *                      parallel_tool_calls, max_output_tokens, temperature
     * @return array ok, status, message, function_calls, response_id, model,
     *               usage, latencia_ms, error_code
     */
    public function gerar(array $pedido)
    {
        if (!$this->isEnabled()) {
            // Sem requisição nenhuma: nem payload, nem socket.
            return $this->falha('desabilitado', 'A integração com a OpenAI está desativada.', 503);
        }

        if (!$this->hasApiKey()) {
            Logger::error('openai.chave_ausente', array('modelo' => $this->config['model'] ?? null));

            return $this->falha('sem_chave', 'A integração com a OpenAI não está configurada.', 503);
        }

        $payload = $this->montarPayload($pedido);
        if ($payload === null) {
            return $this->falha('payload_invalido', 'Pedido inválido para o provedor.', 422);
        }

        // O nome do modelo é extraído ANTES de qualquer log. Nenhuma chamada a
        // Logger neste arquivo referencia $payload, $input, $corpo ou
        // instructions — assim a garantia "o prompt nunca é logado" é
        // verificável por varredura simples, sem exceções para analisar.
        $modelo = $payload['model'];

        $inicio = microtime(true);
        $http = $this->postar($payload);
        $latencia = (int) round((microtime(true) - $inicio) * 1000);

        if (!empty($http['erro_curl'])) {
            Logger::error('openai.falha_transporte', array(
                'erro' => $http['erro_curl'],
                'latencia_ms' => $latencia,
                'modelo' => $modelo,
            ));

            return $this->falha('transporte', 'Não foi possível falar com o provedor agora.', 503, $latencia);
        }

        $status = (int) $http['status'];
        $decodificado = json_decode((string) $http['corpo'], true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodificado)) {
            Logger::error('openai.json_invalido', array(
                'status' => $status,
                'latencia_ms' => $latencia,
                'bytes' => strlen((string) $http['corpo']),
            ));

            return $this->falha('json_invalido', 'Resposta do provedor não pôde ser lida.', 502, $latencia);
        }

        if ($status < 200 || $status >= 300) {
            $mensagem = $this->extrairMensagemDeErro($decodificado);
            Logger::error('openai.erro_http', array(
                'status' => $status,
                'latencia_ms' => $latencia,
                'modelo' => $modelo,
                // Mensagem do provedor: técnica, sem conteúdo do aluno.
                'provedor' => $mensagem,
            ));

            // A explicacao do provedor SEGUE junto (23/08/2026). Antes ela so
            // ia para o log e quem estava na tela recebia "o provedor recusou a
            // requisicao" -- generico a ponto de ser inutil. No caso real que
            // motivou isto, a OpenAI dizia com todas as letras "Unsupported
            // parameter: 'temperature' is not supported with this model", e o
            // administrador ficou trocando de modelo as cegas porque a tela
            // sugeria um problema de conta.
            return $this->falha(
                $this->codigoPorStatus($status),
                'O provedor recusou a requisição.',
                $status,
                $latencia,
                $mensagem
            );
        }

        return $this->interpretar($decodificado, $latencia);
    }

    // =================================================================
    // Montagem
    // =================================================================

    private function montarPayload(array $pedido)
    {
        $input = isset($pedido['input']) ? $pedido['input'] : null;
        if (!is_array($input) || !$input) {
            return null;
        }

        $payload = array(
            'model' => (string) ($this->config['model'] ?? ''),
            'input' => $input,
            // A memória vive no MySQL do Desbloqueia. Nada de histórico no
            // provedor: store=false por padrão, e por decisão de produto.
            'store' => !empty($this->config['store']),
        );

        if ($payload['model'] === '') {
            return null;
        }

        if (!empty($pedido['instructions'])) {
            $payload['instructions'] = (string) $pedido['instructions'];
        }

        $maxOutput = $this->normalizarMaxOutput(
            isset($pedido['max_output_tokens']) ? $pedido['max_output_tokens'] : ($this->config['max_output_tokens'] ?? null)
        );
        if ($maxOutput !== null) {
            $payload['max_output_tokens'] = $maxOutput;
        }

        $temperatura = $this->normalizarTemperatura(
            isset($pedido['temperature']) ? $pedido['temperature'] : ($this->config['temperature'] ?? null)
        );
        if ($temperatura !== null) {
            $payload['temperature'] = $temperatura;
        }

        if (!empty($pedido['tools']) && is_array($pedido['tools'])) {
            $payload['tools'] = array_values($pedido['tools']);

            if (isset($pedido['tool_choice'])) {
                $payload['tool_choice'] = $pedido['tool_choice'];
            }
            // A V1 usa false: uma ferramenta por vez torna o laço auditável e
            // o custo previsível.
            if (array_key_exists('parallel_tool_calls', $pedido)) {
                $payload['parallel_tool_calls'] = (bool) $pedido['parallel_tool_calls'];
            }
        }

        return $payload;
    }

    // =================================================================
    // Transporte
    // =================================================================

    private function postar(array $payload)
    {
        $url = rtrim((string) ($this->config['base_url'] ?? 'https://api.openai.com'), '/') . self::ENDPOINT;
        $corpo = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($corpo === false) {
            return array('erro_curl' => 'nao foi possivel serializar o payload', 'status' => 0, 'corpo' => '');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return array('erro_curl' => 'nao foi possivel iniciar a requisicao', 'status' => 0, 'corpo' => '');
        }

        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $corpo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => (int) ($this->config['timeout'] ?? 30) > 0 ? (int) $this->config['timeout'] : 30,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . (string) $this->config['api_key'],
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
            ),
        ));

        $resposta = curl_exec($ch);
        $erro = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resposta === false) {
            // A mensagem do cURL não carrega a chave: o header não é ecoado.
            return array('erro_curl' => $erro !== '' ? $erro : 'falha desconhecida', 'status' => $status, 'corpo' => '');
        }

        return array('erro_curl' => null, 'status' => $status, 'corpo' => (string) $resposta);
    }

    // =================================================================
    // Interpretação da resposta
    // =================================================================

    /**
     * Percorre o array `output` item a item.
     *
     * Formato confirmado na documentação oficial em 23/08/2026:
     *   - item de mensagem: { type: "message", content: [ { type: "output_text",
     *     text: "..." }, ... ] }
     *   - chamada de função: { type: "function_call", id, call_id, name,
     *     arguments (string JSON) }
     *   - itens de raciocínio e outros tipos: ignorados em silêncio, para uma
     *     versão nova da API não quebrar o parser.
     */
    private function interpretar(array $r, $latencia)
    {
        $textos = array();
        $chamadas = array();

        $output = isset($r['output']) && is_array($r['output']) ? $r['output'] : array();

        foreach ($output as $item) {
            if (!is_array($item) || !isset($item['type'])) {
                continue;
            }

            if ($item['type'] === 'function_call') {
                $chamadas[] = array(
                    'call_id' => isset($item['call_id']) ? (string) $item['call_id'] : null,
                    'name' => isset($item['name']) ? (string) $item['name'] : null,
                    // Vem como STRING JSON. Decodificar é responsabilidade de
                    // quem valida os argumentos, na Etapa 12 — aqui só se
                    // entrega o que chegou.
                    'arguments' => isset($item['arguments']) ? (string) $item['arguments'] : '',
                    'item' => $item,
                );
                continue;
            }

            if ($item['type'] === 'message' && isset($item['content']) && is_array($item['content'])) {
                foreach ($item['content'] as $bloco) {
                    if (is_array($bloco) && isset($bloco['text']) && is_string($bloco['text'])) {
                        $textos[] = $bloco['text'];
                    }
                }
            }
        }

        $mensagem = trim(implode("\n", $textos));
        $status = isset($r['status']) ? (string) $r['status'] : null;

        // Resposta sem texto E sem ferramenta é falha, não sucesso vazio: quem
        // chama precisa saber que não há o que mostrar ao aluno.
        if ($mensagem === '' && !$chamadas) {
            Logger::warning('openai.resposta_vazia', array(
                'response_id' => isset($r['id']) ? (string) $r['id'] : null,
                'status_provedor' => $status,
                'latencia_ms' => $latencia,
            ));

            return $this->falha('resposta_vazia', 'O provedor não devolveu conteúdo.', 502, $latencia);
        }

        $uso = $this->extrairUso($r);

        Logger::info('openai.resposta', array(
            'response_id' => isset($r['id']) ? (string) $r['id'] : null,
            'modelo' => isset($r['model']) ? (string) $r['model'] : null,
            'status_provedor' => $status,
            'latencia_ms' => $latencia,
            'input_tokens' => $uso['input_tokens'],
            'cached_input_tokens' => $uso['cached_input_tokens'],
            'output_tokens' => $uso['output_tokens'],
            'function_calls' => count($chamadas),
        ));

        return array(
            'ok' => true,
            'status' => 200,
            'message' => $mensagem,
            'function_calls' => $chamadas,
            'response_id' => isset($r['id']) ? (string) $r['id'] : null,
            'model' => isset($r['model']) ? (string) $r['model'] : null,
            'status_provedor' => $status,
            'usage' => $uso,
            'latencia_ms' => $latencia,
            'error_code' => null,
        );
    }

    /**
     * Contagem de tokens.
     *
     * Os tokens em cache ficam aninhados em input_tokens_details.cached_tokens.
     * Eles são cobrados mais barato, então relatá-los é o que permite ao painel
     * de custo dizer a verdade em vez de superestimar.
     */
    private function extrairUso(array $r)
    {
        $u = isset($r['usage']) && is_array($r['usage']) ? $r['usage'] : array();

        $cached = null;
        if (isset($u['input_tokens_details']['cached_tokens'])) {
            $cached = (int) $u['input_tokens_details']['cached_tokens'];
        } elseif (isset($u['cached_tokens'])) {
            $cached = (int) $u['cached_tokens'];
        }

        return array(
            'input_tokens' => isset($u['input_tokens']) ? (int) $u['input_tokens'] : null,
            'cached_input_tokens' => $cached,
            'output_tokens' => isset($u['output_tokens']) ? (int) $u['output_tokens'] : null,
            'total_tokens' => isset($u['total_tokens']) ? (int) $u['total_tokens'] : null,
        );
    }

    // =================================================================
    // Auxiliares
    // =================================================================

    private function extrairMensagemDeErro(array $decodificado)
    {
        foreach (array(array('error', 'message'), array('message')) as $caminho) {
            $atual = $decodificado;
            foreach ($caminho as $chave) {
                if (!is_array($atual) || !isset($atual[$chave])) {
                    $atual = null;
                    break;
                }
                $atual = $atual[$chave];
            }
            if (is_string($atual) && $atual !== '') {
                return substr($atual, 0, 300);
            }
        }

        return 'erro sem mensagem';
    }

    private function codigoPorStatus($status)
    {
        if ($status === 401 || $status === 403) {
            return 'credencial';
        }
        if ($status === 429) {
            return 'limite_provedor';
        }
        if ($status >= 500) {
            return 'provedor_indisponivel';
        }

        return 'requisicao_recusada';
    }

    private function normalizarMaxOutput($valor)
    {
        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            return null;
        }

        return max(16, min(32000, (int) $valor));
    }

    private function normalizarTemperatura($valor)
    {
        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            return null;
        }

        return max(0.0, min(2.0, round((float) $valor, 2)));
    }

    private function falha($codigo, $mensagem, $status, $latencia = null, $detalheProvedor = null)
    {
        return array(
            'ok' => false,
            'status' => (int) $status,
            'message' => '',
            'function_calls' => array(),
            'response_id' => null,
            'model' => null,
            'status_provedor' => null,
            'usage' => array(
                'input_tokens' => null,
                'cached_input_tokens' => null,
                'output_tokens' => null,
                'total_tokens' => null,
            ),
            'latencia_ms' => $latencia,
            'error_code' => $codigo,
            'mensagem_interna' => $mensagem,
            // Texto do provedor, quando houver. Vai para tela de ADMIN, nunca
            // para o aluno: e diagnostico de integracao, nao conversa.
            'provedor_mensagem' => $detalheProvedor !== null && trim((string) $detalheProvedor) !== ''
                ? (string) $detalheProvedor
                : null,
        );
    }
}

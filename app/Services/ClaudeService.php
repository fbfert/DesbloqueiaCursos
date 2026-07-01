<?php

namespace App\Services;

use App\Core\Logger;

class ClaudeService
{
    private $config;

    public function __construct()
    {
        $this->config = require BASE_PATH . '/config/ai.php';
    }

    public function isEnabled()
    {
        $config = $this->runtimeConfig();
        return !empty($config['enabled']);
    }

    public function hasApiKey()
    {
        $config = $this->runtimeConfig();
        return trim((string) ($config['api_key'] ?? '')) !== '';
    }

    public function gerarResposta(array $input, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $config = $this->runtimeConfig();

        if (empty($config['enabled'])) {
            return array(
                'ok' => false,
                'status' => 503,
                'message' => 'A integração com o Claude está desativada.',
            );
        }

        if (!$this->hasApiKey()) {
            Logger::error('claude.api.sem_chave', array(
                'usuario_id' => $actorUserId,
                'ip_address' => $ipAddress,
            ));

            return array(
                'ok' => false,
                'status' => 503,
                'message' => 'A integração com o Claude está temporariamente indisponível.',
            );
        }

        $prompt = trim((string) ($input['prompt'] ?? ''));
        if ($prompt === '') {
            return array(
                'ok' => false,
                'status' => 422,
                'message' => 'Informe um prompt para gerar a resposta.',
            );
        }

        $model = trim((string) ($input['model'] ?? $config['model']));
        if ($model === '') {
            return array(
                'ok' => false,
                'status' => 422,
                'message' => 'Informe um modelo válido para o Claude.',
            );
        }

        $maxTokens = $this->normalizeMaxTokens(isset($input['max_tokens']) ? $input['max_tokens'] : $config['max_tokens']);
        $temperature = $this->normalizeTemperature(isset($input['temperature']) ? $input['temperature'] : $config['temperature']);
        $systemPrompt = trim((string) ($input['system'] ?? ''));

        $payload = array(
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'text',
                            'text' => $prompt,
                        ),
                    ),
                ),
            ),
        );

        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        $response = $this->requestJson('POST', '/v1/messages', $payload);
        if (empty($response['ok'])) {
            Logger::error('claude.api.falha', array(
                'usuario_id' => $actorUserId,
                'ip_address' => $ipAddress,
                'model' => $model,
                'http_status' => isset($response['http_status']) ? (int) $response['http_status'] : null,
                'message' => isset($response['message']) ? $response['message'] : 'Falha desconhecida.',
            ));

            return array(
                'ok' => false,
                'status' => isset($response['http_status']) && (int) $response['http_status'] > 0 ? (int) $response['http_status'] : 502,
                'message' => isset($response['message']) ? $response['message'] : 'Não foi possível consultar o Claude.',
                'details' => isset($response['response']) ? $response['response'] : null,
            );
        }

        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();
        $texto = $this->extractTextFromContent(isset($data['content']) && is_array($data['content']) ? $data['content'] : array());
        $usage = isset($data['usage']) && is_array($data['usage']) ? $data['usage'] : array();

        Logger::info('claude.api.sucesso', array(
            'usuario_id' => $actorUserId,
            'ip_address' => $ipAddress,
            'model' => isset($data['model']) ? $data['model'] : $model,
            'input_chars' => strlen($prompt),
            'output_chars' => strlen($texto),
            'input_tokens' => isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : null,
            'output_tokens' => isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : null,
        ));

        return array(
            'ok' => true,
            'status' => 200,
            'model' => isset($data['model']) ? $data['model'] : $model,
            'id' => isset($data['id']) ? $data['id'] : null,
            'content' => $texto,
            'content_blocks' => isset($data['content']) && is_array($data['content']) ? $data['content'] : array(),
            'usage' => $usage,
            'stop_reason' => isset($data['stop_reason']) ? $data['stop_reason'] : null,
            'raw' => $data,
        );
    }

    private function requestJson($method, $endpoint, ?array $payload = null)
    {
        $config = $this->runtimeConfig();
        $url = rtrim((string) ($config['base_url'] ?? 'https://api.anthropic.com'), '/') . '/' . ltrim($endpoint, '/');
        $body = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        if ($payload !== null && $body === false) {
            return array(
                'ok' => false,
                'status' => 500,
                'message' => 'Não foi possível preparar a requisição para o Claude.',
            );
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return array(
                'ok' => false,
                'status' => 500,
                'message' => 'Não foi possível iniciar a requisição HTTP.',
            );
        }

        $headers = array(
            'X-Api-Key: ' . $config['api_key'],
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
            'Accept: application/json',
        );

        curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST => strtoupper((string) $method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => (int) $config['timeout'] > 0 ? (int) $config['timeout'] : 30,
            CURLOPT_HTTPHEADER => $headers,
        ));

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $curlErrorNo = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrorNo) {
            return array(
                'ok' => false,
                'status' => 502,
                'message' => 'Falha de comunicação com o Claude.',
                'error' => $curlError,
                'http_status' => $httpStatus,
            );
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            return array(
                'ok' => false,
                'status' => $httpStatus > 0 ? $httpStatus : 502,
                'message' => 'Resposta inválida do Claude.',
                'http_status' => $httpStatus,
                'response' => is_string($response) ? substr($response, 0, 500) : null,
            );
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            $mensagem = $this->extractErrorMessage($decoded);

            return array(
                'ok' => false,
                'status' => $httpStatus,
                'message' => $mensagem,
                'http_status' => $httpStatus,
                'response' => $decoded,
            );
        }

        return array(
            'ok' => true,
            'data' => $decoded,
            'http_status' => $httpStatus,
        );
    }

    private function extractErrorMessage(array $decoded)
    {
        foreach (array('error', 'message', 'detail') as $key) {
            if (!isset($decoded[$key])) {
                continue;
            }

            if (is_string($decoded[$key]) && trim($decoded[$key]) !== '') {
                return trim($decoded[$key]);
            }

            if (is_array($decoded[$key])) {
                if (isset($decoded[$key]['message']) && is_string($decoded[$key]['message']) && trim($decoded[$key]['message']) !== '') {
                    return trim($decoded[$key]['message']);
                }

                if (isset($decoded[$key]['type']) && is_string($decoded[$key]['type']) && trim($decoded[$key]['type']) !== '') {
                    return trim($decoded[$key]['type']);
                }
            }
        }

        return 'O Claude retornou um erro ao processar a requisição.';
    }

    private function extractTextFromContent(array $contentBlocks)
    {
        $partes = array();

        foreach ($contentBlocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            if (isset($block['text']) && is_string($block['text']) && trim($block['text']) !== '') {
                $partes[] = $block['text'];
            }
        }

        return trim(implode("\n", $partes));
    }

    private function normalizeMaxTokens($value)
    {
        if ($value === null || $value === '') {
            return 1024;
        }

        if (is_string($value) && !preg_match('/^\d+$/', trim($value))) {
            return 1024;
        }

        $value = (int) $value;
        if ($value < 1) {
            return 1;
        }

        if ($value > 8192) {
            return 8192;
        }

        return $value;
    }

    private function normalizeTemperature($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        if (!is_numeric($value)) {
            return null;
        }

        $value = (float) $value;
        if ($value < 0) {
            $value = 0;
        }

        if ($value > 1) {
            $value = 1;
        }

        return $value;
    }

    private function runtimeConfig()
    {
        $defaults = isset($this->config['claude']) && is_array($this->config['claude']) ? $this->config['claude'] : array();

        return array(
            'enabled' => !empty($defaults['enabled']),
            'api_key' => isset($defaults['api_key']) ? $defaults['api_key'] : '',
            'base_url' => isset($defaults['base_url']) ? $defaults['base_url'] : 'https://api.anthropic.com',
            'model' => isset($defaults['model']) ? $defaults['model'] : 'claude-sonnet-4-5-20250929',
            'max_tokens' => isset($defaults['max_tokens']) ? (int) $defaults['max_tokens'] : 1024,
            'temperature' => isset($defaults['temperature']) ? $defaults['temperature'] : '0.2',
            'timeout' => isset($defaults['timeout']) ? (int) $defaults['timeout'] : 30,
        );
    }
}

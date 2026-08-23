<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Services\OpenAIService;

/**
 * Diagnóstico administrativo da integração OpenAI.
 *
 * Existe para uma pergunta operacional: a chave e o modelo estão certos? É a
 * verificação que o roteiro de deploy faz ANTES de habilitar a Norminha com IA,
 * quando ainda dá para descobrir barato que o modelo foi digitado errado.
 *
 * NÃO É USADO PELA NORMINHA. A UI do aluno nunca chama esta rota; ela fala
 * exclusivamente com /api/norminha/chat. Misturar as duas daria ao aluno um
 * caminho para gastar token fora do rate limit.
 *
 * Protegido pela mesma permissão do teste Claude legado
 * (`configuracoes_globais.gerenciar`), como manda o padrão do projeto.
 */
class OpenAIController extends Controller
{
    const PROMPT_PADRAO = 'Responda apenas: OK.';

    private $service;

    public function __construct()
    {
        $this->service = new OpenAIService();
    }

    public function testar(Request $request)
    {
        $diagnostico = $this->service->diagnostico();

        if (!$this->service->isEnabled()) {
            return $this->json(array(
                'ok' => false,
                'erro' => 'desabilitado',
                'mensagem' => 'OPENAI_ENABLED está false. Nenhuma requisição foi feita.',
                'diagnostico' => $diagnostico,
            ), 503);
        }

        if (!$this->service->hasApiKey()) {
            return $this->json(array(
                'ok' => false,
                'erro' => 'sem_chave',
                'mensagem' => 'OPENAI_API_KEY não está configurada no .env.',
                'diagnostico' => $diagnostico,
            ), 503);
        }

        $mensagem = trim((string) $request->input('message', self::PROMPT_PADRAO));
        if ($mensagem === '' || mb_strlen($mensagem, 'UTF-8') > 500) {
            $mensagem = self::PROMPT_PADRAO;
        }

        $resultado = $this->service->gerar(array(
            'instructions' => 'Você é um teste de diagnóstico. Responda de forma curta.',
            'input' => array(
                array('role' => 'user', 'content' => $mensagem),
            ),
            'max_output_tokens' => 64,
        ));

        if (empty($resultado['ok'])) {
            return $this->json(array(
                'ok' => false,
                'erro' => $resultado['error_code'],
                // Mensagem interna: técnica, sem conteúdo de aluno e sem a chave.
                'mensagem' => isset($resultado['mensagem_interna'])
                    ? $resultado['mensagem_interna']
                    : 'Não foi possível consultar a OpenAI.',
                'latencia_ms' => $resultado['latencia_ms'],
                'diagnostico' => $diagnostico,
            ), (int) $resultado['status']);
        }

        return $this->json(array(
            'ok' => true,
            'model' => $resultado['model'],
            'response_id' => $resultado['response_id'],
            'message' => $resultado['message'],
            'usage' => $resultado['usage'],
            'latencia_ms' => $resultado['latencia_ms'],
            'diagnostico' => $diagnostico,
        ));
    }
}

<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\NorminhaConversa;
use App\Models\NorminhaFeedback;
use App\Models\NorminhaMensagem;
use App\Services\NorminhaRateLimitService;
use App\Services\NorminhaService;

/**
 * API da Norminha. Controller fino: valida forma, delega ao Service, traduz o
 * resultado em status HTTP. Nenhuma regra de negócio mora aqui.
 *
 * IDENTIDADE
 * usuario_id sai de Session::get('usuario_id') e de mais lugar nenhum. O corpo
 * da requisição é tratado como sugestão do navegador do começo ao fim; se algum
 * dia alguém mandar `usuario_id` no JSON, ele é simplesmente ignorado.
 *
 * PROTEÇÃO DAS ROTAS
 * `auth.api` devolve 401 em JSON (o `auth` comum redirecionaria, e um fetch()
 * leria o HTML do login como sucesso). O CSRF é injetado automaticamente por
 * $app->post() e, em caminhos /api/, também responde JSON.
 *
 * O TOKEN CSRF VIAJA NO CORPO
 * App\Core\Request decodifica application/json, então o cliente manda `_token`
 * dentro do próprio JSON — não é preciso cabeçalho customizado. Isto é o que a
 * Etapa 6 precisa saber para escrever o fetch().
 */
class NorminhaController extends Controller
{
    const LIMITE_HISTORICO = 50;
    const LIMITE_COMENTARIO = 1000;

    private $norminhaService;
    private $rateLimitService;
    private $conversaModel;
    private $mensagemModel;
    private $feedbackModel;

    public function __construct()
    {
        $this->norminhaService = new NorminhaService();
        $this->rateLimitService = new NorminhaRateLimitService();
        $this->conversaModel = new NorminhaConversa();
        $this->mensagemModel = new NorminhaMensagem();
        $this->feedbackModel = new NorminhaFeedback();
    }

    // =================================================================
    // POST /api/norminha/chat
    // =================================================================

    /**
     * Atendimento a quem NAO tem conta.
     *
     * Endpoint separado do /chat de proposito. O /chat exige sessao e responde
     * sobre matricula, progresso e certificado; misturar os dois num `if` faria
     * um caminho esquecido levar visitante anonimo ao ramo do aluno. Aqui nao ha
     * usuario, nao ha matricula e nao se consulta tabela de aluno nenhuma.
     *
     * Nada do que o visitante escreve e guardado. So um contador por origem,
     * com o IP em hash, para conter abuso.
     */
    public function publico(Request $request)
    {
        $payload = $this->corpo($request);
        if ($payload === null) {
            return $this->erro('corpo_invalido', 'Nao consegui ler os dados enviados.', 422);
        }

        $limite = (new \App\Services\NorminhaPublicoLimiteService())->registrar($request->ip());
        if (empty($limite['permitido'])) {
            return $this->respostaJson(array(
                'ok' => false,
                'erro' => 'limite_excedido',
                'mensagem' => 'Muitas mensagens seguidas. Aguarde um instante e tente de novo.',
            ), 429, array('Retry-After' => '60'));
        }

        $mensagem = isset($payload['message']) ? (string) $payload['message'] : '';
        $acao = isset($payload['action']) ? (string) $payload['action'] : '';

        // Mesmo teto de tamanho do chat do aluno: nada aqui justifica um texto
        // maior, e um campo sem limite e um convite.
        if (mb_strlen($mensagem) > 2000) {
            return $this->erro('mensagem_longa', 'Escreva de forma mais curta, por favor.', 422);
        }

        $resposta = (new \App\Services\NorminhaPublicoService())->responder($acao, $mensagem);

        return $this->respostaJson($resposta, 200);
    }

    public function chat(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');

        $payload = $this->corpo($request);
        if ($payload === null) {
            return $this->erro('corpo_invalido', 'Não consegui ler os dados enviados.', 422);
        }

        // Rate limit ANTES de qualquer trabalho: negar barato é o ponto.
        //
        // `action` é atalho determinístico — consulta ao banco, não token. Ele
        // entra com política mais folgada para não travar navegação normal.
        // Se a mensagem vier a consumir IA, o consumo é contabilizado depois,
        // pelo próprio orquestrador.
        $ehAcao = isset($payload['action']) && $payload['action'] !== null && trim((string) $payload['action']) !== '';
        $limite = $this->rateLimitService->registrarEVerificar($usuarioId, false, $ehAcao);
        if (empty($limite['permitido'])) {
            return $this->respostaJson(array(
                'ok' => false,
                'erro' => 'limite_excedido',
                'mensagem' => $this->rateLimitService->mensagemDeBloqueio($limite),
                'retry_after' => (int) $limite['retry_after'],
            ), 429, array('Retry-After' => (string) max(1, (int) $limite['retry_after'])));
        }

        $formato = $this->validarFormato($payload);
        if ($formato !== null) {
            return $formato;
        }

        try {
            $resultado = $this->norminhaService->processar($usuarioId, array(
                'message' => isset($payload['message']) ? $payload['message'] : null,
                'action' => isset($payload['action']) ? $payload['action'] : null,
                'conversation_id' => isset($payload['conversation_id']) ? $payload['conversation_id'] : null,
                'context' => isset($payload['context']) ? $payload['context'] : array(),
            ));
        } catch (\Throwable $e) {
            // Stack trace nunca chega ao navegador.
            Logger::error('norminha.chat.excecao', array(
                'usuario_id' => $usuarioId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ));

            return $this->erro('indisponivel', 'A Norminha está indisponível no momento. Tente novamente em instantes.', 503);
        }

        if (empty($resultado['ok'])) {
            $status = isset($resultado['status']) ? (int) $resultado['status'] : 422;
            unset($resultado['status']);

            return $this->respostaJson($resultado, $status);
        }

        return $this->respostaJson($resultado, 200);
    }

    // =================================================================
    // GET /api/norminha/historico
    // =================================================================

    public function historico(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');

        $uuid = trim((string) $request->query('conversation_id', ''));
        if ($uuid === '') {
            return $this->erro('conversation_id_ausente', 'Informe a conversa.', 422);
        }
        if (!$this->conversaModel->uuidValido($uuid)) {
            return $this->erro('conversation_id_invalido', 'Conversa não encontrada.', 422);
        }

        // Busca por uuid + usuario: uuid alheio devolve 403, nunca conteúdo.
        $conversa = $this->conversaModel->buscarPorUuid($uuid, $usuarioId);
        if (!$conversa) {
            return $this->erro('conversa_invalida', 'Conversa não encontrada.', 403);
        }

        $limite = (int) $request->query('limite', 30);
        $limite = max(1, min(self::LIMITE_HISTORICO, $limite));

        $mensagens = $this->mensagemModel->ultimasDaConversa(
            (int) $conversa['id'],
            $usuarioId,
            $limite,
            array('user', 'assistant')
        );

        // Só o que a interface precisa. Nada de payload de tool, prompt de
        // sistema, tokens, modelo ou id interno de provedor.
        $limpas = array();
        foreach ($mensagens as $m) {
            $limpas[] = array(
                'id' => (int) $m['id'],
                'papel' => $m['papel'],
                'mensagem' => $m['mensagem'],
                'intent' => $m['intencao'],
                'resolved_by' => $m['resolved_by'],
                'criado_em' => $m['created_at'],
            );
        }

        return $this->respostaJson(array(
            'ok' => true,
            'conversation_id' => $conversa['uuid'],
            'mensagens' => $limpas,
            'total' => count($limpas),
        ), 200);
    }

    // =================================================================
    // POST /api/norminha/feedback
    // =================================================================

    public function feedback(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');

        $payload = $this->corpo($request);
        if ($payload === null) {
            return $this->erro('corpo_invalido', 'Não consegui ler os dados enviados.', 422);
        }

        $mensagemId = isset($payload['message_id']) ? (int) $payload['message_id'] : 0;
        if ($mensagemId <= 0) {
            return $this->erro('message_id_invalido', 'Mensagem não encontrada.', 422);
        }

        if (!array_key_exists('useful', $payload)) {
            return $this->erro('useful_ausente', 'Informe se a resposta foi útil.', 422);
        }
        $util = filter_var($payload['useful'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($util === null) {
            return $this->erro('useful_invalido', 'Informe se a resposta foi útil.', 422);
        }

        $comentario = isset($payload['comment']) && $payload['comment'] !== null
            ? trim((string) $payload['comment']) : null;
        if ($comentario !== null && mb_strlen($comentario, 'UTF-8') > self::LIMITE_COMENTARIO) {
            return $this->erro('comentario_longo', 'Comentário muito longo.', 422);
        }

        // A propriedade é verificada aqui, na camada de serviço da requisição:
        // o Model de feedback não conhece a sessão e não deve fingir que conhece.
        $mensagem = $this->mensagemModel->buscarDoUsuario($mensagemId, $usuarioId);
        if (!$mensagem) {
            return $this->erro('mensagem_invalida', 'Mensagem não encontrada.', 403);
        }

        $this->feedbackModel->registrar($mensagemId, $usuarioId, $util, $comentario);

        return $this->respostaJson(array('ok' => true, 'message_id' => $mensagemId, 'useful' => (bool) $util), 200);
    }

    // =================================================================
    // Internos
    // =================================================================

    /** Aceita JSON e formulário; devolve null quando o corpo não é utilizável. */
    private function corpo(Request $request)
    {
        $corpo = $request->all();

        return is_array($corpo) ? $corpo : null;
    }

    /**
     * Validação de FORMA. As regras de conteúdo (tamanho de mensagem, enum de
     * ação) são do NorminhaService, que é quem responde por elas.
     */
    private function validarFormato(array $payload)
    {
        if (isset($payload['conversation_id']) && $payload['conversation_id'] !== null
            && trim((string) $payload['conversation_id']) !== ''
            && !$this->conversaModel->uuidValido(trim((string) $payload['conversation_id']))) {
            return $this->erro('conversation_id_invalido', 'Conversa não encontrada.', 422);
        }

        if (isset($payload['context'])) {
            if (!is_array($payload['context'])) {
                return $this->erro('context_invalido', 'Contexto inválido.', 422);
            }

            $permitidas = array('route', 'inscricao_id', 'curso_id', 'turma_id', 'modulo_id', 'item_id');
            foreach ($payload['context'] as $chave => $valor) {
                if (!in_array($chave, $permitidas, true)) {
                    return $this->erro('context_invalido', 'Contexto inválido.', 422);
                }
                if ($chave === 'route') {
                    continue;
                }
                if ($valor === null || $valor === '') {
                    continue;
                }
                if (!is_numeric($valor) || (int) $valor <= 0 || (string) (int) $valor !== (string) $valor) {
                    return $this->erro('context_invalido', 'Contexto inválido.', 422);
                }
            }
        }

        return null;
    }

    private function erro($codigo, $mensagem, $status)
    {
        return $this->respostaJson(array('ok' => false, 'erro' => $codigo, 'mensagem' => $mensagem), $status);
    }

    private function respostaJson(array $dados, $status, array $cabecalhos = array())
    {
        $cabecalhos['Content-Type'] = 'application/json; charset=utf-8';

        return new Response(
            json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            $cabecalhos
        );
    }
}

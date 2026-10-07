<?php

namespace App\Controllers\Api\App;

use App\Core\Request;
use App\Models\ConteudoQuizTentativa;
use App\Services\ConteudoCursoService;
use App\Services\ConteudoQuizService;
use App\Support\AppApi\QuizPresenter;

/**
 * Quiz do item (`item.tipo = quiz`): estado, iniciar/retomar, rascunho, tempo,
 * envio e resultado. Toda regra (sorteio, prazo, tentativas, correção) é do
 * ConteudoQuizService — o mesmo do site.
 */
class QuizController extends AppController
{
    private $quizService;

    public function __construct()
    {
        $this->quizService = new ConteudoQuizService();
    }

    public function estado(Request $request)
    {
        $ctx = $this->carregar($request);
        if (!is_array($ctx) || isset($ctx['erro'])) {
            return $ctx['erro'];
        }

        $quiz = $this->quizService->findQuizParaAluno($ctx['item_id'], $this->usuarioId(), $ctx['inscricao_id']);
        if (!$quiz) {
            return $this->naoEncontrado('Este quiz ainda não está disponível.');
        }

        return $this->ok(QuizPresenter::estado(
            $quiz,
            (array) ($quiz['estrutura'] ?? array()),
            !empty($quiz['tentativa_em_andamento']) ? $quiz['tentativa_em_andamento'] : null,
            $this->quizService->listarTentativasAluno((int) $quiz['id'], $ctx['inscricao_id']),
            !empty($quiz['pode_nova_tentativa'])
        ));
    }

    public function iniciar(Request $request)
    {
        $ctx = $this->carregar($request);
        if (!is_array($ctx) || isset($ctx['erro'])) {
            return $ctx['erro'];
        }

        $resultado = $this->quizService->iniciarOuRetomar(array(
            'quiz_id' => (int) $ctx['quiz']['id'],
            'inscricao_id' => $ctx['inscricao_id'],
            'aluno_id' => $this->usuarioId(),
            'curso_evento_id' => $ctx['curso_id'],
            'turma_id' => $ctx['turma_id'] > 0 ? $ctx['turma_id'] : null,
        ));
        if (empty($resultado['ok'])) {
            return $this->erro('quiz_indisponivel', $resultado['message'] ?? 'Não foi possível iniciar o quiz.', 422);
        }

        $dados = $this->quizService->obterTentativaParaAluno((int) $resultado['tentativa']['id'], $this->usuarioId());
        if (!$dados) {
            return $this->erro('erro_interno', null, 500);
        }

        return $this->ok(QuizPresenter::tentativaIniciada($dados['tentativa'], (array) $dados['perguntas'], $dados['tempo']));
    }

    public function rascunho(Request $request)
    {
        $ctx = $this->carregar($request);
        if (!is_array($ctx) || isset($ctx['erro'])) {
            return $ctx['erro'];
        }
        $tentativa = $this->tentativaDoContexto((int) $request->input('tentativa_id', 0), $ctx);
        if ($tentativa === null) {
            return $this->naoEncontrado('Tentativa não encontrada.');
        }

        list($objetivas, $discursivas) = QuizPresenter::respostasParaServico($request->input('respostas', array()));
        $resultado = $this->quizService->salvarRascunho(array(
            'tentativa_id' => (int) $tentativa['id'],
            'aluno_id' => $this->usuarioId(),
            'item_id' => $ctx['item_id'],
            'respostas' => $objetivas,
            'discursivas' => $discursivas,
        ));
        if (empty($resultado['ok'])) {
            return $this->erro('tentativa_encerrada', $resultado['message'] ?? 'Esta tentativa já foi enviada ou encerrada.', 422);
        }

        return $this->ok(array(
            'ok' => true,
            'segundos_restantes' => isset($resultado['tempo']['segundos_restantes']) ? (int) $resultado['tempo']['segundos_restantes'] : null,
        ));
    }

    public function tempo(Request $request)
    {
        $ctx = $this->carregar($request);
        if (!is_array($ctx) || isset($ctx['erro'])) {
            return $ctx['erro'];
        }
        $tentativa = $this->tentativaDoContexto((int) $request->query('tentativa_id', 0), $ctx);
        if ($tentativa === null) {
            return $this->naoEncontrado('Tentativa não encontrada.');
        }

        $dados = $this->quizService->consultarTempo((int) $tentativa['id'], $this->usuarioId());
        if (empty($dados['ok'])) {
            return $this->naoEncontrado('Tentativa não encontrada.');
        }

        $tempo = isset($dados['tempo']) && is_array($dados['tempo']) ? $dados['tempo'] : null;
        $expirada = !empty($dados['expirada']);

        return $this->ok(array(
            'segundos_restantes' => $tempo !== null && isset($tempo['segundos_restantes']) ? (int) $tempo['segundos_restantes'] : null,
            'expirada' => $expirada,
        ));
    }

    public function enviar(Request $request)
    {
        $ctx = $this->carregar($request);
        if (!is_array($ctx) || isset($ctx['erro'])) {
            return $ctx['erro'];
        }
        $tentativa = $this->tentativaDoContexto((int) $request->input('tentativa_id', 0), $ctx);
        if ($tentativa === null) {
            return $this->naoEncontrado('Tentativa não encontrada.');
        }

        list($objetivas, $discursivas) = QuizPresenter::respostasParaServico($request->input('respostas', array()));
        $resultado = $this->quizService->enviarTentativa(array(
            'tentativa_id' => (int) $tentativa['id'],
            'aluno_id' => $this->usuarioId(),
            'item_id' => $ctx['item_id'],
            'inscricao_id' => $ctx['inscricao_id'],
            'respostas' => $objetivas,
            'discursivas' => $discursivas,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ));
        if (empty($resultado['ok'])) {
            return $this->erro('validacao', $resultado['message'] ?? 'Não foi possível enviar o quiz.', 422, array());
        }

        return $this->resultadoDaTentativa((int) $tentativa['id']);
    }

    public function tentativa(Request $request)
    {
        $ctx = $this->carregar($request);
        if (!is_array($ctx) || isset($ctx['erro'])) {
            return $ctx['erro'];
        }
        $tentativa = $this->tentativaDoContexto((int) $request->route('tentativa', 0), $ctx);
        if ($tentativa === null || (string) $tentativa['status'] === 'em_andamento') {
            return $this->naoEncontrado('Resultado não encontrado.');
        }

        return $this->resultadoDaTentativa((int) $tentativa['id']);
    }

    private function resultadoDaTentativa($tentativaId)
    {
        $dados = $this->quizService->obterTentativaParaAluno($tentativaId, $this->usuarioId());
        if (!$dados) {
            return $this->naoEncontrado('Resultado não encontrado.');
        }

        return $this->ok(QuizPresenter::resultado($dados));
    }

    /**
     * Inscrição + item do quiz (publicado, do tipo quiz e configurado).
     *
     * @return array contexto ou ['erro' => Response]
     */
    private function carregar(Request $request)
    {
        $contexto = $this->inscricaoDoAluno($request);
        if ($contexto === null) {
            return array('erro' => $this->semAcesso());
        }
        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $contexto['curso_id'];
        $turmaId = (int) $contexto['turma_id'];
        $itemId = (int) $request->route('item', 0);

        $detalhe = (new ConteudoCursoService())->buscarItemPublicadoParaAluno($itemId, $this->usuarioId(), (int) $inscricao['id'], $cursoId, $turmaId > 0 ? $turmaId : null);
        if (empty($detalhe['ok']) || (string) ($detalhe['item']['tipo'] ?? '') !== 'quiz') {
            return array('erro' => $this->naoEncontrado('Quiz não encontrado.'));
        }
        if (empty($detalhe['detalhe'])) {
            return array('erro' => $this->naoEncontrado('Este quiz ainda não tem perguntas configuradas.'));
        }

        return array(
            'inscricao_id' => (int) $inscricao['id'],
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
            'item_id' => $itemId,
            'quiz' => $detalhe['detalhe'],
        );
    }

    /** Tentativa do aluno, desta inscrição e deste quiz. */
    private function tentativaDoContexto($tentativaId, array $ctx)
    {
        if ($tentativaId <= 0) {
            return null;
        }
        $tentativa = (new ConteudoQuizTentativa())->findById($tentativaId);
        if (!$tentativa
            || (int) $tentativa['aluno_id'] !== $this->usuarioId()
            || (int) $tentativa['inscricao_id'] !== (int) $ctx['inscricao_id']
            || (int) $tentativa['quiz_id'] !== (int) $ctx['quiz']['id']) {
            return null;
        }

        return $tentativa;
    }
}

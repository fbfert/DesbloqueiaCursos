<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AreaCursoService;
use App\Services\ConteudoCursoService;
use App\Services\ConteudoQuizService;

class QuizController extends Controller
{
    private $areaCursoService;
    private $conteudoService;
    private $quizService;

    public function __construct()
    {
        $this->areaCursoService = new AreaCursoService();
        $this->conteudoService  = new ConteudoCursoService();
        $this->quizService      = new ConteudoQuizService();
    }

    public function iniciar(Request $request)
    {
        $itemId      = (int) $request->input('item_id', 0);
        $inscricaoId = (int) $request->input('inscricao_id', 0);
        $cursoId     = (int) $request->input('curso_id', 0);
        $turmaId     = (int) $request->input('turma_id', 0);
        $moduloId    = (int) $request->input('modulo_id', 0);

        $contexto = $this->areaCursoService->carregarAluno(
            Session::get('usuario_id'), $inscricaoId, 0, 0,
            $cursoId > 0 ? $cursoId : null,
            $turmaId > 0 ? $turmaId : null, null
        );
        if (empty($contexto['inscricao'])) {
            Session::flash('errors', array('Acesso negado.'));
            return $this->redirect('/aluno/meus-cursos');
        }

        $inscricao = $contexto['inscricao'];
        $detalhe   = $this->conteudoService->buscarItemPublicadoParaAluno(
            $itemId, (int) Session::get('usuario_id'),
            (int) $inscricao['id'], (int) $inscricao['curso_evento_id'],
            !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null
        );

        if (empty($detalhe['ok']) || (string) ($detalhe['item']['tipo'] ?? '') !== 'quiz') {
            Session::flash('errors', array('Quiz não encontrado.'));
            return $this->redirect('/aluno/meus-cursos');
        }

        $quiz = $detalhe['detalhe'];
        if (!$quiz) {
            Session::flash('errors', array('Este conteúdo ainda não tem perguntas configuradas.'));
            return $this->redirectConteudo($inscricao, $moduloId, $itemId);
        }

        $resultado = $this->quizService->iniciarOuRetomar(array(
            'quiz_id'        => (int) $quiz['id'],
            'inscricao_id'   => (int) $inscricao['id'],
            'aluno_id'       => (int) Session::get('usuario_id'),
            'curso_evento_id'=> (int) $inscricao['curso_evento_id'],
            'turma_id'       => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
        ));

        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível iniciar o quiz.'));
            return $this->redirectConteudo($inscricao, $moduloId, $itemId);
        }

        Session::flash('quiz_tentativa_id', (int) $resultado['tentativa']['id']);
        return $this->redirectConteudo($inscricao, $moduloId, $itemId);
    }

    public function salvarRascunho(Request $request)
    {
        $tentativaId = (int) $request->input('tentativa_id', 0);
        $itemId      = (int) $request->input('item_id', 0);
        $inscricaoId = (int) $request->input('inscricao_id', 0);
        $cursoId     = (int) $request->input('curso_id', 0);
        $turmaId     = (int) $request->input('turma_id', 0);
        $moduloId    = (int) $request->input('modulo_id', 0);
        $respostas   = $request->input('respostas', array());
        $discursivas = $request->input('discursivas', array());
        $revisoes    = $request->input('revisoes', array());
        if (!is_array($respostas)) {
            $respostas = array();
        }
        if (!is_array($discursivas)) {
            $discursivas = array();
        }
        if (!is_array($revisoes)) {
            $revisoes = array();
        }

        $contexto = $this->areaCursoService->carregarAluno(
            Session::get('usuario_id'), $inscricaoId, 0, 0,
            $cursoId > 0 ? $cursoId : null,
            $turmaId > 0 ? $turmaId : null, null
        );
        if (empty($contexto['inscricao'])) {
            return $this->json(array('ok' => false, 'message' => 'Acesso negado.'));
        }

        $resultado = $this->quizService->salvarRascunho(array(
            'tentativa_id' => $tentativaId,
            'aluno_id'     => (int) Session::get('usuario_id'),
            'item_id'      => $itemId,
            'respostas'    => $respostas,
            'discursivas'  => $discursivas,
            'revisoes'     => $revisoes,
        ));

        return $this->json($resultado);
    }

    /**
     * Marca/desmarca uma questão para revisão, sem alterar a resposta.
     */
    public function marcarRevisao(Request $request)
    {
        $resultado = $this->quizService->marcarParaRevisao(array(
            'tentativa_id' => (int) $request->input('tentativa_id', 0),
            'aluno_id'     => (int) Session::get('usuario_id'),
            'pergunta_id'  => (int) $request->input('pergunta_id', 0),
            'marcada'      => (bool) $request->input('marcada', false),
        ));

        return $this->json($resultado);
    }

    /**
     * Tempo restante conferido no servidor (o relógio do navegador é apenas
     * visual). Quando o prazo vence, aplica a regra configurada no quiz.
     */
    public function tempo(Request $request)
    {
        $tentativaId = (int) $request->input('tentativa_id', 0);
        $alunoId     = (int) Session::get('usuario_id');

        $dados = $this->quizService->consultarTempo($tentativaId, $alunoId);
        if (empty($dados['ok'])) {
            return $this->json($dados);
        }

        return $this->json($dados);
    }

    public function enviar(Request $request)
    {
        $tentativaId = (int) $request->input('tentativa_id', 0);
        $itemId      = (int) $request->input('item_id', 0);
        $inscricaoId = (int) $request->input('inscricao_id', 0);
        $cursoId     = (int) $request->input('curso_id', 0);
        $turmaId     = (int) $request->input('turma_id', 0);
        $moduloId    = (int) $request->input('modulo_id', 0);
        $respostas   = $request->input('respostas', array());
        $discursivas = $request->input('discursivas', array());
        if (!is_array($respostas)) {
            $respostas = array();
        }
        if (!is_array($discursivas)) {
            $discursivas = array();
        }

        $contexto = $this->areaCursoService->carregarAluno(
            Session::get('usuario_id'), $inscricaoId, 0, 0,
            $cursoId > 0 ? $cursoId : null,
            $turmaId > 0 ? $turmaId : null, null
        );
        if (empty($contexto['inscricao'])) {
            Session::flash('errors', array('Acesso negado.'));
            return $this->redirect('/aluno/meus-cursos');
        }

        $inscricao = $contexto['inscricao'];

        $resultado = $this->quizService->enviarTentativa(array(
            'tentativa_id'   => $tentativaId,
            'aluno_id'       => (int) Session::get('usuario_id'),
            'item_id'        => $itemId,
            'inscricao_id'   => (int) $inscricao['id'],
            'respostas'      => $respostas,
            'discursivas'    => $discursivas,
            'ip'             => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ));

        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível enviar o quiz.'));
            return $this->redirectConteudo($inscricao, $moduloId, $itemId);
        }

        if (!empty($resultado['automatico'])) {
            Session::flash('success', 'O tempo da prova terminou. As respostas salvas foram enviadas automaticamente.');
        } else {
            Session::flash('success', 'Prova enviada com sucesso.');
        }
        Session::flash('quiz_tentativa_id_resultado', $tentativaId);
        return $this->redirectConteudo($inscricao, $moduloId, $itemId);
    }

    public function resultado(Request $request)
    {
        $tentativaId = (int) $request->query('tentativa_id', 0);
        $alunoId     = (int) Session::get('usuario_id');

        $dados = $this->quizService->obterTentativaParaAluno($tentativaId, $alunoId);
        if (!$dados) {
            return $this->redirect('/aluno/meus-cursos');
        }

        return $this->view('aluno/curso/quiz_resultado', array(
            'title'    => 'Resultado do Quiz',
            'dados'    => $dados,
            'tentativa'=> $dados['tentativa'],
            'quiz'     => $dados['quiz'],
            'perguntas'=> $dados['perguntas'],
        ));
    }

    private function redirectConteudo(array $inscricao, $moduloId, $itemId)
    {
        $inscricaoId = (int) $inscricao['id'];
        $cursoId     = (int) $inscricao['curso_evento_id'];
        $turmaId     = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;
        return $this->redirect(
            '/aluno/curso/' . $inscricaoId . '/' . $cursoId . '/' . $turmaId
            . '/modulo/' . (int) $moduloId . '/conteudo/' . (int) $itemId
        );
    }
}

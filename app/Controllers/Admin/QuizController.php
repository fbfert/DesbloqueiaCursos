<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ConteudoCursoService;
use App\Services\ConteudoQuizService;

class QuizController extends Controller
{
    private $conteudoService;
    private $quizService;

    public function __construct()
    {
        $this->conteudoService = new ConteudoCursoService();
        $this->quizService     = new ConteudoQuizService();
    }

    public function editarPerguntas(Request $request)
    {
        $itemId  = (int) $request->query('item_id', 0);
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $moduloId = (int) $request->query('modulo_id', 0);

        if ($itemId <= 0 || $cursoId <= 0) {
            Session::flash('errors', array('Parâmetros inválidos.'));
            return $this->redirect('/admin/area-curso?curso_id=' . $cursoId . '&aba=conteudo');
        }

        $detalhe = $this->conteudoService->detalharItem($itemId, $cursoId);
        if (empty($detalhe['ok']) || (string) ($detalhe['item']['tipo'] ?? '') !== 'quiz') {
            Session::flash('errors', array('Quiz não encontrado.'));
            return $this->redirect('/admin/area-curso?curso_id=' . $cursoId . '&aba=conteudo');
        }

        $quiz = $this->quizService->findQuizCompleto($itemId);
        $validacao = $quiz ? $this->quizService->validarQuiz((int) $quiz['id']) : array('ok' => false, 'erros' => array('Quiz não configurado ainda.'));

        return $this->view('admin/area-curso/quiz_perguntas', array(
            'title'     => 'Editar perguntas do quiz: ' . $detalhe['item']['titulo'],
            'success'   => Session::pullFlash('success'),
            'errors'    => Session::pullFlash('errors', array()),
            'item'      => $detalhe['item'],
            'quiz'      => $quiz,
            'validacao' => $validacao,
            'curso_id'  => $cursoId,
            'turma_id'  => $turmaId,
            'modulo_id' => $moduloId,
        ));
    }

    public function salvarPergunta(Request $request)
    {
        $itemId   = (int) $request->input('item_id', 0);
        $cursoId  = (int) $request->input('curso_id', 0);
        $turmaId  = (int) $request->input('turma_id', 0);
        $moduloId = (int) $request->input('modulo_id', 0);

        $detalhe = $this->conteudoService->detalharItem($itemId, $cursoId);
        if (empty($detalhe['ok']) || (string) ($detalhe['item']['tipo'] ?? '') !== 'quiz') {
            Session::flash('errors', array('Quiz não encontrado.'));
            return $this->redirectPerguntas($cursoId, $turmaId, $moduloId, $itemId);
        }

        $quiz = $this->quizService->findQuizCompleto($itemId);
        if (!$quiz) {
            Session::flash('errors', array('Configure o quiz antes de adicionar perguntas.'));
            return $this->redirectPerguntas($cursoId, $turmaId, $moduloId, $itemId);
        }

        $alternativas = array();
        $altTextos   = $request->input('alternativa_texto', array());
        $altCorretas = $request->input('alternativa_correta', 0);
        $altIds      = $request->input('alternativa_id', array());
        $altOrdens   = $request->input('alternativa_ordem', array());
        if (!is_array($altTextos)) {
            $altTextos = array();
        }
        if (!is_array($altOrdens)) {
            $altOrdens = array();
        }

        foreach ($altTextos as $idx => $texto) {
            $texto = trim((string) $texto);
            if ($texto === '') {
                continue;
            }
            $alternativas[] = array(
                'id'     => isset($altIds[$idx]) ? (int) $altIds[$idx] : 0,
                'texto'  => $texto,
                'correta'=> (string) $altCorretas === (string) $idx ? 1 : 0,
                'ordem'  => isset($altOrdens[$idx]) && $altOrdens[$idx] !== '' ? (int) $altOrdens[$idx] : ($idx + 1),
            );
        }

        $resultado = $this->quizService->salvarPergunta(array(
            'id'          => (int) $request->input('pergunta_id', 0),
            'quiz_id'     => (int) $quiz['id'],
            'enunciado'   => (string) $request->input('enunciado', ''),
            'explicacao'  => (string) $request->input('explicacao', ''),
            'peso'        => $request->input('peso', 1),
            'obrigatoria' => !empty($request->input('obrigatoria')),
            'alternativas'=> $alternativas,
        ));

        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível salvar a pergunta.'));
        } else {
            Session::flash('success', 'Pergunta salva com sucesso.');
        }

        return $this->redirectPerguntas($cursoId, $turmaId, $moduloId, $itemId);
    }

    public function excluirPergunta(Request $request)
    {
        $itemId    = (int) $request->input('item_id', 0);
        $cursoId   = (int) $request->input('curso_id', 0);
        $turmaId   = (int) $request->input('turma_id', 0);
        $moduloId  = (int) $request->input('modulo_id', 0);
        $perguntaId= (int) $request->input('pergunta_id', 0);

        $quiz = $this->quizService->findQuizCompleto($itemId);
        if (!$quiz) {
            Session::flash('errors', array('Quiz não encontrado.'));
            return $this->redirectPerguntas($cursoId, $turmaId, $moduloId, $itemId);
        }

        $resultado = $this->quizService->excluirPergunta($perguntaId, (int) $quiz['id'], (int) Session::get('usuario_id'));
        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível excluir a pergunta.'));
        } else {
            Session::flash('success', 'Pergunta excluída.');
        }

        return $this->redirectPerguntas($cursoId, $turmaId, $moduloId, $itemId);
    }

    public function reordenarPerguntas(Request $request)
    {
        $itemId  = (int) $request->input('item_id', 0);
        $cursoId = (int) $request->input('curso_id', 0);
        $ordens  = $request->input('ordens', array());
        if (!is_array($ordens)) {
            $ordens = array();
        }

        $quiz = $this->quizService->findQuizCompleto($itemId);
        if (!$quiz) {
            return $this->json(array('ok' => false, 'message' => 'Quiz não encontrado.'));
        }

        $resultado = $this->quizService->reordenarPerguntas((int) $quiz['id'], $ordens);
        return $this->json($resultado);
    }

    public function resultados(Request $request)
    {
        $itemId  = (int) $request->query('item_id', 0);
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $alunoId = (int) $request->query('aluno_id', 0);
        $status  = trim((string) $request->query('status', ''));
        $aprovado = $request->query('aprovado', '');

        $detalhe = $this->conteudoService->detalharItem($itemId, $cursoId);
        if (empty($detalhe['ok'])) {
            Session::flash('errors', array('Item não encontrado.'));
            return $this->redirect('/admin/area-curso?curso_id=' . $cursoId . '&aba=conteudo');
        }

        $quiz = $this->quizService->findQuizCompleto($itemId);
        $resultados = array();
        if ($quiz) {
            $filtros = array();
            if ($alunoId > 0) {
                $filtros['aluno_id'] = $alunoId;
            }
            if ($status !== '') {
                $filtros['status'] = $status;
            }
            if ($aprovado !== '') {
                $filtros['aprovado'] = (int) $aprovado;
            }
            $resultados = $this->quizService->resumoResultadosQuizAdmin(
                $itemId, $cursoId, $turmaId > 0 ? $turmaId : null, $filtros
            );
        }

        return $this->view('admin/area-curso/quiz_resultados', array(
            'title'     => 'Resultados: ' . $detalhe['item']['titulo'],
            'success'   => Session::pullFlash('success'),
            'errors'    => Session::pullFlash('errors', array()),
            'item'      => $detalhe['item'],
            'quiz'      => $quiz,
            'resultados'=> $resultados,
            'curso_id'  => $cursoId,
            'turma_id'  => $turmaId,
            'filtros'   => array(
                'aluno_id' => $alunoId,
                'status'   => $status,
                'aprovado' => $aprovado,
            ),
        ));
    }

    public function resetarAluno(Request $request)
    {
        $itemId      = (int) $request->input('item_id', 0);
        $alunoId     = (int) $request->input('aluno_id', 0);
        $inscricaoId = (int) $request->input('inscricao_id', 0);
        $cursoId     = (int) $request->input('curso_id', 0);
        $turmaId     = (int) $request->input('turma_id', 0);

        if ($itemId <= 0 || $alunoId <= 0 || $inscricaoId <= 0) {
            Session::flash('errors', array('Parâmetros inválidos para reset.'));
            return $this->redirect('/admin/area-curso?curso_id=' . $cursoId . '&aba=relatorios');
        }

        $resultado = $this->quizService->resetarProgressoQuizAluno(
            $itemId, $alunoId, $inscricaoId,
            (int) Session::get('usuario_id'),
            $request->ip(), $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível redefinir o progresso.'));
        } else {
            Session::flash('success', 'Progresso do aluno redefinido. O histórico de tentativas foi preservado.');
        }

        return $this->redirect(
            '/admin/area-curso/conteudo/quiz/resultados?item_id=' . $itemId
            . '&curso_id=' . $cursoId . ($turmaId > 0 ? '&turma_id=' . $turmaId : '')
        );
    }

    public function preview(Request $request)
    {
        $itemId  = (int) $request->query('item_id', 0);
        $cursoId = (int) $request->query('curso_id', 0);

        $detalhe = $this->conteudoService->detalharItem($itemId, $cursoId);
        if (empty($detalhe['ok']) || (string) ($detalhe['item']['tipo'] ?? '') !== 'quiz') {
            Session::flash('errors', array('Quiz não encontrado.'));
            return $this->redirect('/admin/area-curso?curso_id=' . $cursoId . '&aba=conteudo');
        }

        $quiz = $this->quizService->findQuizCompleto($itemId);
        // Remover 'correta' das alternativas para simular a visão do aluno
        $perguntasPreview = array();
        if ($quiz && !empty($quiz['perguntas'])) {
            foreach ($quiz['perguntas'] as $p) {
                $alts = $p['alternativas'] ?? array();
                foreach ($alts as &$alt) {
                    unset($alt['correta']);
                }
                unset($alt);
                $p['alternativas'] = $alts;
                $perguntasPreview[] = $p;
            }
        }

        return $this->view('admin/area-curso/quiz_preview', array(
            'title'     => 'Pré-visualização: ' . $detalhe['item']['titulo'],
            'item'      => $detalhe['item'],
            'quiz'      => $quiz,
            'perguntas' => $perguntasPreview,
            'curso_id'  => $cursoId,
            'is_preview'=> true,
        ));
    }

    private function redirectPerguntas($cursoId, $turmaId, $moduloId, $itemId)
    {
        $url = '/admin/area-curso/conteudo/quiz/perguntas?item_id=' . $itemId . '&curso_id=' . $cursoId;
        if ($turmaId > 0) {
            $url .= '&turma_id=' . $turmaId;
        }
        if ($moduloId > 0) {
            $url .= '&modulo_id=' . $moduloId;
        }
        return $this->redirect($url);
    }
}

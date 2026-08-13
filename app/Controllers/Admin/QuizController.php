<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ConteudoCursoService;
use App\Services\ConteudoQuizBlocoService;
use App\Services\ConteudoQuizDiscursivaService;
use App\Services\ConteudoQuizService;

class QuizController extends Controller
{
    private $conteudoService;
    private $quizService;
    private $blocoService;
    private $discursivaService;

    public function __construct()
    {
        $this->conteudoService   = new ConteudoCursoService();
        $this->quizService       = new ConteudoQuizService();
        $this->blocoService      = new ConteudoQuizBlocoService();
        $this->discursivaService = new ConteudoQuizDiscursivaService();
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
        $validacao = $quiz ? $this->quizService->validarQuiz((int) $quiz['id']) : array('ok' => false, 'erros' => array('Quiz não configurado ainda.'), 'alertas' => array());

        // Filtros do banco de questões
        $filtros = array(
            'bloco_id'    => (string) $request->query('f_bloco', ''),
            'dificuldade' => trim((string) $request->query('f_dificuldade', '')),
            'tema'        => trim((string) $request->query('f_tema', '')),
            'tipo'        => trim((string) $request->query('f_tipo', '')),
            'status'      => trim((string) $request->query('f_status', '')),
            'busca'       => trim((string) $request->query('f_busca', '')),
        );

        $blocos    = array();
        $temas     = array();
        $perguntas = array();
        if ($quiz) {
            $blocos    = $this->blocoService->listarComResumo((int) $quiz['id']);
            $temas     = $this->quizService->listarTemas((int) $quiz['id']);
            $perguntas = $this->quizService->listarBancoQuestoes((int) $quiz['id'], $filtros);
        }

        return $this->view('admin/area-curso/quiz_perguntas', array(
            'title'      => 'Editar perguntas do quiz: ' . $detalhe['item']['titulo'],
            'success'    => Session::pullFlash('success'),
            'errors'     => Session::pullFlash('errors', array()),
            'item'       => $detalhe['item'],
            'quiz'       => $quiz,
            'validacao'  => $validacao,
            'blocos'     => $blocos,
            'temas'      => $temas,
            'perguntas'  => $perguntas,
            'filtros'    => $filtros,
            'curso_id'   => $cursoId,
            'turma_id'   => $turmaId,
            'modulo_id'  => $moduloId,
        ));
    }

    // ------------------------------------------------------------------
    // BLOCOS DE SORTEIO
    // ------------------------------------------------------------------

    public function blocos(Request $request)
    {
        $itemId   = (int) $request->query('item_id', 0);
        $cursoId  = (int) $request->query('curso_id', 0);
        $turmaId  = (int) $request->query('turma_id', 0);
        $moduloId = (int) $request->query('modulo_id', 0);

        $detalhe = $this->conteudoService->detalharItem($itemId, $cursoId);
        if (empty($detalhe['ok']) || (string) ($detalhe['item']['tipo'] ?? '') !== 'quiz') {
            Session::flash('errors', array('Quiz não encontrado.'));
            return $this->redirect('/admin/area-curso?curso_id=' . $cursoId . '&aba=conteudo');
        }

        $quiz = $this->quizService->findQuizCompleto($itemId);
        if (!$quiz) {
            Session::flash('errors', array('Configure o quiz antes de criar blocos.'));
            return $this->redirect('/admin/area-curso?curso_id=' . $cursoId . '&aba=conteudo');
        }

        return $this->view('admin/area-curso/quiz_blocos', array(
            'title'      => 'Blocos de sorteio: ' . $detalhe['item']['titulo'],
            'success'    => Session::pullFlash('success'),
            'errors'     => Session::pullFlash('errors', array()),
            'item'       => $detalhe['item'],
            'quiz'       => $quiz,
            'blocos'     => $this->blocoService->listarComResumo((int) $quiz['id']),
            'composicao' => $this->quizService->validarComposicao((int) $quiz['id']),
            'curso_id'   => $cursoId,
            'turma_id'   => $turmaId,
            'modulo_id'  => $moduloId,
        ));
    }

    public function salvarBloco(Request $request)
    {
        $itemId   = (int) $request->input('item_id', 0);
        $cursoId  = (int) $request->input('curso_id', 0);
        $turmaId  = (int) $request->input('turma_id', 0);
        $moduloId = (int) $request->input('modulo_id', 0);

        $quiz = $this->quizService->findQuizCompleto($itemId);
        if (!$quiz) {
            Session::flash('errors', array('Quiz não encontrado.'));
            return $this->redirectBlocos($cursoId, $turmaId, $moduloId, $itemId);
        }

        $resultado = $this->blocoService->salvar(array(
            'id'                     => (int) $request->input('bloco_id', 0),
            'quiz_id'                => (int) $quiz['id'],
            'codigo'                 => (string) $request->input('codigo', ''),
            'titulo'                 => (string) $request->input('titulo', ''),
            'descricao'              => (string) $request->input('descricao', ''),
            'tipo_questao'           => (string) $request->input('tipo_questao', 'multipla_escolha'),
            'quantidade_sortear'     => (int) $request->input('quantidade_sortear', 0),
            'conta_para_percentual'  => !empty($request->input('conta_para_percentual')) ? 1 : 0,
            'obrigatorio_para_envio' => !empty($request->input('obrigatorio_para_envio')) ? 1 : 0,
            'status'                 => (string) $request->input('status', 'ativo'),
            'ordem'                  => $request->input('ordem', ''),
            'usar_distribuicao'      => !empty($request->input('usar_distribuicao')) ? 1 : 0,
            'distribuicao_facil'     => $request->input('distribuicao_facil', ''),
            'distribuicao_media'     => $request->input('distribuicao_media', ''),
            'distribuicao_dificil'   => $request->input('distribuicao_dificil', ''),
        ), (int) Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível salvar o bloco.'));
        } else {
            Session::flash('success', 'Bloco salvo com sucesso.');
        }

        return $this->redirectBlocos($cursoId, $turmaId, $moduloId, $itemId);
    }

    public function excluirBloco(Request $request)
    {
        $itemId   = (int) $request->input('item_id', 0);
        $cursoId  = (int) $request->input('curso_id', 0);
        $turmaId  = (int) $request->input('turma_id', 0);
        $moduloId = (int) $request->input('modulo_id', 0);

        $quiz = $this->quizService->findQuizCompleto($itemId);
        if (!$quiz) {
            Session::flash('errors', array('Quiz não encontrado.'));
            return $this->redirectBlocos($cursoId, $turmaId, $moduloId, $itemId);
        }

        $resultado = $this->blocoService->excluir(
            (int) $request->input('bloco_id', 0),
            (int) $quiz['id'],
            (string) $request->input('justificativa', ''),
            (int) Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível excluir o bloco.'));
        } else {
            Session::flash('success', 'Bloco enviado para a lixeira.');
        }

        return $this->redirectBlocos($cursoId, $turmaId, $moduloId, $itemId);
    }

    public function alternarStatusBloco(Request $request)
    {
        $itemId   = (int) $request->input('item_id', 0);
        $cursoId  = (int) $request->input('curso_id', 0);
        $turmaId  = (int) $request->input('turma_id', 0);
        $moduloId = (int) $request->input('modulo_id', 0);

        $quiz = $this->quizService->findQuizCompleto($itemId);
        if (!$quiz) {
            Session::flash('errors', array('Quiz não encontrado.'));
            return $this->redirectBlocos($cursoId, $turmaId, $moduloId, $itemId);
        }

        $resultado = $this->blocoService->alternarStatus(
            (int) $request->input('bloco_id', 0),
            (int) $quiz['id'],
            (int) Session::get('usuario_id')
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível alterar o bloco.'));
        } else {
            Session::flash('success', $resultado['status'] === 'ativo' ? 'Bloco ativado.' : 'Bloco desativado.');
        }

        return $this->redirectBlocos($cursoId, $turmaId, $moduloId, $itemId);
    }

    public function reordenarBlocos(Request $request)
    {
        $itemId = (int) $request->input('item_id', 0);
        $ordens = $request->input('ordens', array());
        if (!is_array($ordens)) {
            $ordens = array();
        }

        $quiz = $this->quizService->findQuizCompleto($itemId);
        if (!$quiz) {
            return $this->json(array('ok' => false, 'message' => 'Quiz não encontrado.'));
        }

        return $this->json($this->blocoService->reordenar((int) $quiz['id'], $ordens));
    }

    // ------------------------------------------------------------------
    // FILA DE CORREÇÃO DAS DISCURSIVAS
    // ------------------------------------------------------------------

    public function discursivas(Request $request)
    {
        $itemId  = (int) $request->query('item_id', 0);
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $status  = trim((string) $request->query('status', 'pendente'));

        $detalhe = $this->conteudoService->detalharItem($itemId, $cursoId);
        if (empty($detalhe['ok'])) {
            Session::flash('errors', array('Item não encontrado.'));
            return $this->redirect('/admin/area-curso?curso_id=' . $cursoId . '&aba=conteudo');
        }

        $quiz = $this->quizService->findQuizCompleto($itemId);
        $fila = array();
        if ($quiz) {
            $filtros = array();
            if ($status !== '' && $status !== 'todas') {
                $filtros['status'] = $status;
            }
            if ($turmaId > 0) {
                $filtros['turma_id'] = $turmaId;
            }
            $fila = $this->discursivaService->listarFila((int) $quiz['id'], $cursoId, $filtros);
        }

        return $this->view('admin/area-curso/quiz_discursivas', array(
            'title'     => 'Discursivas: ' . $detalhe['item']['titulo'],
            'success'   => Session::pullFlash('success'),
            'errors'    => Session::pullFlash('errors', array()),
            'item'      => $detalhe['item'],
            'quiz'      => $quiz,
            'fila'      => $fila,
            'pendentes' => $quiz ? $this->discursivaService->contarPendentes((int) $quiz['id'], $cursoId, $turmaId > 0 ? $turmaId : null) : 0,
            'status'    => $status,
            'curso_id'  => $cursoId,
            'turma_id'  => $turmaId,
        ));
    }

    public function corrigirDiscursiva(Request $request)
    {
        $itemId  = (int) $request->input('item_id', 0);
        $cursoId = (int) $request->input('curso_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);

        $resultado = $this->discursivaService->corrigir(array(
            'correcao_id' => (int) $request->input('correcao_id', 0),
            'nota'        => $request->input('nota', null),
            'rubrica'     => (string) $request->input('rubrica', ''),
            'feedback'    => (string) $request->input('feedback', ''),
            'usuario_id'  => (int) Session::get('usuario_id'),
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ));

        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível registrar a correção.'));
        } else {
            Session::flash('success', 'Correção registrada.');
        }

        return $this->redirect(
            '/admin/area-curso/conteudo/quiz/discursivas?item_id=' . $itemId
            . '&curso_id=' . $cursoId . ($turmaId > 0 ? '&turma_id=' . $turmaId : '')
        );
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
            // Banco de questões
            'bloco_id'    => $request->input('bloco_id', ''),
            'tipo'        => (string) $request->input('tipo', 'multipla_escolha'),
            'dificuldade' => (string) $request->input('dificuldade', 'media'),
            'tema'        => (string) $request->input('tema', ''),
            'status'      => (string) $request->input('status_questao', 'ativo'),
            'referencia'  => (string) $request->input('referencia', ''),
            'rubrica'     => (string) $request->input('rubrica', ''),
            'nota_maxima' => $request->input('nota_maxima', ''),
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
            $tentativas = $this->quizService->detalharTentativasAdmin(
                (int) $quiz['id'], $cursoId, $turmaId > 0 ? $turmaId : null, $filtros
            );
            $pendentesDiscursiva = $this->discursivaService->contarPendentes(
                (int) $quiz['id'], $cursoId, $turmaId > 0 ? $turmaId : null
            );
        }

        return $this->view('admin/area-curso/quiz_resultados', array(
            'title'      => 'Resultados: ' . $detalhe['item']['titulo'],
            'success'    => Session::pullFlash('success'),
            'errors'     => Session::pullFlash('errors', array()),
            'item'       => $detalhe['item'],
            'quiz'       => $quiz,
            'resultados' => $resultados,
            'tentativas' => isset($tentativas) ? $tentativas : array(),
            'pendentes_discursiva' => isset($pendentesDiscursiva) ? $pendentesDiscursiva : 0,
            'curso_id'   => $cursoId,
            'turma_id'   => $turmaId,
            'filtros'    => array(
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
        return $this->redirect($this->montarUrlQuiz('perguntas', $cursoId, $turmaId, $moduloId, $itemId));
    }

    private function redirectBlocos($cursoId, $turmaId, $moduloId, $itemId)
    {
        return $this->redirect($this->montarUrlQuiz('blocos', $cursoId, $turmaId, $moduloId, $itemId));
    }

    private function montarUrlQuiz($rota, $cursoId, $turmaId, $moduloId, $itemId)
    {
        $url = '/admin/area-curso/conteudo/quiz/' . $rota . '?item_id=' . (int) $itemId . '&curso_id=' . (int) $cursoId;
        if ((int) $turmaId > 0) {
            $url .= '&turma_id=' . (int) $turmaId;
        }
        if ((int) $moduloId > 0) {
            $url .= '&modulo_id=' . (int) $moduloId;
        }
        return $url;
    }

    /**
     * Garante que o item pertence ao curso e é um quiz configurado.
     *
     * @return array|null
     */
    private function quizGuardado($itemId, $cursoId)
    {
        $detalhe = $this->conteudoService->detalharItem((int) $itemId, (int) $cursoId);
        if (empty($detalhe['ok']) || (string) ($detalhe['item']['tipo'] ?? '') !== 'quiz') {
            return null;
        }
        return $this->quizService->findQuizCompleto((int) $itemId);
    }
}

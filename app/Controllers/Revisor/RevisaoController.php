<?php

namespace App\Controllers\Revisor;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\RevisaoComentario;
use App\Services\RevisaoComentarioService;
use App\Services\RevisorAcademicScopeService;
use App\Services\RevisorLeituraService;

/**
 * Area de revisao: arvore do curso, leitura de aula, leitura do banco de
 * questoes e registro de apontamentos.
 *
 * Este controller NAO injeta ConteudoCursoService nem qualquer Service de
 * gravacao de conteudo — e a garantia estrutural descrita no plano da spec
 * 0002. A unica escrita possivel a partir daqui e em revisao_comentarios,
 * atraves de RevisaoComentarioService.
 */
class RevisaoController extends Controller
{
    private $escopo;
    private $leitura;
    private $comentarios;
    private $comentarioModel;

    public function __construct()
    {
        $this->escopo = new RevisorAcademicScopeService();
        $this->leitura = new RevisorLeituraService();
        $this->comentarios = new RevisaoComentarioService();
        $this->comentarioModel = new RevisaoComentario();
    }

    // ------------------------------------------------------------------
    // Leitura
    // ------------------------------------------------------------------

    public function curso(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');
        $cursoId = (int) $this->param($request, 'curso_id');

        $contexto = $this->escopo->validarContexto($usuarioId, $cursoId);
        if (empty($contexto['ok'])) {
            return $this->negar($contexto['message']);
        }

        $curso = $this->leitura->curso($cursoId);
        $modulos = $this->leitura->modulosComItens($cursoId);

        return $this->view('revisor/curso', array(
            'title' => $curso['nome'],
            'curso' => $curso,
            'modulos' => $modulos,
            'comentariosPorItem' => $this->comentarioModel->contagemPorAlvo($cursoId, 'conteudo_item'),
            'errosAbertos' => $this->comentarioModel->contarErrosAbertos($cursoId),
            'cursosDoRevisor' => $this->escopo->cursosDoRevisor($usuarioId),
            'cursoAtual' => $curso,
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    public function conteudo(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');
        $itemId = (int) $this->param($request, 'item_id');

        $item = $this->leitura->item($itemId);
        if (!$item) {
            return $this->negar('Conteúdo não encontrado.');
        }
        $cursoId = (int) $item['curso_evento_id'];

        $contexto = $this->escopo->validarContexto($usuarioId, $cursoId);
        if (empty($contexto['ok'])) {
            return $this->negar($contexto['message']);
        }

        $curso = $this->leitura->curso($cursoId);

        return $this->view('revisor/conteudo', array(
            'title' => $item['titulo'],
            'curso' => $curso,
            'item' => $item,
            'corpo' => $this->leitura->corpoDoItem($item),
            'comentarios' => $this->comentarioModel->listarPorAlvo('conteudo_item', $itemId),
            'alvoTipo' => 'conteudo_item',
            'alvoId' => $itemId,
            'cursosDoRevisor' => $this->escopo->cursosDoRevisor($usuarioId),
            'cursoAtual' => $curso,
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    public function questoes(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');
        $itemId = (int) $this->param($request, 'item_id');

        $item = $this->leitura->item($itemId);
        if (!$item || $item['tipo'] !== 'quiz') {
            return $this->negar('Banco de questões não encontrado.');
        }
        $cursoId = (int) $item['curso_evento_id'];

        $contexto = $this->escopo->validarContexto($usuarioId, $cursoId);
        if (empty($contexto['ok'])) {
            return $this->negar($contexto['message']);
        }

        $banco = $this->leitura->bancoDoQuiz($itemId);
        if (!$banco) {
            return $this->negar('Este item ainda não tem quiz configurado.');
        }

        $curso = $this->leitura->curso($cursoId);

        return $this->view('revisor/questoes', array(
            'title' => $item['titulo'],
            'curso' => $curso,
            'item' => $item,
            'quiz' => $banco['quiz'],
            'blocos' => $banco['blocos'],
            'perguntas' => $banco['perguntas'],
            'comentariosPorPergunta' => $this->comentarioModel->contagemPorAlvo($cursoId, 'quiz_pergunta'),
            'comentariosDoCurso' => $this->comentarioModel->listarPorCurso($cursoId, array('alvo_tipo' => 'quiz_pergunta')),
            'cursosDoRevisor' => $this->escopo->cursosDoRevisor($usuarioId),
            'cursoAtual' => $curso,
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    // ------------------------------------------------------------------
    // Escrita — exclusivamente em revisao_comentarios
    // ------------------------------------------------------------------

    public function comentar(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');
        $resultado = $this->comentarios->criar($request->all(), $usuarioId);

        if (empty($resultado['ok'])) {
            Session::flash('errors', array_values($resultado['errors']));
            Session::flash('old_input', $request->all());
        } else {
            Session::flash('success', 'Apontamento registrado.');
        }

        return $this->redirect($this->voltarPara($request));
    }

    public function editarComentario(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');
        $resultado = $this->comentarios->editar((int) $request->input('id', 0), $request->all(), $usuarioId);

        if (empty($resultado['ok'])) {
            Session::flash('errors', array_values($resultado['errors']));
        } else {
            Session::flash('success', 'Apontamento atualizado.');
        }

        return $this->redirect($this->voltarPara($request));
    }

    public function excluirComentario(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');
        $comentario = $this->comentarioModel->findById((int) $request->input('id', 0));

        if (!$comentario || (int) $comentario['autor_id'] !== $usuarioId) {
            Session::flash('errors', array('Só o autor pode excluir o próprio apontamento.'));
            return $this->redirect($this->voltarPara($request));
        }

        $resultado = $this->comentarios->excluir(
            (int) $comentario['id'],
            (string) $request->input('justificativa', ''),
            $usuarioId,
            array(
                'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
            )
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array_values($resultado['errors']));
        } else {
            Session::flash('success', 'Apontamento excluído.');
        }

        return $this->redirect($this->voltarPara($request));
    }

    // ------------------------------------------------------------------

    /**
     * Request::input() le apenas o corpo do POST; a query string vem por
     * query(). As telas do revisor sao GET com ?curso_id= / ?item_id=, entao
     * o parametro precisa ser lido dos dois lugares.
     */
    private function param(Request $request, $chave)
    {
        $valor = $request->query($chave, null);
        if ($valor === null || $valor === '') {
            $valor = $request->input($chave, 0);
        }
        return $valor;
    }

    private function voltarPara(Request $request)
    {
        $destino = (string) $request->input('retorno', '');
        // So aceita caminho interno da area de revisao: evita open redirect.
        if ($destino !== '' && strpos($destino, '/revisor/') === 0 && strpos($destino, '//') === false) {
            return $destino;
        }
        return '/revisor';
    }

    private function negar($mensagem)
    {
        Session::flash('errors', array($mensagem));
        return $this->redirect('/revisor');
    }
}

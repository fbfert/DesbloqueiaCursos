<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AtividadeService;
use App\Services\AreaCursoService;
use App\Services\ProgressoService;

class AreaCursoController extends Controller
{
    // Cache-bust para sincronização de deploy.
    private $areaCursoService;
    private $atividadeService;
    private $progressoService;

    public function __construct()
    {
        $this->areaCursoService = new AreaCursoService();
        $this->atividadeService = new AtividadeService();
        $this->progressoService = new ProgressoService();
    }

    public function index(Request $request)
    {
        $dados = $this->areaCursoService->carregarAluno(
            Session::get('usuario_id'),
            (int) $request->query('inscricao_id', 0),
            (int) $request->query('modulo_id', 0),
            (int) $request->query('aula_id', 0),
            (int) $request->query('curso_id', 0) > 0 ? (int) $request->query('curso_id', 0) : null,
            (int) $request->query('turma_id', 0) > 0 ? (int) $request->query('turma_id', 0) : null,
            (int) $request->query('atividade_id', 0) > 0 ? (int) $request->query('atividade_id', 0) : null
        );

        if (empty($dados['inscricao'])) {
            Session::flash('errors', array('Nenhuma inscrição válida foi encontrada para este usuário.'));
            return $this->redirect('/aluno/meus-cursos');
        }

        return $this->view('area-curso/index', array_merge(
            array(
                'title' => 'Sala virtual',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $dados
        ));
    }

    public function modulo(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $dados = $this->areaCursoService->carregarModuloAluno(
            Session::get('usuario_id'),
            (int) $request->query('inscricao_id', 0),
            (int) $request->query('modulo_id', 0),
            (int) $request->query('aula_id', 0),
            $cursoId > 0 ? $cursoId : null,
            $turmaId > 0 ? $turmaId : null
        );

        if (empty($dados['inscricao']) || empty($dados['selected_modulo'])) {
            Session::flash('errors', array('Módulo não encontrado ou acesso negado.'));
            $redirect = '/aluno/cursos?inscricao_id=' . (int) $request->query('inscricao_id', 0);
            if ($cursoId > 0) {
                $redirect .= '&curso_id=' . $cursoId;
            }
            if ($turmaId > 0) {
                $redirect .= '&turma_id=' . $turmaId;
            }
            return $this->redirect($redirect);
        }

        return $this->view('area-curso/modulo', array_merge(
            array(
                'title' => 'Módulo do curso',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $dados
        ));
    }

    public function concluirAula(Request $request)
    {
        $resultado = $this->progressoService->concluirAula(
            (int) $request->input('inscricao_id', 0),
            (int) $request->input('aula_id', 0),
            Session::get('usuario_id'),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível concluir a aula.'));
        } else {
            Session::flash('success', 'Aula marcada como concluída.');
        }

        return $this->redirect('/aluno/cursos/modulo?inscricao_id=' . (int) $request->input('inscricao_id', 0) . '&modulo_id=' . (int) $request->input('modulo_id', 0) . '&curso_id=' . (int) $request->input('curso_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function concluirModulo(Request $request)
    {
        Session::flash('errors', array('O módulo é concluído automaticamente ao finalizar as aulas publicadas.'));

        return $this->redirect('/aluno/cursos/modulo?inscricao_id=' . (int) $request->input('inscricao_id', 0) . '&modulo_id=' . (int) $request->input('modulo_id', 0) . '&curso_id=' . (int) $request->input('curso_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function material(Request $request)
    {
        $materialId = (int) $request->query('material_id', 0);
        $material = $this->areaCursoService->materialAutorizado(Session::get('usuario_id'), $materialId, 'aluno');

        if (!$material) {
            return new Response(View::render('errors/404', array('title' => 'Material não encontrado')), 404);
        }

        $acesso = $this->areaCursoService->prepararAcessoMaterial($material);
        if (!$acesso) {
            return new Response(View::render('errors/404', array('title' => 'Material indisponível')), 404);
        }

        if ($acesso['tipo'] === 'url') {
            return $this->redirect($acesso['url']);
        }

        if (!is_file($acesso['absolute_path'])) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo não encontrado')), 404);
        }

        $content = file_get_contents($acesso['absolute_path']);
        return new Response($content, 200, array(
            'Content-Type' => $acesso['content_type'],
            'Content-Disposition' => 'inline; filename="' . $acesso['filename'] . '"',
        ));
    }

    public function enviarAtividade(Request $request)
    {
        $resultado = $this->atividadeService->enviarEntrega(
            $request->all(),
            isset($_FILES['arquivo']) ? $_FILES['arquivo'] : null,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível enviar a atividade.'));
        } else {
            Session::flash('success', 'Atividade enviada com sucesso.');
        }

        return $this->redirect('/aluno/cursos?inscricao_id=' . (int) $request->input('inscricao_id', 0) . '&curso_id=' . (int) $request->input('curso_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0) . '&modulo_id=' . (int) $request->input('modulo_id', 0) . '&aula_id=' . (int) $request->input('aula_id', 0) . '&atividade_id=' . (int) $request->input('atividade_id', 0));
    }

    public function entregaArquivo(Request $request)
    {
        $entregaId = (int) $request->query('entrega_id', 0);
        $entrega = $this->atividadeService->entregaAutorizada(Session::get('usuario_id'), $entregaId, 'aluno');

        if (!$entrega) {
            Logger::info('atividade.entrega.bloqueio_acesso', array(
                'contexto' => 'aluno',
                'entrega_id' => $entregaId,
                'usuario_id' => Session::get('usuario_id'),
            ));
            return new Response(View::render('errors/404', array('title' => 'Entrega nao encontrada')), 404);
        }

        $acesso = $this->atividadeService->prepararAcessoEntrega($entrega);
        if (!$acesso) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo indisponivel')), 404);
        }

        if (!is_file($acesso['absolute_path'])) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo nao encontrado')), 404);
        }

        Logger::info('atividade.entrega.download', array(
            'contexto' => 'aluno',
            'entrega_id' => $entregaId,
            'usuario_id' => Session::get('usuario_id'),
        ));

        $content = file_get_contents($acesso['absolute_path']);
        return new Response($content, 200, array(
            'Content-Type' => $acesso['content_type'],
            'Content-Disposition' => 'inline; filename="' . $acesso['filename'] . '"',
        ));
    }
}


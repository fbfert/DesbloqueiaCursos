<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AreaCursoService;
use App\Services\ProgressoService;

class AreaCursoController extends Controller
{
    private $areaCursoService;
    private $progressoService;

    public function __construct()
    {
        $this->areaCursoService = new AreaCursoService();
        $this->progressoService = new ProgressoService();
    }

    public function index(Request $request)
    {
        $dados = $this->areaCursoService->carregarAluno(
            Session::get('usuario_id'),
            (int) $request->query('inscricao_id', 0),
            (int) $request->query('modulo_id', 0),
            (int) $request->query('aula_id', 0)
        );

        if (empty($dados['inscricao'])) {
            Session::flash('errors', array('Nenhuma inscricao aprovada foi encontrada para este usuario.'));
            return $this->redirect('/meus-cursos');
        }

        return $this->view('area-curso/index', array_merge(
            array(
                'title' => 'Area do curso',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $dados
        ));
    }

    public function modulo(Request $request)
    {
        $dados = $this->areaCursoService->carregarModuloAluno(
            Session::get('usuario_id'),
            (int) $request->query('inscricao_id', 0),
            (int) $request->query('modulo_id', 0),
            (int) $request->query('aula_id', 0)
        );

        if (empty($dados['inscricao']) || empty($dados['selected_modulo'])) {
            Session::flash('errors', array('Modulo nao encontrado ou acesso negado.'));
            return $this->redirect('/area-curso?inscricao_id=' . (int) $request->query('inscricao_id', 0));
        }

        return $this->view('area-curso/modulo', array_merge(
            array(
                'title' => 'Modulo do curso',
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
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possivel concluir a aula.'));
        } else {
            Session::flash('success', 'Aula marcada como concluida.');
        }

        return $this->redirect('/area-curso/modulo?inscricao_id=' . (int) $request->input('inscricao_id', 0) . '&modulo_id=' . (int) $request->input('modulo_id', 0));
    }

    public function concluirModulo(Request $request)
    {
        $resultado = $this->progressoService->concluirModulo(
            (int) $request->input('inscricao_id', 0),
            (int) $request->input('modulo_id', 0),
            Session::get('usuario_id'),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possivel concluir o modulo.'));
        } else {
            Session::flash('success', 'Modulo marcado como concluido.');
        }

        return $this->redirect('/area-curso/modulo?inscricao_id=' . (int) $request->input('inscricao_id', 0) . '&modulo_id=' . (int) $request->input('modulo_id', 0));
    }

    public function material(Request $request)
    {
        $materialId = (int) $request->query('material_id', 0);
        $material = $this->areaCursoService->materialAutorizado(Session::get('usuario_id'), $materialId, 'aluno');

        if (!$material) {
            return new Response(View::render('errors/404', array('title' => 'Material nao encontrado')), 404);
        }

        $absolute = BASE_PATH . '/storage/private_uploads/' . $material['arquivo_caminho'];
        if (!is_file($absolute)) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo nao encontrado')), 404);
        }

        $content = file_get_contents($absolute);
        return new Response($content, 200, array(
            'Content-Type' => !empty($material['arquivo_mime_type']) ? $material['arquivo_mime_type'] : 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($material['arquivo_nome_original'] ?: $material['arquivo_caminho']) . '"',
        ));
    }
}


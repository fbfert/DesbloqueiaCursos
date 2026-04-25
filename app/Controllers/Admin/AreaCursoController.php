<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AreaCursoService;
use App\Services\AulaService;
use App\Services\MaterialService;
use App\Services\ModuloService;
use App\Services\ProgressoService;

class AreaCursoController extends Controller
{
    private $areaCursoService;
    private $moduloService;
    private $aulaService;
    private $materialService;
    private $progressoService;

    public function __construct()
    {
        $this->areaCursoService = new AreaCursoService();
        $this->moduloService = new ModuloService();
        $this->aulaService = new AulaService();
        $this->materialService = new MaterialService();
        $this->progressoService = new ProgressoService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/area-curso/index', array_merge(
            array(
                'title' => 'Area interna do curso',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->areaCursoService->carregarAdmin(
                (int) $request->query('curso_id', 0),
                (int) $request->query('turma_id', 0),
                array(
                    'instrucao_id' => (int) $request->query('instrucao_id', 0),
                    'modulo_id' => (int) $request->query('modulo_id', 0),
                    'aula_id' => (int) $request->query('aula_id', 0),
                    'material_id' => (int) $request->query('material_id', 0),
                    'link_id' => (int) $request->query('link_id', 0),
                )
            )
        ));
    }

    public function salvarInstrução(Request $request)
    {
        $resultado = $this->areaCursoService->salvarInstrução($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/admin/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function salvarModulo(Request $request)
    {
        $resultado = $this->moduloService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/admin/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function salvarAula(Request $request)
    {
        $resultado = $this->aulaService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/admin/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function salvarMaterial(Request $request)
    {
        $resultado = $this->materialService->salvar($request->all(), isset($_FILES['arquivo']) ? $_FILES['arquivo'] : null, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/admin/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function salvarLink(Request $request)
    {
        $resultado = $this->areaCursoService->salvarLink($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/admin/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function excluir(Request $request)
    {
        $resultado = $this->areaCursoService->excluir(
            (string) $request->input('tipo', ''),
            (int) $request->input('id', 0),
            trim((string) $request->input('justificativa', '')),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        return $this->respondForm($resultado, '/admin/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function participantes(Request $request)
    {
        return $this->json(array(
            'ok' => true,
            'participantes' => $this->areaCursoService->listarParticipantes((int) $request->query('curso_id', 0), (int) $request->query('turma_id', 0)),
        ));
    }

    public function material(Request $request)
    {
        $materialId = (int) $request->query('material_id', 0);
        $material = $this->areaCursoService->materialAutorizado(Session::get('usuario_id'), $materialId, 'admin');

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

    private function respondForm(array $resultado, $redirectTo)
    {
        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possivel salvar o registro.'));
        } else {
            Session::flash('success', 'Registro salvo com sucesso.');
        }

        return $this->redirect($redirectTo);
    }
}


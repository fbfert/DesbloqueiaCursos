<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AreaCursoService;
use App\Services\AptidãoCertificadoService;
use App\Services\AvaliacaoService;
use App\Services\PresencaService;

class AcadêmicoController extends Controller
{
    private $areaCursoService;
    private $aptidaoService;
    private $presencaService;
    private $avaliacaoService;

    public function __construct()
    {
        $this->areaCursoService = new AreaCursoService();
        $this->aptidaoService = new AptidãoCertificadoService();
        $this->presencaService = new PresencaService();
        $this->avaliacaoService = new AvaliacaoService();
    }

    public function index(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);

        return $this->view('admin/academico/index', array_merge(
            array(
                'title' => 'Area academica',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->areaCursoService->carregarAdmin($cursoId, $turmaId),
            $this->aptidaoService->contexto($cursoId, $turmaId > 0 ? $turmaId : null),
            $this->presencaService->listarContexto($cursoId, $turmaId > 0 ? $turmaId : null),
            $this->avaliacaoService->listarContexto($cursoId, $turmaId > 0 ? $turmaId : null)
        ));
    }

    public function salvarConfiguração(Request $request)
    {
        $resultado = $this->aptidaoService->salvarConfiguração($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/admin/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function registrarPresenca(Request $request)
    {
        $resultado = $this->presencaService->registrar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/admin/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function salvarAvaliacao(Request $request)
    {
        $resultado = $this->avaliacaoService->salvarAvaliacao($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/admin/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function salvarPergunta(Request $request)
    {
        $resultado = $this->avaliacaoService->salvarPergunta($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/admin/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function registrarNota(Request $request)
    {
        $resultado = $this->avaliacaoService->registrarNota($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/admin/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function recalcularAptidão(Request $request)
    {
        $resultado = $this->aptidaoService->recalcularInscrição((int) $request->input('inscricao_id', 0), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/admin/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
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


<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AreaCursoService;
use App\Services\AptidaoCertificadoService;
use App\Services\AvaliacaoService;
use App\Services\PresencaService;

class AcademicoController extends Controller
{
    private $areaCursoService;
    private $aptidaoService;
    private $presencaService;
    private $avaliacaoService;

    public function __construct()
    {
        $this->areaCursoService = new AreaCursoService();
        $this->aptidaoService = new AptidaoCertificadoService();
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

    public function salvarConfiguracao(Request $request)
    {
        $resultado = $this->aptidaoService->salvarConfiguracao($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/admin/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0),
            '/admin/academico'
        );
    }

    public function registrarPresenca(Request $request)
    {
        $resultado = $this->presencaService->registrar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/admin/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0),
            '/admin/academico'
        );
    }

    public function salvarAvaliacao(Request $request)
    {
        $resultado = $this->avaliacaoService->salvarAvaliacao($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/admin/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0),
            '/admin/academico'
        );
    }

    public function salvarPergunta(Request $request)
    {
        $resultado = $this->avaliacaoService->salvarPergunta($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/admin/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0),
            '/admin/academico'
        );
    }

    public function registrarNota(Request $request)
    {
        $resultado = $this->avaliacaoService->registrarNota($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/admin/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0),
            '/admin/academico'
        );
    }

    public function recalcularAptidao(Request $request)
    {
        $resultado = $this->aptidaoService->recalcularInscricao((int) $request->input('inscricao_id', 0), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/admin/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0),
            '/admin/academico'
        );
    }

    private function respondForm(array $resultado, Request $request, $redirectTo, $exitUrl = null)
    {
        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possivel salvar o registro.'));
        } else {
            Session::flash('success', 'Registro salvo com sucesso.');
        }

        return $this->redirectAfterFormAction($request, $redirectTo, $exitUrl);
    }
}





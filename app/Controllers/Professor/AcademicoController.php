<?php

namespace App\Controllers\Professor;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AreaCursoService;
use App\Services\AptidaoCertificadoService;
use App\Services\AvaliacaoService;
use App\Services\ProfessorAcademicScopeService;
use App\Services\PresencaService;

class AcademicoController extends Controller
{
    private $areaCursoService;
    private $aptidaoService;
    private $presencaService;
    private $avaliacaoService;
    private $scopeService;

    public function __construct()
    {
        $this->areaCursoService = new AreaCursoService();
        $this->aptidaoService = new AptidaoCertificadoService();
        $this->presencaService = new PresencaService();
        $this->avaliacaoService = new AvaliacaoService();
        $this->scopeService = new ProfessorAcademicScopeService();
    }

    public function index(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);

        if ($cursoId > 0) {
            $validacaoContexto = $this->scopeService->validarContexto($cursoId, $turmaId > 0 ? $turmaId : null);
            if (empty($validacaoContexto['ok']) || !$this->contextoAutorizado($cursoId, $turmaId)) {
                return $this->forbidden();
            }
        }

        return $this->view('professor/academico/index', array_merge(
            array(
                'title' => 'Area academica do professor',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->areaCursoService->carregarProfessor(Session::get('usuario_id'), $cursoId, $turmaId > 0 ? $turmaId : null),
            $this->aptidaoService->contexto($cursoId, $turmaId > 0 ? $turmaId : null),
            $this->presencaService->listarContexto($cursoId, $turmaId > 0 ? $turmaId : null),
            $this->avaliacaoService->listarContexto($cursoId, $turmaId > 0 ? $turmaId : null)
        ));
    }

    public function salvarConfiguracao(Request $request)
    {
        if (!$this->contextoAutorizado((int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            return $this->forbidden();
        }

        $resultado = $this->aptidaoService->salvarConfiguracao($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/professor/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function registrarPresenca(Request $request)
    {
        if (!$this->inscricaoAutorizada((int) $request->input('inscricao_id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            return $this->forbidden();
        }

        if ((int) $request->input('aula_id', 0) > 0 && !$this->aulaAutorizada((int) $request->input('aula_id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            return $this->forbidden();
        }

        $resultado = $this->presencaService->registrar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/professor/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function salvarAvaliacao(Request $request)
    {
        if (!$this->avaliacaoAutorizada((int) $request->input('id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            return $this->forbidden();
        }

        $resultado = $this->avaliacaoService->salvarAvaliacao($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/professor/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function salvarPergunta(Request $request)
    {
        if (!$this->avaliacaoAutorizada((int) $request->input('avaliacao_id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            return $this->forbidden();
        }

        if ((int) $request->input('id', 0) > 0 && !$this->perguntaAutorizada((int) $request->input('id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            return $this->forbidden();
        }

        $resultado = $this->avaliacaoService->salvarPergunta($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/professor/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function registrarNota(Request $request)
    {
        if (!$this->inscricaoAutorizada((int) $request->input('inscricao_id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            return $this->forbidden();
        }

        if (!$this->avaliacaoAutorizada((int) $request->input('avaliacao_id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            return $this->forbidden();
        }

        $resultado = $this->avaliacaoService->registrarNota($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/professor/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function recalcularAptidao(Request $request)
    {
        if (!$this->inscricaoAutorizada((int) $request->input('inscricao_id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            return $this->forbidden();
        }

        $resultado = $this->aptidaoService->recalcularInscricao(
            (int) $request->input('inscricao_id', 0),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent(),
            (int) $request->input('curso_evento_id', 0),
            (int) $request->input('turma_id', 0) > 0 ? (int) $request->input('turma_id', 0) : null
        );
        return $this->respondForm($resultado, '/professor/academico?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    private function respondForm(array $resultado, $redirectTo)
    {
        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Nao foi possivel salvar o registro.'));
        } else {
            Session::flash('success', 'Registro salvo com sucesso.');
        }

        return $this->redirect($redirectTo);
    }

    private function contextoAutorizado($cursoId, $turmaId)
    {
        if ($cursoId <= 0) {
            return false;
        }

        $turmaId = $turmaId > 0 ? $turmaId : null;
        $validacaoContexto = $this->scopeService->validarContexto($cursoId, $turmaId);
        if (empty($validacaoContexto['ok'])) {
            return false;
        }

        return $this->areaCursoService->contextoProfessorAutorizado(Session::get('usuario_id'), $cursoId, $turmaId);
    }

    private function inscricaoAutorizada($inscricaoId, $cursoId, $turmaId)
    {
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            return false;
        }

        $validacao = $this->scopeService->validarInscricaoNoContexto($inscricaoId, $cursoId, $turmaId > 0 ? $turmaId : null);
        if (!empty($validacao['ok'])) {
            return true;
        }

        return isset($validacao['message']) && $validacao['message'] === 'Inscricao nao encontrada.';
    }

    private function avaliacaoAutorizada($avaliacaoId, $cursoId, $turmaId)
    {
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            return false;
        }

        if ($avaliacaoId <= 0) {
            return true;
        }

        $validacao = $this->scopeService->validarAvaliacaoNoContexto($avaliacaoId, $cursoId, $turmaId > 0 ? $turmaId : null);
        if (!empty($validacao['ok'])) {
            return true;
        }

        return isset($validacao['message']) && $validacao['message'] === 'Avaliacao nao encontrada.';
    }

    private function perguntaAutorizada($perguntaId, $cursoId, $turmaId)
    {
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            return false;
        }

        $validacao = $this->scopeService->validarPerguntaNoContexto($perguntaId, $cursoId, $turmaId > 0 ? $turmaId : null);
        if (!empty($validacao['ok'])) {
            return true;
        }

        return isset($validacao['message']) && $validacao['message'] === 'Pergunta nao encontrada.';
    }

    private function aulaAutorizada($aulaId, $cursoId, $turmaId)
    {
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            return false;
        }

        $validacao = $this->scopeService->validarAulaNoContexto($aulaId, $cursoId, $turmaId > 0 ? $turmaId : null);
        if (!empty($validacao['ok'])) {
            return true;
        }

        return isset($validacao['message']) && $validacao['message'] === 'Aula nao encontrada.';
    }

    private function forbidden()
    {
        return new Response(View::render('errors/403', array('title' => 'Acesso negado')), 403);
    }
}

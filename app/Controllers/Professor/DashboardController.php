<?php

namespace App\Controllers\Professor;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ConteudoAvaliacaoTextualService;
use App\Services\DashboardProfessorService;

class DashboardController extends Controller
{
    private $dashboardService;
    private $conteudoAvaliacaoTextualService;

    public function __construct()
    {
        $this->dashboardService = new DashboardProfessorService();
        $this->conteudoAvaliacaoTextualService = new ConteudoAvaliacaoTextualService();
    }

    public function index(Request $request)
    {
        $result = $this->dashboardService->painel(
            Session::get('usuario_id'),
            array(
                'periodo' => $request->query('periodo', 'mes'),
                'data_inicio' => $request->query('data_inicio', ''),
                'data_fim' => $request->query('data_fim', ''),
            ),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            if (!empty($result['redirect'])) {
                return $this->redirect($result['redirect']);
            }

            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Acesso negado.'));
            return $this->redirect('/meus-cursos');
        }

        $pendencias = $this->conteudoAvaliacaoTextualService->contarPendentesProfessor((int) Session::get('usuario_id'));

        return $this->view('professor/dashboard/index', array_merge(
            array(
                'title' => 'Meu dashboard',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'conteudo_avaliacoes_pendentes' => !empty($pendencias['total']) ? (int) $pendencias['total'] : 0,
            ),
            $result
        ));
    }
}

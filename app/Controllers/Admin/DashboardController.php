<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ConteudoAvaliacaoTextualService;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    private $dashboardService;
    private $conteudoAvaliacaoTextualService;

    public function __construct()
    {
        $this->dashboardService = new DashboardService();
        $this->conteudoAvaliacaoTextualService = new ConteudoAvaliacaoTextualService();
    }

    public function index(Request $request)
    {
        $result = $this->dashboardService->adminDashboard(
            $request->all() + array(
                'periodo' => $request->query('periodo', 'mes'),
                'data_inicio' => $request->query('data_inicio', ''),
                'data_fim' => $request->query('data_fim', ''),
                'curso_evento_id' => $request->query('curso_evento_id', 0),
                'turma_id' => $request->query('turma_id', 0),
                'categoria_id' => $request->query('categoria_id', 0),
                'cidade' => $request->query('cidade', ''),
                'estado' => $request->query('estado', ''),
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
            return $this->redirect('/');
        }

        $pendencias = $this->conteudoAvaliacaoTextualService->contarPendentesProfessor(0);

        return $this->view('admin/dashboard/index', array_merge(
            array(
                'title' => 'Dashboard executivo',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'conteudo_avaliacoes_pendentes' => !empty($pendencias['total']) ? (int) $pendencias['total'] : 0,
            ),
            $result
        ));
    }
}

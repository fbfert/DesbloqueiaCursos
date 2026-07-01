<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Services\ConteudoAvaliacaoTextualService;
use App\Services\DashboardService;
use App\Services\RbacService;
use App\Services\SuperAdminTurmasService;

class DashboardController extends Controller
{
    private $dashboardService;
    private $conteudoAvaliacaoTextualService;
    private $rbacService;
    private $superAdminTurmasService;

    public function __construct()
    {
        $this->dashboardService = new DashboardService();
        $this->conteudoAvaliacaoTextualService = new ConteudoAvaliacaoTextualService();
        $this->rbacService = new RbacService();
        $this->superAdminTurmasService = new SuperAdminTurmasService();
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
        $usuarioId = Session::get('usuario_id');
        $isSuperAdmin = $this->rbacService->isSuperAdmin($usuarioId);
        $superadminTurmasPreview = array();

        if ($isSuperAdmin) {
            try {
                $superadminTurmasPreview = $this->superAdminTurmasService->preview();
            } catch (\Throwable $exception) {
                Logger::error('admin.dashboard.superadmins_turmas.preview_falhou', array(
                    'usuario_id' => $usuarioId,
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ));
            }
        }

        return $this->view('admin/dashboard/index', array_merge(
            array(
                'title' => 'Dashboard executivo',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'conteudo_avaliacoes_pendentes' => !empty($pendencias['total']) ? (int) $pendencias['total'] : 0,
                'is_superadmin' => $isSuperAdmin,
                'superadmin_turmas_preview' => $superadminTurmasPreview,
            ),
            $result
        ));
    }

    public function sincronizarSuperadminsTurmas(Request $request)
    {
        $result = $this->superAdminTurmasService->sincronizar(
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível concluir a sincronização.'));
            return $this->redirect('/admin/dashboard');
        }

        Session::flash(
            'success',
            'Sincronização concluída: ' . (int) $result['inscricoes_criadas'] . ' inscrições criadas e ' . (int) $result['inscricoes_ignoradas'] . ' já existentes ignoradas.'
        );

        return $this->redirect('/admin/dashboard');
    }
}

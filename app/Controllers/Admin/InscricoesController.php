<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\InscricaoService;

class InscricoesController extends Controller
{
    private $inscricaoService;

    public function __construct()
    {
        $this->inscricaoService = new InscricaoService();
    }

    public function index(Request $request)
    {
        $filters = array(
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'pedido_status' => trim((string) $request->query('pedido_status', '')),
            'curso_evento_id' => (int) $request->query('curso_evento_id', 0),
            'turma_id' => (int) $request->query('turma_id', 0),
            'sort_by' => trim((string) $request->query('sort_by', 'id')),
            'sort_dir' => strtolower(trim((string) $request->query('sort_dir', 'desc'))),
            'per_page' => (int) $request->query('per_page', 20),
        );
        $page = (int) $request->query('page', 1);

        return $this->view('admin/inscricoes/index', array_merge(
            array(
                'title' => 'Inscrições',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'filters' => $filters,
            ),
            $this->inscricaoService->listarBackoffice(
                Session::get('usuario_id'),
                $filters,
                $page,
                $filters['per_page']
            )
        ));
    }

    public function alterarStatus(Request $request)
    {
        $inscricaoId = (int) $request->input('inscricao_id', 0);
        $novoStatus = trim((string) $request->input('status', ''));
        $observacao = trim((string) $request->input('observacao', ''));
        $action = $this->submitAction($request);

        $result = $this->inscricaoService->alterarStatus(
            $inscricaoId,
            $novoStatus,
            $observacao !== '' ? $observacao : null,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flash('errors', array($result['message']));
            return $this->redirect('/admin/inscricoes');
        }

        Session::flash('success', 'Status da inscrição atualizado.');
        return $action === 'save_exit' ? $this->redirect('/admin/inscricoes') : $this->redirect('/admin/inscricoes');
    }
}



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
        return $this->view('admin/inscricoes/index', array_merge(
            array(
                'title' => 'Inscrições',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->inscricaoService->listarBackoffice(Session::get('usuario_id'))
        ));
    }

    public function alterarStatus(Request $request)
    {
        $inscricaoId = (int) $request->input('inscricao_id', 0);
        $novoStatus = trim((string) $request->input('status', ''));
        $observacao = trim((string) $request->input('observacao', ''));

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

        Session::flash('success', 'Status da inscricao atualizado.');
        return $this->redirect('/admin/inscricoes');
    }
}



<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ComprovantePixService;

class ComprovantesPixController extends Controller
{
    private $comprovanteService;

    public function __construct()
    {
        $this->comprovanteService = new ComprovantePixService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/comprovantes_pix/index', array_merge(
            array(
                'title' => 'Comprovantes PIX',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->comprovanteService->listarBackoffice(Session::get('usuario_id'))
        ));
    }

    public function aprovar(Request $request)
    {
        $comprovanteId = (int) $request->input('comprovante_id', 0);
        $observacao = trim((string) $request->input('observacao', ''));
        $confirmacaoAprovacao = strtoupper(trim((string) $request->input('confirmacao_aprovacao', '')));

        if ($confirmacaoAprovacao !== 'CONFERIDO') {
            Session::flash('errors', array('Digite CONFERIDO para aprovar este comprovante.'));
            return $this->redirect('/admin/comprovantes-pix');
        }

        $result = $this->comprovanteService->aprovar(
            $comprovanteId,
            $observacao !== '' ? $observacao : null,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flash('errors', array($result['message']));
            return $this->redirect('/admin/comprovantes-pix');
        }

        Session::flash('success', 'Comprovante aprovado.');
        return $this->redirect('/admin/comprovantes-pix');
    }

    public function reprovar(Request $request)
    {
        $comprovanteId = (int) $request->input('comprovante_id', 0);
        $observacao = trim((string) $request->input('observacao', ''));
        $confirmacaoReprovacao = strtoupper(trim((string) $request->input('confirmacao_reprovacao', '')));

        if ($confirmacaoReprovacao !== 'REPROVADO') {
            Session::flash('errors', array('Digite REPROVADO para reprovar este comprovante.'));
            return $this->redirect('/admin/comprovantes-pix');
        }

        $result = $this->comprovanteService->reprovar(
            $comprovanteId,
            $observacao !== '' ? $observacao : null,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flash('errors', array($result['message']));
            return $this->redirect('/admin/comprovantes-pix');
        }

        Session::flash('success', 'Comprovante reprovado.');
        return $this->redirect('/admin/comprovantes-pix');
    }
}

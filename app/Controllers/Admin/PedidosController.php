<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\PedidoService;

class PedidosController extends Controller
{
    private $pedidoService;

    public function __construct()
    {
        $this->pedidoService = new PedidoService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/pedidos/index', array_merge(
            array(
                'title' => 'Pedidos',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->pedidoService->listarBackoffice(Session::get('usuario_id'))
        ));
    }

    public function show(Request $request)
    {
        $pedidoId = (int) $request->query('pedido_id', 0);
        $detalhe = $this->pedidoService->detalharBackoffice($pedidoId, Session::get('usuario_id'));

        if (empty($detalhe['pedido'])) {
            Session::flash('errors', array('Pedido nao encontrado.'));
            return $this->redirect('/admin/pedidos');
        }

        return $this->view('admin/pedidos/show', array_merge(
            array(
                'title' => 'Pedido #' . (int) $detalhe['pedido']['id'],
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $detalhe
        ));
    }

    public function aprovar(Request $request)
    {
        $pedidoId = (int) $request->input('pedido_id', 0);
        $observacao = trim((string) $request->input('observacao', ''));

        $result = $this->pedidoService->aprovarPedido(
            $pedidoId,
            $observacao !== '' ? $observacao : null,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flash('errors', array($result['message']));
            return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId);
        }

        Session::flash('success', 'Pedido aprovado.');
        return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId);
    }

    public function marcarPendencia(Request $request)
    {
        $pedidoId = (int) $request->input('pedido_id', 0);
        $observacao = trim((string) $request->input('observacao', ''));

        $result = $this->pedidoService->marcarPendencia(
            $pedidoId,
            $observacao !== '' ? $observacao : null,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flash('errors', array($result['message']));
            return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId);
        }

        Session::flash('success', 'Pedido marcado com pendencia.');
        return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId);
    }

    public function solicitarReenvio(Request $request)
    {
        $pedidoId = (int) $request->input('pedido_id', 0);
        $observacao = trim((string) $request->input('observacao', ''));

        $result = $this->pedidoService->solicitarReenvioComprovante(
            $pedidoId,
            $observacao !== '' ? $observacao : null,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flash('errors', array($result['message']));
            return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId);
        }

        Session::flash('success', 'Reenvio de comprovante solicitado.');
        return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId);
    }
}

<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Pedido;
use App\Services\InscricaoService;
use App\Services\PedidoService;

class MeusCursosController extends Controller
{
    private $inscricaoService;
    private $pedidoModel;
    private $pedidoService;

    public function __construct()
    {
        $this->inscricaoService = new InscricaoService();
        $this->pedidoModel = new Pedido();
        $this->pedidoService = new PedidoService();
    }

    public function index(Request $request)
    {
        $usuarioId = Session::get('usuario_id');
        $pedidosPendentes = $this->pedidoModel->pendentesParaMeusCursos((int) $usuarioId, 5);
        $cacheBust = date('Y-m-d H:i:s');
        $inscricoesAprovadas = $this->inscricaoService->listarAprovadasDoUsuario($usuarioId);
        $inscricoes = isset($inscricoesAprovadas['inscricoes']) && is_array($inscricoesAprovadas['inscricoes'])
            ? $inscricoesAprovadas['inscricoes']
            : array();

        if (empty($inscricoes)) {
            $todas = $this->inscricaoService->listarDoUsuario($usuarioId);
            $inscricoes = isset($todas['inscricoes']) && is_array($todas['inscricoes']) ? $todas['inscricoes'] : array();
        }

        return $this->view('meus-cursos/index', array(
            'title' => 'Meus Cursos',
            'usuarioNome' => Session::get('usuario_nome'),
            'success' => Session::pullFlash('success'),
            'pedidosPendentes' => $pedidosPendentes,
            'cacheBustMeusCursos' => $cacheBust,
            'inscricoes' => $inscricoes,
        ));
    }

    public function cancelarPedido(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        $pedidoId = (int) $request->input('pedido_id', 0);
        $motivo = trim((string) $request->input('motivo_cancelamento', ''));

        if ($pedidoId <= 0) {
            Session::flash('errors', array('Pedido inválido para cancelamento.'));
            return $this->redirect('/aluno/meus-cursos');
        }

        if ($motivo === '') {
            Session::flash('errors', array('Informe o motivo do cancelamento do pedido.'));
            return $this->redirect('/aluno/meus-cursos');
        }

        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            Session::flash('errors', array('Pedido não encontrado.'));
            return $this->redirect('/aluno/meus-cursos');
        }

        $statusCancelaveis = array(
            'rascunho',
            'aguardando_pagamento',
            'pendencia',
            'aguardando_reenvio',
            'comprovante_enviado',
            'em_analise',
        );

        if (!in_array((string) $pedido['status'], $statusCancelaveis, true)) {
            Session::flash('errors', array('Este pedido não pode mais ser cancelado pelo aluno.'));
            return $this->redirect('/aluno/meus-cursos');
        }

        $observacao = 'Cancelamento solicitado pelo aluno. Motivo: ' . $motivo;
        $resultado = $this->pedidoService->registrarStatus(
            $pedidoId,
            'cancelado',
            $observacao,
            $usuarioId,
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível cancelar o pedido.'));
            return $this->redirect('/aluno/meus-cursos');
        }

        Session::flash('success', 'Pedido cancelado com sucesso.');
        return $this->redirect('/aluno/meus-cursos');
    }
}



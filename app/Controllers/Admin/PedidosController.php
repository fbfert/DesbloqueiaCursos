<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Response;
use App\Core\View;
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

    public function excluidos(Request $request)
    {
        $filters = array(
            'q' => $request->query('q', ''),
            'status' => $request->query('status', ''),
            'curso' => $request->query('curso', ''),
            'de' => $request->query('de', ''),
            'ate' => $request->query('ate', ''),
        );

        return $this->view('admin/pedidos/excluidos', array_merge(
            array(
                'title' => 'Pedidos excluídos',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->pedidoService->listarExcluidosBackoffice(Session::get('usuario_id'), $filters)
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

    public function excluir(Request $request)
    {
        $pedidoId = (int) $request->input('pedido_id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));

        if ($pedidoId <= 0) {
            Session::flash('errors', array('Pedido inválido.'));
            return $this->redirect('/admin/pedidos');
        }

        $result = $this->pedidoService->excluir(
            $pedidoId,
            $justificativa !== '' ? $justificativa : 'Exclusão administrativa do pedido.',
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array($result['message']));
            return $this->redirect('/admin/pedidos');
        }

        Session::flash('success', 'Pedido excluído com sucesso.');
        return $this->redirect('/admin/pedidos');
    }

    public function excluirEmLote(Request $request)
    {
        $dias = (int) $request->input('dias', 30);
        $result = $this->pedidoService->excluirPedidosAntigosNaoConfirmados(
            $dias > 0 ? $dias : 30,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array($result['message'] ?? 'Não foi possível excluir os pedidos em lote.'));
            return $this->redirect('/admin/pedidos');
        }

        $mensagem = $result['message'] ?? 'Operação concluída.';
        if (!empty($result['ignorados'])) {
            $mensagem .= ' ' . $result['ignorados'] . ' pedidos foram ignorados por possuírem vínculos protegidos.';
        }

        Session::flash('success', $mensagem);
        return $this->redirect('/admin/pedidos');
    }

    public function cupomManual(Request $request)
    {
        $pedidoId = (int) $request->input('pedido_id', 0);
        $acao = trim((string) $request->input('acao', 'aplicar'));
        $justificativa = trim((string) $request->input('justificativa', ''));

        if ($pedidoId <= 0) {
            Session::flash('errors', array('Pedido inválido.'));
            return $this->redirect('/admin/pedidos');
        }

        if ($acao === 'remover') {
            $result = $this->pedidoService->removerCupomManualDoPedido(
                $pedidoId,
                $justificativa,
                Session::get('usuario_id'),
                $request->ip(),
                $request->userAgent()
            );
        } else {
            $cupomCodigo = trim((string) $request->input('cupom_codigo', ''));
            if ($cupomCodigo === '') {
                Session::flash('errors', array('Informe o código do cupom.'));
                return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#cupom-manual');
            }

            $result = $this->pedidoService->aplicarCupomManualAoPedido(
                $pedidoId,
                $cupomCodigo,
                $justificativa,
                Session::get('usuario_id'),
                $request->ip(),
                $request->userAgent()
            );
        }

        if (empty($result['ok'])) {
            $mensagens = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array(isset($result['message']) ? $result['message'] : 'Não foi possível aplicar o cupom manualmente.');
            Session::flash('errors', $mensagens);
            return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#cupom-manual');
        }

        if ($acao === 'remover') {
            Session::flash('success', 'Cupom removido manualmente do pedido.');
        } else {
            Session::flash('success', 'Cupom aplicado manualmente ao pedido. Revise o comprovante antes de aprovar o pagamento.');
        }

        return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#cupom-manual');
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

    public function comprovante(Request $request)
    {
        $pedidoId = (int) $request->query('pedido_id', 0);
        $comprovanteId = (int) $request->query('comprovante_id', 0);
        $detalhe = $this->pedidoService->detalharBackoffice($pedidoId, Session::get('usuario_id'));

        if (empty($detalhe['pedido']) || empty($detalhe['can_see_pix'])) {
            return new Response(View::render('errors/404', array('title' => 'Comprovante não encontrado')), 404);
        }

        $comprovantes = isset($detalhe['pedido']['comprovantes']) && is_array($detalhe['pedido']['comprovantes'])
            ? $detalhe['pedido']['comprovantes']
            : array();

        $selecionado = null;
        foreach ($comprovantes as $comprovante) {
            if ((int) $comprovante['id'] === $comprovanteId) {
                $selecionado = $comprovante;
                break;
            }
        }

        if (!$selecionado && !empty($detalhe['pedido']['comprovante_atual'])) {
            $selecionado = $detalhe['pedido']['comprovante_atual'];
        }

        if (!$selecionado || empty($selecionado['arquivo_caminho'])) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo do comprovante não encontrado')), 404);
        }

        $absolute = BASE_PATH . '/storage/private_uploads/' . $selecionado['arquivo_caminho'];
        if (!is_file($absolute)) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo do comprovante não encontrado')), 404);
        }

        $content = file_get_contents($absolute);
        return new Response($content, 200, array(
            'Content-Type' => !empty($selecionado['arquivo_mime_type']) ? $selecionado['arquivo_mime_type'] : 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($selecionado['arquivo_nome_original'] ?: $selecionado['arquivo_caminho']) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ));
    }
}

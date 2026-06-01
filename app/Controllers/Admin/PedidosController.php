<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Response;
use App\Core\View;
use App\Services\ComprovantePixService;
use App\Services\PedidoService;

class PedidosController extends Controller
{
    private $pedidoService;
    private $comprovanteService;

    public function __construct()
    {
        $this->pedidoService = new PedidoService();
        $this->comprovanteService = new ComprovantePixService();
    }

    public function index(Request $request)
    {
        $filters = array(
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'curso' => trim((string) $request->query('curso', '')),
            'de' => trim((string) $request->query('de', '')),
            'ate' => trim((string) $request->query('ate', '')),
            'sort_by' => trim((string) $request->query('sort_by', 'id')),
            'sort_dir' => strtolower(trim((string) $request->query('sort_dir', 'desc'))),
            'per_page' => (int) $request->query('per_page', 20),
        );
        $page = (int) $request->query('page', 1);

        return $this->view('admin/pedidos/index', array_merge(
            array(
                'title' => 'Pedidos',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'filters' => $filters,
            ),
            $this->pedidoService->listarBackoffice(
                Session::get('usuario_id'),
                $filters,
                $page,
                $filters['per_page']
            )
        ));
    }

    public function criar(Request $request)
    {
        if ($request->method() === 'POST') {
            return $this->salvarPedidoManual($request);
        }

        return $this->viewPedidoManual(array(
            'title' => 'Criar pedido manualmente',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'form_data' => array(),
            'aluno_selecionado' => null,
        ));
    }

    public function alunos(Request $request)
    {
        $termo = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 12);
        $alunos = $this->pedidoService->buscarAlunosPedidoManual($termo, $limit);

        $itens = array();
        foreach ($alunos as $aluno) {
            $itens[] = array(
                'id' => (int) $aluno['id'],
                'nome' => isset($aluno['nome']) ? (string) $aluno['nome'] : '',
                'email' => isset($aluno['email']) ? (string) $aluno['email'] : '',
                'cpf' => isset($aluno['cpf']) ? (string) $aluno['cpf'] : '',
                'telefone' => isset($aluno['telefone']) ? (string) $aluno['telefone'] : '',
                'label' => trim(
                    (string) ($aluno['nome'] ?? '') .
                    (!empty($aluno['email']) ? ' · ' . $aluno['email'] : '') .
                    (!empty($aluno['cpf']) ? ' · CPF ' . $aluno['cpf'] : '')
                ),
            );
        }

        return Response::json(array(
            'ok' => true,
            'alunos' => $itens,
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
        $pedidoId = (int) $request->query('pedido_id', (int) $request->query('id', 0));
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

    public function reverterCancelamento(Request $request)
    {
        $pedidoId = (int) $request->input('pedido_id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));

        if ($pedidoId <= 0) {
            Session::flash('errors', array('Pedido inválido.'));
            return $this->redirect('/admin/pedidos');
        }

        if ($justificativa === '') {
            Session::flash('errors', array('Informe a justificativa da reversão do cancelamento.'));
            return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#acoes-pedido');
        }

        $result = $this->pedidoService->reverterCancelamento(
            $pedidoId,
            $justificativa,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array($result['message']));
            return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#acoes-pedido');
        }

        Session::flash('success', 'Cancelamento revertido com sucesso. O pedido voltou para o status anterior.');
        return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#acoes-pedido');
    }

    public function reabrirComoAguardandoPagamento(Request $request)
    {
        $pedidoId = (int) $request->input('pedido_id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));

        if ($pedidoId <= 0) {
            Session::flash('errors', array('Pedido inválido.'));
            return $this->redirect('/admin/pedidos');
        }

        if ($justificativa === '') {
            Session::flash('errors', array('Informe a justificativa da reabertura do pedido.'));
            return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#acoes-pedido');
        }

        $result = $this->pedidoService->reabrirCanceladoComoAguardandoPagamento(
            $pedidoId,
            $justificativa,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array($result['message']));
            return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#acoes-pedido');
        }

        Session::flash('success', 'Pedido reaberto com sucesso. O status voltou para aguardando pagamento.');
        return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#acoes-pedido');
    }

    public function marcarRascunhoComoAguardandoPagamento(Request $request)
    {
        $pedidoId = (int) $request->input('pedido_id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));

        if ($pedidoId <= 0) {
            Session::flash('errors', array('Pedido inválido.'));
            return $this->redirect('/admin/pedidos');
        }

        if ($justificativa === '') {
            Session::flash('errors', array('Informe a justificativa da alteração de status.'));
            return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#acoes-pedido');
        }

        $result = $this->pedidoService->marcarRascunhoComoAguardandoPagamento(
            $pedidoId,
            $justificativa,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array($result['message']));
            return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#acoes-pedido');
        }

        Session::flash('success', 'Pedido atualizado com sucesso. O status agora é aguardando pagamento.');
        return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#acoes-pedido');
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
        } elseif (!empty($result['aplicacao_pos_aprovacao'])) {
            if (!empty($result['total_final'])) {
                Session::flash('success', 'Cupom aplicado como ajuste pós-aprovação. Novo total do pedido: R$ ' . number_format((float) $result['total_final'], 2, ',', '.'));
            } else {
                Session::flash('success', 'Cupom aplicado como ajuste pós-aprovação. O status do pedido foi mantido e o financeiro foi atualizado.');
            }
        } else {
            if (!empty($result['total_final'])) {
                Session::flash('success', 'Cupom aplicado. Novo total do pedido: R$ ' . number_format((float) $result['total_final'], 2, ',', '.'));
            } else {
                Session::flash('success', 'Cupom aplicado manualmente ao pedido. Revise o comprovante antes de aprovar o pagamento.');
            }
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

        Session::flash('success', array(
            'message' => 'Pedido aprovado.',
            'link' => array(
                'label' => 'Voltar para analisar outros comprovantes',
                'href' => '/admin/comprovantes-pix',
            ),
        ));
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

    private function salvarPedidoManual(Request $request)
    {
        $formData = $this->normalizarFormPedidoManual($request);
        $resultado = $this->pedidoService->criarPedidoManual(
            $formData,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            if (!empty($resultado['pedido_id'])) {
                Session::flash('errors', isset($resultado['errors']) && is_array($resultado['errors'])
                    ? $resultado['errors']
                    : array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível concluir a criação deste pedido manual.'));
                return $this->redirect('/admin/pedidos/show?pedido_id=' . (int) $resultado['pedido_id'] . '#comprovante-manual');
            }

            return $this->viewPedidoManual(array(
                'title' => 'Criar pedido manualmente',
                'errors' => isset($resultado['errors']) && is_array($resultado['errors'])
                    ? $resultado['errors']
                    : array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível criar o pedido manualmente.'),
                'form_data' => $formData,
                'aluno_selecionado' => !empty($formData['aluno_usuario_id'])
                    ? $this->pedidoService->obterAlunoPedidoManual((int) $formData['aluno_usuario_id'])
                    : null,
            ));
        }

        Session::flash('success', 'Pedido manual criado com sucesso. O status inicial é aguardando pagamento.');
        return $this->redirect('/admin/pedidos/show?pedido_id=' . (int) $resultado['pedido_id'] . '#comprovante-manual');
    }

    private function viewPedidoManual(array $data = array())
    {
        $base = $this->pedidoService->dadosPedidoManual();
        $formData = isset($data['form_data']) && is_array($data['form_data']) ? $data['form_data'] : array();
        $alunoSelecionado = null;
        if (!empty($formData['aluno_usuario_id'])) {
            $alunoSelecionado = $this->pedidoService->obterAlunoPedidoManual((int) $formData['aluno_usuario_id']);
        } elseif (!empty($data['aluno_selecionado']) && is_array($data['aluno_selecionado'])) {
            $alunoSelecionado = $data['aluno_selecionado'];
        }

        return $this->view('admin/pedidos/criar', array_merge(
            array(
                'title' => 'Criar pedido manualmente',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'form_data' => $formData,
                'aluno_selecionado' => $alunoSelecionado,
            ),
            $base,
            $data
        ));
    }

    private function normalizarFormPedidoManual(Request $request)
    {
        return array(
            'aluno_usuario_id' => (int) $request->input('aluno_usuario_id', 0),
            'pagador_nome' => trim((string) $request->input('pagador_nome', '')),
            'pagador_cpf' => trim((string) $request->input('pagador_cpf', '')),
            'pagador_email' => trim((string) $request->input('pagador_email', '')),
            'pagador_telefone' => trim((string) $request->input('pagador_telefone', '')),
            'curso_evento_id' => (int) $request->input('curso_evento_id', 0),
            'turma_id' => (int) $request->input('turma_id', 0),
            'cupom_codigo' => trim((string) $request->input('cupom_codigo', '')),
            'cupom_justificativa' => trim((string) $request->input('cupom_justificativa', '')),
            'observacoes_internas' => trim((string) $request->input('observacoes_internas', '')),
        );
    }

    public function anexarComprovante(Request $request)
    {
        $pedidoId = (int) $request->input('pedido_id', 0);
        if ($pedidoId <= 0) {
            Session::flash('errors', array('Pedido inválido.'));
            return $this->redirect('/admin/pedidos');
        }

        if (empty($_FILES['comprovante']) || empty($_FILES['comprovante']['tmp_name'])) {
            Session::flash('errors', array('Selecione um arquivo de comprovante.'));
            return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#comprovante-manual');
        }

        if ((int) $_FILES['comprovante']['size'] <= 0) {
            Session::flash('errors', array('O arquivo enviado não é válido.'));
            return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#comprovante-manual');
        }

        $resultado = $this->comprovanteService->enviarUpload(
            $pedidoId,
            $_FILES['comprovante'],
            array(
                'valor_informado' => $request->input('valor_informado'),
                'motivo_reenvio' => $request->input('motivo_reenvio'),
            ),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível enviar o comprovante.'));
            return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#comprovante-manual');
        }

        Session::flash('success', 'Comprovante enviado com sucesso.');
        return $this->redirect('/admin/pedidos/show?pedido_id=' . $pedidoId . '#comprovante-manual');
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

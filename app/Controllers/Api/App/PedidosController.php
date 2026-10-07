<?php

namespace App\Controllers\Api\App;

use App\Core\Logger;
use App\Core\Request;
use App\Models\ComprovantePix;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Usuario;
use App\Services\ComprovantePixService;
use App\Services\PagamentoAbacatepayService;
use App\Services\PedidoService;
use App\Services\Payments\AbacatePayService;
use App\Support\AppApi\PedidoPresenter;

/**
 * Pedidos do aluno: lista, cancelamento, comprovante PIX e pagamento online.
 * Só pedidos em que o usuário do token é comprador ou pagador.
 */
class PedidosController extends AppController
{
    public function index(Request $request)
    {
        $online = $this->pagamentoOnlineHabilitado();
        $saida = array();
        foreach ((new Pedido())->forUsuario($this->usuarioId()) as $pedido) {
            $saida[] = $this->apresentar($pedido, $online);
        }

        return $this->ok($saida);
    }

    public function cancelar(Request $request)
    {
        $pedidoId = (int) $request->route('id', 0);
        $motivo = $this->texto($request, 'motivo', 500);

        $resultado = (new PedidoService())->cancelarPeloAluno(
            $pedidoId,
            $this->usuarioId(),
            $motivo !== '' ? $motivo : 'Cancelado pelo aluno no aplicativo.',
            $request->ip(),
            $request->userAgent(),
            true
        );
        if (empty($resultado['ok'])) {
            $motivoErro = isset($resultado['motivo']) ? $resultado['motivo'] : '';
            if ($motivoErro === 'nao_encontrado' || $motivoErro === 'pedido_invalido') {
                return $this->naoEncontrado('Pedido não encontrado.');
            }
            return $this->erro('nao_cancelavel', $resultado['message'] ?? 'Este pedido não pode mais ser cancelado.', 422);
        }

        return $this->ok($this->apresentar((new Pedido())->findById($pedidoId), $this->pagamentoOnlineHabilitado()));
    }

    public function comprovante(Request $request)
    {
        $pedido = $this->pedidoDoAluno($request);
        if ($pedido === null) {
            return $this->naoEncontrado('Pedido não encontrado.');
        }

        $arquivo = isset($_FILES['arquivo']) && is_array($_FILES['arquivo']) ? $_FILES['arquivo'] : null;
        if ($arquivo === null || (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $this->erro('validacao', 'Selecione o comprovante.', 422, array('arquivo' => 'Selecione o comprovante.'));
        }
        if ((int) ($arquivo['error'] ?? 0) !== UPLOAD_ERR_OK) {
            return $this->erro('validacao', 'Não foi possível receber o arquivo. Ele pode ser maior que o permitido.', 422, array('arquivo' => 'Arquivo inválido ou grande demais.'));
        }

        $motivoReenvio = $this->texto($request, 'motivo_reenvio', 500);
        try {
            $resultado = (new ComprovantePixService())->enviarUpload(
                (int) $pedido['id'],
                $arquivo,
                // Reenvio exige motivo no site; no app o campo é opcional.
                array('motivo_reenvio' => $motivoReenvio !== '' ? $motivoReenvio : 'Reenvio pelo aplicativo.'),
                $this->usuarioId(),
                $request->ip(),
                $request->userAgent()
            );
        } catch (\InvalidArgumentException $e) {
            return $this->erro('validacao', $e->getMessage(), 422, array('arquivo' => $e->getMessage()));
        }

        if (empty($resultado['ok'])) {
            return $this->erro('comprovante_nao_aceito', $resultado['message'] ?? 'Não foi possível enviar o comprovante.', 422);
        }

        return $this->ok($this->apresentar((new Pedido())->findById((int) $pedido['id']), $this->pagamentoOnlineHabilitado()));
    }

    public function abacatepay(Request $request)
    {
        $pedido = $this->pedidoDoAluno($request);
        if ($pedido === null) {
            return $this->naoEncontrado('Pedido não encontrado.');
        }

        $usuario = (new Usuario())->findById($this->usuarioId());
        $resultado = (new PagamentoAbacatepayService())->iniciarParaPedido(
            (int) $pedido['id'],
            $this->usuarioId(),
            array(
                'nome' => (string) ($usuario['nome'] ?? ''),
                'email' => (string) ($usuario['email'] ?? ''),
                'cpf' => (string) ($usuario['cpf'] ?? ''),
                'telefone' => (string) ($usuario['telefone'] ?? ''),
            ),
            $request->ip(),
            $request->userAgent()
        );

        if (!empty($resultado['ok'])) {
            return $this->ok(array('url' => (string) $resultado['url']));
        }

        $motivo = isset($resultado['motivo']) ? $resultado['motivo'] : '';
        $mensagem = $resultado['message'] ?? 'Não foi possível iniciar o pagamento.';
        if ($motivo === 'sem_acesso' || $motivo === 'pedido_invalido') {
            return $this->naoEncontrado('Pedido não encontrado.');
        }
        if (in_array($motivo, array('desativado', 'ja_pago', 'sem_cobranca', 'falha_finalizacao'), true)) {
            return $this->erro('pagamento_indisponivel', $mensagem, 422);
        }

        Logger::warning('app.pedido.abacatepay_falhou', array('pedido_id' => (int) $pedido['id'], 'motivo' => $motivo));
        return $this->erro('pagamento_falhou', $mensagem, 502);
    }

    private function pedidoDoAluno(Request $request)
    {
        $pedido = (new Pedido())->findById((int) $request->route('id', 0));
        if (!$pedido || !PedidoService::usuarioEhDonoDoPedido($pedido, $this->usuarioId())) {
            return null;
        }

        return $pedido;
    }

    private function apresentar(array $pedido, $online)
    {
        return PedidoPresenter::pedido(
            $pedido,
            (new PedidoItem())->forPedido((int) $pedido['id']),
            (new ComprovantePix())->findCurrentByPedido((int) $pedido['id']) ?: null,
            $online
        );
    }

    private function pagamentoOnlineHabilitado()
    {
        try {
            return (new AbacatePayService())->isEnabled();
        } catch (\Throwable $e) {
            return false;
        }
    }
}

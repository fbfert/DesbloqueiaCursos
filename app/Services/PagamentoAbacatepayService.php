<?php

namespace App\Services;

use App\Core\Logger;
use App\Services\Payments\AbacatePayService;

/**
 * Início do pagamento online (AbacatePay) de um pedido do aluno.
 *
 * Orquestração extraída de CheckoutController::pagarAbacatepay sem mudança de
 * regra: reaproveita checkout já criado, recusa pedido pago ou sem cobrança,
 * finaliza o rascunho, cria o checkout no gateway e registra no pedido. O site
 * traduz `motivo` em flash/redirect; a API do app, em JSON.
 */
class PagamentoAbacatepayService
{
    private $pedidoService;
    private $abacatePayService;

    public function __construct(?PedidoService $pedidoService = null, ?AbacatePayService $abacatePayService = null)
    {
        $this->pedidoService = $pedidoService ?: new PedidoService();
        $this->abacatePayService = $abacatePayService ?: new AbacatePayService();
    }

    /**
     * @param array $alunoPadrao nome, email, cpf, telefone usados quando o pedido não
     *                           tem os dados do pagador (no site vêm da sessão).
     *
     * @return array ok=true: url, reaproveitado | ok=false: motivo, message
     *   motivos: pedido_invalido | desativado | sem_acesso | ja_pago | sem_cobranca |
     *            falha_finalizacao | falha_recarregar | falha_checkout | falha_registro | sem_url
     */
    public function iniciarParaPedido($pedidoId, $usuarioId, array $alunoPadrao = array(), $ipAddress = null, $userAgent = null)
    {
        $pedidoId = (int) $pedidoId;
        if ($pedidoId <= 0) {
            return array('ok' => false, 'motivo' => 'pedido_invalido', 'message' => 'Pedido inválido.');
        }

        if (!$this->abacatePayService->isEnabled()) {
            return array('ok' => false, 'motivo' => 'desativado', 'message' => 'O pagamento online está desativado no momento.');
        }

        $detalhe = $this->pedidoService->detalharCheckout($pedidoId, $usuarioId, true);
        if (empty($detalhe['pedido'])) {
            return array('ok' => false, 'motivo' => 'sem_acesso', 'message' => 'Você não tem permissão para acessar este pedido.');
        }

        $pedido = $detalhe['pedido'];
        if (strtolower(trim((string) ($pedido['payment_gateway'] ?? ''))) === 'abacatepay' && !empty($pedido['payment_provider_payment_url'])) {
            Logger::info('checkout.abacatepay.reaproveitando_checkout', array(
                'pedido_id' => $pedidoId,
                'payment_provider_checkout_id' => isset($pedido['payment_provider_checkout_id']) ? $pedido['payment_provider_checkout_id'] : null,
                'payment_provider_payment_url' => $pedido['payment_provider_payment_url'],
            ));

            return array('ok' => true, 'url' => (string) $pedido['payment_provider_payment_url'], 'reaproveitado' => true);
        }
        if (in_array((string) $pedido['status'], array('pago', 'aprovado'), true)) {
            return array('ok' => false, 'motivo' => 'ja_pago', 'message' => 'Este pedido já está pago.');
        }

        if ((float) $pedido['total'] <= 0.0) {
            return array('ok' => false, 'motivo' => 'sem_cobranca', 'message' => 'Este pedido não possui cobrança. A liberação segue o fluxo gratuito.');
        }

        if ((string) $pedido['status'] === 'rascunho') {
            $finalizacao = $this->pedidoService->finalizarCheckout($pedidoId, $usuarioId, $ipAddress, $userAgent);
            if (empty($finalizacao['ok'])) {
                return array(
                    'ok' => false,
                    'motivo' => 'falha_finalizacao',
                    'message' => isset($finalizacao['message']) ? $finalizacao['message'] : 'Não foi possível preparar o pedido para pagamento.',
                );
            }

            $detalhe = $this->pedidoService->detalharCheckout($pedidoId, $usuarioId, true);
            if (empty($detalhe['pedido'])) {
                return array('ok' => false, 'motivo' => 'falha_recarregar', 'message' => 'Não foi possível recarregar o pedido.');
            }

            $pedido = $detalhe['pedido'];
        }

        $aluno = array(
            'id' => $usuarioId,
            'nome' => isset($pedido['pagador_nome']) && trim((string) $pedido['pagador_nome']) !== '' ? (string) $pedido['pagador_nome'] : (string) ($alunoPadrao['nome'] ?? ''),
            'email' => isset($pedido['pagador_email']) ? (string) $pedido['pagador_email'] : (string) ($alunoPadrao['email'] ?? ''),
            'cpf' => isset($pedido['pagador_cpf']) ? (string) $pedido['pagador_cpf'] : (string) ($alunoPadrao['cpf'] ?? ''),
            'telefone' => isset($pedido['pagador_telefone']) ? (string) $pedido['pagador_telefone'] : (string) ($alunoPadrao['telefone'] ?? ''),
            'cidade' => isset($pedido['pagador_cidade']) ? (string) $pedido['pagador_cidade'] : '',
            'estado' => isset($pedido['pagador_estado']) ? (string) $pedido['pagador_estado'] : '',
        );

        $checkout = $this->abacatePayService->createCheckout($pedido, $aluno, isset($pedido['itens']) && is_array($pedido['itens']) ? $pedido['itens'] : array());
        if (empty($checkout['ok'])) {
            return array(
                'ok' => false,
                'motivo' => 'falha_checkout',
                'message' => isset($checkout['message']) ? $checkout['message'] : 'Não foi possível iniciar o checkout da AbacatePay.',
            );
        }

        $registrado = $this->abacatePayService->registrarCheckoutNoPedido(
            $pedido,
            $checkout,
            $usuarioId,
            $ipAddress,
            $userAgent
        );

        if (empty($registrado['ok'])) {
            return array(
                'ok' => false,
                'motivo' => 'falha_registro',
                'message' => isset($registrado['message']) ? $registrado['message'] : 'Não foi possível registrar o checkout.',
            );
        }

        if (empty($checkout['checkout']['url'])) {
            return array('ok' => false, 'motivo' => 'sem_url', 'message' => 'A URL de pagamento não foi retornada pela AbacatePay.');
        }

        return array('ok' => true, 'url' => (string) $checkout['checkout']['url'], 'reaproveitado' => false);
    }
}

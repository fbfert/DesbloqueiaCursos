<?php

namespace App\Services\Payments;

use App\Core\Helpers;
use App\Core\Logger;
use App\Core\Database;
use App\Core\Env;
use App\Models\PagamentoGatewayConfiguracao;
use App\Models\PagamentoGatewayLog;
use App\Models\PagamentoGatewayTransacao;
use App\Models\Pedido;
use App\Services\PedidoService;
use Exception;

class AbacatePayService
{
    private $config;
    private $pedidoModel;
    private $pedidoService;
    private $gatewayLogModel;
    private $gatewayTransacaoModel;
    private $gatewayConfiguracaoModel;
    private $runtimeConfig;

    public function __construct()
    {
        $this->config = require BASE_PATH . '/config/payments.php';
        $this->pedidoModel = new Pedido();
        $this->pedidoService = new PedidoService();
        $this->gatewayLogModel = new PagamentoGatewayLog();
        $this->gatewayTransacaoModel = new PagamentoGatewayTransacao();
        $this->gatewayConfiguracaoModel = new PagamentoGatewayConfiguracao();
        $this->runtimeConfig = null;
    }

    public function isEnabled()
    {
        $config = $this->runtimeConfig();
        return !empty($config['enabled']);
    }

    public function hasApiKey()
    {
        $config = $this->runtimeConfig();
        return trim((string) ($config['api_key'] ?? '')) !== '';
    }

    public function createCheckout(array $pedido, array $aluno, array $itens): array
    {
        if (!$this->isEnabled()) {
            return array('ok' => false, 'message' => 'O pagamento online está desativado no momento.');
        }

        if (!$this->hasApiKey()) {
            Logger::error('abacatepay.checkout.sem_chave', array(
                'pedido_id' => isset($pedido['id']) ? (int) $pedido['id'] : null,
            ));
            return array('ok' => false, 'message' => 'O pagamento online está temporariamente indisponível.');
        }

        $pedidoId = isset($pedido['id']) ? (int) $pedido['id'] : 0;
        $amountCents = (int) round(((float) ($pedido['total'] ?? 0)) * 100);
        if ($amountCents <= 0) {
            return array('ok' => false, 'message' => 'Pedido sem valor para cobrança.');
        }

        $returnUrl = Helpers::url('checkout/resumo?pedido_id=' . $pedidoId);
        $completionUrl = Helpers::url('checkout/sucesso?pedido_id=' . $pedidoId);
        if ($this->isProductionLike() && (!$this->isHttpsUrl($returnUrl) || !$this->isHttpsUrl($completionUrl))) {
            return array('ok' => false, 'message' => 'As URLs de retorno precisam usar HTTPS em produção.');
        }

        $productExternalId = $this->buildProductExternalId($pedido);
        $productName = $this->buildProductName($pedido, $itens);

        $productResponse = $this->requestJson(
            'POST',
            '/products/create',
            array(
                'externalId' => $productExternalId,
                'name' => $productName,
                'price' => $amountCents,
                'currency' => 'BRL',
            )
        );

        if (empty($productResponse['ok'])) {
            Logger::error('abacatepay.produto_falhou', array(
                'pedido_id' => $pedidoId,
                'message' => isset($productResponse['message']) ? $productResponse['message'] : 'Falha ao criar produto.',
            ));

            return array(
                'ok' => false,
                'message' => isset($productResponse['message']) ? $productResponse['message'] : 'Falha ao criar o produto de cobrança.',
            );
        }

        $productData = isset($productResponse['data']) && is_array($productResponse['data']) ? $productResponse['data'] : array();
        $productId = isset($productData['id']) ? (string) $productData['id'] : '';
        if ($productId === '') {
            return array('ok' => false, 'message' => 'A resposta do produto veio incompleta.');
        }

        $checkoutPayload = array(
            'items' => array(
                array(
                    'id' => $productId,
                    'quantity' => 1,
                ),
            ),
            'methods' => array('PIX', 'CARD'),
            'returnUrl' => $returnUrl,
            'completionUrl' => $completionUrl,
            'externalId' => 'pedido-' . $pedidoId,
            'metadata' => array(
                'pedido_id' => $pedidoId,
                'pedido_codigo' => isset($pedido['codigo']) ? (string) $pedido['codigo'] : null,
                'origem' => 'portal_de_cursos',
            ),
        );

        $checkoutResponse = $this->requestJson('POST', '/checkouts/create', $checkoutPayload);
        if (empty($checkoutResponse['ok'])) {
            Logger::error('abacatepay.checkout.falhou', array(
                'pedido_id' => $pedidoId,
                'message' => isset($checkoutResponse['message']) ? $checkoutResponse['message'] : 'Falha ao criar checkout.',
            ));

            return array(
                'ok' => false,
                'message' => isset($checkoutResponse['message']) ? $checkoutResponse['message'] : 'Falha ao criar checkout.',
            );
        }

        $checkoutData = isset($checkoutResponse['data']) && is_array($checkoutResponse['data']) ? $checkoutResponse['data'] : array();
        $checkoutUrl = isset($checkoutData['url']) ? trim((string) $checkoutData['url']) : '';
        if ($checkoutUrl === '') {
            return array('ok' => false, 'message' => 'A URL de checkout não foi retornada pela AbacatePay.');
        }

        Logger::info('abacatepay.checkout.criado', array(
            'pedido_id' => $pedidoId,
            'checkout_id' => isset($checkoutData['id']) ? $checkoutData['id'] : null,
            'external_id' => isset($checkoutData['externalId']) ? $checkoutData['externalId'] : null,
            'amount' => isset($checkoutData['amount']) ? (int) $checkoutData['amount'] : $amountCents,
        ));

        return array(
            'ok' => true,
            'gateway' => 'abacatepay',
            'product' => array(
                'id' => $productId,
                'external_id' => $productExternalId,
                'name' => isset($productData['name']) ? $productData['name'] : $productName,
                'price' => isset($productData['price']) ? (int) $productData['price'] : $amountCents,
            ),
            'checkout' => array(
                'id' => isset($checkoutData['id']) ? (string) $checkoutData['id'] : null,
                'external_id' => isset($checkoutData['externalId']) ? (string) $checkoutData['externalId'] : 'pedido-' . $pedidoId,
                'url' => $checkoutUrl,
                'status' => isset($checkoutData['status']) ? (string) $checkoutData['status'] : 'PENDING',
                'amount' => isset($checkoutData['amount']) ? (int) $checkoutData['amount'] : $amountCents,
                'paid_amount' => isset($checkoutData['paidAmount']) ? (int) $checkoutData['paidAmount'] : null,
                'receipt_url' => isset($checkoutData['receiptUrl']) ? (string) $checkoutData['receiptUrl'] : null,
                'methods' => isset($checkoutData['methods']) && is_array($checkoutData['methods']) ? $checkoutData['methods'] : array('PIX', 'CARD'),
                'raw' => $checkoutData,
            ),
        );
    }

    public function registrarCheckoutNoPedido(array $pedido, array $checkoutResult, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedidoId = isset($pedido['id']) ? (int) $pedido['id'] : 0;
        if ($pedidoId <= 0) {
            return array('ok' => false, 'message' => 'Pedido inválido.');
        }

        $checkout = isset($checkoutResult['checkout']) && is_array($checkoutResult['checkout']) ? $checkoutResult['checkout'] : array();
        $product = isset($checkoutResult['product']) && is_array($checkoutResult['product']) ? $checkoutResult['product'] : array();

        $resultado = $this->pedidoService->registrarCheckoutGateway(
            $pedidoId,
            array(
                'payment_gateway' => 'abacatepay',
                'payment_external_id' => isset($checkout['external_id']) ? $checkout['external_id'] : 'pedido-' . $pedidoId,
                'payment_provider_checkout_id' => isset($checkout['id']) ? $checkout['id'] : null,
                'payment_provider_product_id' => isset($product['id']) ? $product['id'] : null,
                'payment_provider_product_external_id' => isset($product['external_id']) ? $product['external_id'] : null,
                'payment_provider_payment_url' => isset($checkout['url']) ? $checkout['url'] : null,
                'payment_provider_receipt_url' => isset($checkout['receipt_url']) ? $checkout['receipt_url'] : null,
                'payment_provider_status' => isset($checkout['status']) ? $checkout['status'] : 'PENDING',
                'payment_provider_amount' => isset($checkout['amount']) ? $this->centsToDecimal($checkout['amount']) : $this->centsToDecimal((int) round(((float) ($pedido['total'] ?? 0)) * 100)),
                'payment_provider_paid_amount' => isset($checkout['paid_amount']) ? $this->centsToDecimal($checkout['paid_amount']) : null,
                'payment_provider_method' => null,
                'payment_provider_payload' => $this->encodePayload(array(
                    'product' => $product,
                    'checkout' => array(
                        'id' => isset($checkout['id']) ? $checkout['id'] : null,
                        'external_id' => isset($checkout['external_id']) ? $checkout['external_id'] : null,
                        'url' => isset($checkout['url']) ? $checkout['url'] : null,
                        'status' => isset($checkout['status']) ? $checkout['status'] : null,
                        'amount' => isset($checkout['amount']) ? $checkout['amount'] : null,
                        'methods' => isset($checkout['methods']) ? $checkout['methods'] : array(),
                    ),
                )),
                'payment_provider_updated_at' => date('Y-m-d H:i:s'),
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        if (empty($resultado['ok'])) {
            return $resultado;
        }

        return array(
            'ok' => true,
            'checkout_url' => isset($checkout['url']) ? $checkout['url'] : null,
        );
    }

    public function handleWebhook(string $rawBody, array $headers = array(), $ipAddress = null, $userAgent = null)
    {
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $logId = $this->gatewayLogModel->create(array(
                'gateway' => 'abacatepay',
                'payload' => $rawBody,
                'processed' => 0,
                'error_message' => 'JSON inválido.',
            ));

            Logger::error('abacatepay.webhook.json_invalido', array('log_id' => $logId));

            return array('ok' => false, 'status' => 422, 'message' => 'JSON inválido.');
        }

        $eventType = isset($payload['event']) ? trim((string) $payload['event']) : '';
        $eventId = isset($payload['id']) ? trim((string) $payload['id']) : '';
        $checkout = isset($payload['data']['checkout']) && is_array($payload['data']['checkout']) ? $payload['data']['checkout'] : array();
        $externalId = isset($checkout['externalId']) ? trim((string) $checkout['externalId']) : '';

        $logId = $this->gatewayLogModel->create(array(
            'gateway' => 'abacatepay',
            'event_id' => $eventId !== '' ? $eventId : null,
            'event_type' => $eventType !== '' ? $eventType : null,
            'payload' => $rawBody,
            'processed' => 0,
        ));

        if (!$this->verifyWebhookSignature($rawBody, $this->extractSignatureFromHeaders($headers))) {
            $this->gatewayLogModel->markProcessed($logId, false, 'Assinatura inválida.');
            Logger::error('abacatepay.webhook.assinatura_invalida', array('log_id' => $logId));

            return array('ok' => false, 'status' => 401, 'message' => 'Assinatura inválida.');
        }

        if ($eventType === '' || $eventId === '') {
            $this->gatewayLogModel->markProcessed($logId, false, 'Evento ou identificador ausente.');
            Logger::error('abacatepay.webhook.incompleto', array('log_id' => $logId));

            return array('ok' => false, 'status' => 422, 'message' => 'Evento incompleto.');
        }

        if ($this->gatewayTransacaoModel->existsByEventId('abacatepay', $eventId)) {
            $this->gatewayLogModel->markProcessed($logId, true, 'Evento duplicado ignorado.');
            Logger::info('abacatepay.webhook.duplicado', array('event_id' => $eventId));

            return array('ok' => true, 'duplicate' => true, 'message' => 'Evento duplicado ignorado.');
        }

        $pedido = $externalId !== '' ? $this->pedidoModel->findByPaymentGatewayExternalId('abacatepay', $externalId) : null;
        if (!$pedido && $externalId !== '') {
            $pedidoIdParsed = $this->extrairPedidoIdDoExternalId($externalId);
            if ($pedidoIdParsed > 0) {
                $pedido = $this->pedidoModel->findById($pedidoIdParsed);
            }
        }

        if (!$pedido) {
            $this->salvarTransacaoGateway(array(
                'pedido_id' => null,
                'gateway' => 'abacatepay',
                'external_id' => $externalId !== '' ? $externalId : null,
                'provider_id' => isset($checkout['id']) ? $checkout['id'] : null,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'status' => 'PEDIDO_NAO_ENCONTRADO',
                'amount' => isset($checkout['amount']) ? $this->centsToDecimal($checkout['amount']) : null,
                'paid_amount' => isset($checkout['paidAmount']) ? $this->centsToDecimal($checkout['paidAmount']) : null,
                'payment_method' => isset($payload['data']['payerInformation']['method']) ? (string) $payload['data']['payerInformation']['method'] : null,
                'receipt_url' => isset($checkout['receiptUrl']) ? (string) $checkout['receiptUrl'] : null,
                'raw_payload' => $rawBody,
            ));

            $this->gatewayLogModel->markProcessed($logId, false, 'Pedido não encontrado.');
            Logger::error('abacatepay.webhook.pedido_nao_encontrado', array(
                'event_id' => $eventId,
                'external_id' => $externalId,
            ));

            return array('ok' => false, 'status' => 404, 'message' => 'Pedido não encontrado.');
        }

        if (in_array($eventType, array('checkout.completed', 'checkout.disputed', 'checkout.refunded'), true)) {
            $this->persistirDadosGatewayNoPedido(
                $pedido,
                $checkout,
                $payload,
                $eventType,
                $eventId,
                $eventType === 'checkout.completed' && isset($checkout['status']) ? strtoupper((string) $checkout['status']) : strtoupper(str_replace('checkout.', '', $eventType))
            );
        }

        if ($eventType === 'checkout.completed') {
            $statusCheckout = strtoupper((string) ($checkout['status'] ?? ''));
            $amountPedido = $this->centsToDecimal(isset($checkout['amount']) ? (int) $checkout['amount'] : 0);
            $paidAmountPedido = $this->centsToDecimal(isset($checkout['paidAmount']) ? (int) $checkout['paidAmount'] : 0);
            $valorPedido = (float) ($pedido['total'] ?? 0);

            if ($statusCheckout !== 'PAID' || abs($valorPedido - $paidAmountPedido) > 0.01 || abs($valorPedido - $amountPedido) > 0.01) {
                $this->gatewayTransacaoModel->create(array(
                    'pedido_id' => (int) $pedido['id'],
                    'gateway' => 'abacatepay',
                    'external_id' => $externalId,
                    'provider_id' => isset($checkout['id']) ? $checkout['id'] : null,
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'status' => 'AMOUNT_MISMATCH',
                    'amount' => $amountPedido,
                    'paid_amount' => $paidAmountPedido,
                    'payment_method' => isset($payload['data']['payerInformation']['method']) ? (string) $payload['data']['payerInformation']['method'] : null,
                    'receipt_url' => isset($checkout['receiptUrl']) ? (string) $checkout['receiptUrl'] : null,
                    'raw_payload' => $rawBody,
                ));

                $this->gatewayLogModel->markProcessed($logId, false, 'Valor ou status divergente do pedido.');
                Logger::error('abacatepay.webhook.valor_divergente', array(
                    'pedido_id' => (int) $pedido['id'],
                    'event_id' => $eventId,
                ));

                return array(
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Valor ou status divergente do pedido.',
                );
            }

            $confirmacao = $this->pedidoService->confirmarPagamentoGateway(
                (int) $pedido['id'],
                array(
                    'gateway' => 'abacatepay',
                    'event_type' => $eventType,
                    'event_id' => $eventId,
                    'observacao' => 'Pagamento confirmado via AbacatePay.',
                ),
                null,
                $ipAddress,
                $userAgent
            );

            if (empty($confirmacao['ok'])) {
                $mensagemConfirmacao = isset($confirmacao['message']) ? $confirmacao['message'] : 'Falha ao confirmar pagamento.';
                if (stripos($mensagemConfirmacao, 'nao pode ser confirmado neste status') !== false) {
                    $this->salvarTransacaoGateway(array(
                        'pedido_id' => (int) $pedido['id'],
                        'gateway' => 'abacatepay',
                        'external_id' => $externalId,
                        'provider_id' => isset($checkout['id']) ? $checkout['id'] : null,
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                        'status' => 'PEDIDO_BLOQUEADO',
                        'amount' => $amountPedido,
                        'paid_amount' => $paidAmountPedido,
                        'payment_method' => isset($payload['data']['payerInformation']['method']) ? (string) $payload['data']['payerInformation']['method'] : null,
                        'receipt_url' => isset($checkout['receiptUrl']) ? (string) $checkout['receiptUrl'] : null,
                        'raw_payload' => $rawBody,
                    ));
                    $this->gatewayLogModel->markProcessed($logId, true, $mensagemConfirmacao);
                    Logger::error('abacatepay.webhook.confirmacao_bloqueada', array(
                        'pedido_id' => (int) $pedido['id'],
                        'event_id' => $eventId,
                        'message' => $mensagemConfirmacao,
                    ));

                    return array(
                        'ok' => true,
                        'status' => 200,
                        'message' => $mensagemConfirmacao,
                    );
                }

                $this->gatewayLogModel->markProcessed($logId, false, $mensagemConfirmacao);
                Logger::error('abacatepay.webhook.confirmacao_falhou', array(
                    'pedido_id' => (int) $pedido['id'],
                    'event_id' => $eventId,
                    'message' => $mensagemConfirmacao,
                ));

                return array(
                    'ok' => false,
                    'status' => 500,
                    'message' => $mensagemConfirmacao,
                );
            }

            $this->salvarTransacaoGateway(array(
                'pedido_id' => (int) $pedido['id'],
                'gateway' => 'abacatepay',
                'external_id' => $externalId,
                'provider_id' => isset($checkout['id']) ? $checkout['id'] : null,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'status' => 'PAID',
                'amount' => $amountPedido,
                'paid_amount' => $paidAmountPedido,
                'payment_method' => isset($payload['data']['payerInformation']['method']) ? (string) $payload['data']['payerInformation']['method'] : null,
                'receipt_url' => isset($checkout['receiptUrl']) ? (string) $checkout['receiptUrl'] : null,
                'raw_payload' => $rawBody,
            ));

            $this->gatewayLogModel->markProcessed($logId, true, null);

            Logger::info('abacatepay.webhook.checkout_completed.processado', array(
                'pedido_id' => (int) $pedido['id'],
                'event_id' => $eventId,
                'external_id' => $externalId,
            ));

            return array('ok' => true, 'status' => 200, 'message' => 'Pagamento confirmado.');
        }

        if (in_array($eventType, array('checkout.disputed', 'checkout.refunded'), true)) {
            $statusEvento = strtoupper(str_replace('checkout.', '', $eventType));
            $this->salvarTransacaoGateway(array(
                'pedido_id' => (int) $pedido['id'],
                'gateway' => 'abacatepay',
                'external_id' => $externalId,
                'provider_id' => isset($checkout['id']) ? $checkout['id'] : null,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'status' => $statusEvento,
                'amount' => isset($checkout['amount']) ? $this->centsToDecimal((int) $checkout['amount']) : null,
                'paid_amount' => isset($checkout['paidAmount']) ? $this->centsToDecimal((int) $checkout['paidAmount']) : null,
                'payment_method' => isset($payload['data']['payerInformation']['method']) ? (string) $payload['data']['payerInformation']['method'] : null,
                'receipt_url' => isset($checkout['receiptUrl']) ? (string) $checkout['receiptUrl'] : null,
                'raw_payload' => $rawBody,
            ));

            $this->gatewayLogModel->markProcessed($logId, true, null);

            Logger::info('abacatepay.webhook.evento_registrado', array(
                'pedido_id' => (int) $pedido['id'],
                'event_id' => $eventId,
                'event_type' => $eventType,
            ));

            return array('ok' => true, 'status' => 200, 'message' => 'Evento registrado.');
        }

        $this->gatewayLogModel->markProcessed($logId, true, 'Evento ignorado.');
        Logger::info('abacatepay.webhook.ignorado', array(
            'event_id' => $eventId,
            'event_type' => $eventType,
        ));

        return array('ok' => true, 'status' => 200, 'message' => 'Evento ignorado.');
    }

    public function verifyWebhookSignature($rawBody, $signatureFromHeader)
    {
        $secret = trim((string) $this->webhookSecret());
        $signatureFromHeader = trim((string) $signatureFromHeader);

        if ($secret === '' || $signatureFromHeader === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        return hash_equals($expected, $signatureFromHeader);
    }

    public function extractSignatureFromHeaders(array $headers)
    {
        $candidates = array(
            'X-Webhook-Signature',
            'X-Abacate-Signature',
            'x-webhook-signature',
            'x-abacate-signature',
        );

        foreach ($candidates as $headerName) {
            if (isset($headers[$headerName]) && trim((string) $headers[$headerName]) !== '') {
                return (string) $headers[$headerName];
            }
        }

        return '';
    }

    private function requestJson($method, $endpoint, array $payload = null)
    {
        $runtimeConfig = $this->runtimeConfig();
        $url = rtrim((string) ($runtimeConfig['base_url'] ?? 'https://api.abacatepay.com/v2'), '/') . '/' . ltrim($endpoint, '/');
        $body = $payload !== null ? $this->encodePayload($payload) : null;
        if ($payload !== null && $body === null) {
            return array('ok' => false, 'message' => 'Falha ao serializar a requisição.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return array('ok' => false, 'message' => 'Falha ao iniciar a requisição HTTP.');
        }

        $headers = array(
            'Authorization: Bearer ' . $runtimeConfig['api_key'],
            'Accept: application/json',
            'Content-Type: application/json',
        );

        curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST => strtoupper((string) $method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ));

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $curlErrorNo = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrorNo) {
            return array(
                'ok' => false,
                'message' => 'Falha de comunicação com a AbacatePay.',
                'error' => $curlError,
                'http_status' => $httpStatus,
            );
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            return array(
                'ok' => false,
                'message' => 'Resposta inválida da AbacatePay.',
                'http_status' => $httpStatus,
                'response' => is_string($response) ? substr($response, 0, 500) : null,
            );
        }

        if ($httpStatus < 200 || $httpStatus >= 300 || (isset($decoded['success']) && !$decoded['success'])) {
            $message = isset($decoded['error']) && $decoded['error'] !== null
                ? (string) $decoded['error']
                : 'A AbacatePay retornou um erro ao processar a requisição.';

            return array(
                'ok' => false,
                'message' => $message,
                'http_status' => $httpStatus,
                'response' => $decoded,
            );
        }

        return array(
            'ok' => true,
            'data' => isset($decoded['data']) ? $decoded['data'] : null,
            'response' => $decoded,
            'http_status' => $httpStatus,
        );
    }

    private function persistirDadosGatewayNoPedido(array $pedido, array $checkout, array $payload, $eventType, $eventId, $providerStatus = null)
    {
        $pedidoId = isset($pedido['id']) ? (int) $pedido['id'] : 0;
        if ($pedidoId <= 0) {
            return;
        }

        $this->pedidoModel->updatePaymentGatewayData($pedidoId, array(
            'payment_gateway' => 'abacatepay',
            'payment_external_id' => isset($checkout['externalId']) ? (string) $checkout['externalId'] : 'pedido-' . $pedidoId,
            'payment_provider_checkout_id' => isset($checkout['id']) ? (string) $checkout['id'] : null,
            'payment_provider_product_id' => isset($checkout['items'][0]['id']) ? (string) $checkout['items'][0]['id'] : null,
            'payment_provider_product_external_id' => isset($checkout['items'][0]['externalId']) ? (string) $checkout['items'][0]['externalId'] : null,
            'payment_provider_payment_url' => isset($checkout['url']) ? (string) $checkout['url'] : null,
            'payment_provider_receipt_url' => isset($checkout['receiptUrl']) ? (string) $checkout['receiptUrl'] : null,
            'payment_provider_status' => $providerStatus !== null ? $providerStatus : strtoupper(str_replace('checkout.', '', $eventType)),
            'payment_provider_amount' => isset($checkout['amount']) ? $this->centsToDecimal((int) $checkout['amount']) : null,
            'payment_provider_paid_amount' => isset($checkout['paidAmount']) ? $this->centsToDecimal((int) $checkout['paidAmount']) : null,
            'payment_provider_method' => isset($payload['data']['payerInformation']['method']) ? (string) $payload['data']['payerInformation']['method'] : null,
            'payment_provider_payload' => $this->encodePayload(array(
                'event_id' => $eventId,
                'event_type' => $eventType,
                'checkout' => array(
                    'id' => isset($checkout['id']) ? $checkout['id'] : null,
                    'externalId' => isset($checkout['externalId']) ? $checkout['externalId'] : null,
                    'status' => isset($checkout['status']) ? $checkout['status'] : null,
                    'amount' => isset($checkout['amount']) ? $checkout['amount'] : null,
                    'paidAmount' => isset($checkout['paidAmount']) ? $checkout['paidAmount'] : null,
                    'receiptUrl' => isset($checkout['receiptUrl']) ? $checkout['receiptUrl'] : null,
                ),
            )),
            'payment_provider_updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    private function buildProductExternalId(array $pedido)
    {
        $pedidoId = isset($pedido['id']) ? (int) $pedido['id'] : 0;
        $suffix = substr(sha1(uniqid((string) $pedidoId, true)), 0, 10);

        return 'pedido-' . $pedidoId . '-produto-' . $suffix;
    }

    private function buildProductName(array $pedido, array $itens)
    {
        $nomes = array();
        foreach ($itens as $item) {
            if (!empty($item['curso_nome'])) {
                $nomes[] = (string) $item['curso_nome'];
            }
        }

        $nomes = array_values(array_unique($nomes));
        $base = !empty($pedido['codigo']) ? 'Pedido ' . (string) $pedido['codigo'] : 'Pedido AbacatePay';

        if (!empty($nomes)) {
            $base .= ' - ' . implode(', ', $nomes);
        }

        return function_exists('mb_substr') ? mb_substr($base, 0, 120, 'UTF-8') : substr($base, 0, 120);
    }

    private function centsToDecimal($cents)
    {
        return round(((int) $cents) / 100, 2);
    }

    private function encodePayload(array $data)
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? null : $json;
    }

    private function salvarTransacaoGateway(array $data)
    {
        try {
            return array(
                'ok' => true,
                'id' => $this->gatewayTransacaoModel->create($data),
            );
        } catch (Exception $exception) {
            if ($this->isDuplicateKeyException($exception)) {
                return array('ok' => false, 'duplicate' => true);
            }

            throw $exception;
        }
    }

    private function isDuplicateKeyException(Exception $exception)
    {
        $message = strtolower((string) $exception->getMessage());

        return strpos($message, 'duplicate') !== false || strpos($message, '1062') !== false;
    }

    private function webhookSecret()
    {
        $runtimeConfig = $this->runtimeConfig();

        return $runtimeConfig['webhook_hmac_secret'] ?? '';
    }

    private function isProductionLike(array $runtimeConfig = null)
    {
        $appConfig = require BASE_PATH . '/config/app.php';
        $env = strtolower((string) ($appConfig['env'] ?? Env::get('APP_ENV', 'production')));
        $runtimeConfig = $runtimeConfig ?: $this->runtimeConfig();
        $mode = strtolower((string) ($runtimeConfig['mode'] ?? 'dev'));
        $environment = strtolower((string) ($runtimeConfig['environment'] ?? 'sandbox'));

        return in_array($env, array('production', 'prod'), true)
            || in_array($mode, array('production', 'prod', 'producao'), true)
            || in_array($environment, array('production', 'prod', 'producao'), true);
    }

    private function isHttpsUrl($url)
    {
        return strtolower((string) parse_url((string) $url, PHP_URL_SCHEME)) === 'https';
    }

    private function extrairPedidoIdDoExternalId($externalId)
    {
        if (preg_match('/pedido-(\d+)/', (string) $externalId, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function runtimeConfig()
    {
        if (is_array($this->runtimeConfig)) {
            return $this->runtimeConfig;
        }

        $databaseConfig = $this->gatewayConfiguracaoModel->runtimeAbacatePayConfig();
        if (!empty($databaseConfig['source']) && $databaseConfig['source'] === 'database') {
            $this->runtimeConfig = $databaseConfig;
            return $this->runtimeConfig;
        }

        $fallback = $this->config['abacatepay'];
        $this->runtimeConfig = array(
            'source' => 'env',
            'enabled' => !empty($fallback['enabled']),
            'mode' => isset($fallback['mode']) ? $fallback['mode'] : 'dev',
            'environment' => isset($fallback['environment']) ? $fallback['environment'] : 'sandbox',
            'api_key' => isset($fallback['api_key']) ? $fallback['api_key'] : '',
            'webhook_hmac_secret' => isset($fallback['webhook_secret']) ? $fallback['webhook_secret'] : '',
            'webhook_url_secret' => isset($fallback['webhook_url_secret']) ? $fallback['webhook_url_secret'] : '',
            'base_url' => isset($fallback['base_url']) ? $fallback['base_url'] : 'https://api.abacatepay.com/v2',
            'webhook_url_publica' => $this->gatewayConfiguracaoModel->buildWebhookUrl(isset($fallback['webhook_url_secret']) ? $fallback['webhook_url_secret'] : ''),
        );

        return $this->runtimeConfig;
    }
}

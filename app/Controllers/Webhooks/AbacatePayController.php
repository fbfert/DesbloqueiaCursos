<?php

namespace App\Controllers\Webhooks;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Models\PagamentoGatewayConfiguracao;
use App\Services\Payments\AbacatePayService;

class AbacatePayController extends Controller
{
    private $abacatePayService;
    private $configModel;

    public function __construct()
    {
        $this->abacatePayService = new AbacatePayService();
        $this->configModel = new PagamentoGatewayConfiguracao();
    }

    public function handle(Request $request)
    {
        $rawBody = file_get_contents('php://input');
        $headers = array(
            'X-Webhook-Signature' => $request->header('X-Webhook-Signature', ''),
            'X-Abacate-Signature' => $request->header('X-Abacate-Signature', ''),
        );

        if (!$this->validateUrlSecret($request)) {
            Logger::error('abacatepay.webhook.url_secret_invalido', array(
                'ip_address' => $request->ip(),
            ));

            return $this->json(array('ok' => false, 'message' => 'URL inválida.'), 401);
        }

        $resultado = $this->abacatePayService->handleWebhook(
            $rawBody !== false ? $rawBody : '',
            $headers,
            $request->ip(),
            $request->userAgent()
        );

        $status = isset($resultado['status']) ? (int) $resultado['status'] : 200;
        unset($resultado['status']);

        return $this->json($resultado, $status);
    }

    private function validateUrlSecret(Request $request)
    {
        $received = '';
        foreach (array('webhookSecret', 'secret', 'token', 'webhook_secret') as $param) {
            $value = trim((string) $request->query($param, ''));
            if ($value !== '') {
                $received = $value;
                break;
            }
        }

        if ($received === '') {
            return false;
        }

        $candidates = $this->urlSecretCandidates();
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && hash_equals($candidate, $received)) {
                return true;
            }
        }

        return false;
    }

    private function urlSecretCandidates()
    {
        $candidates = array();

        $runtime = $this->configModel->runtimeAbacatePayConfig();
        if (!empty($runtime['webhook_url_secret'])) {
            $candidates[] = (string) $runtime['webhook_url_secret'];
        }

        $envSecret = trim((string) $this->configModel->getWebhookUrlSecret());
        if ($envSecret !== '') {
            $candidates[] = $envSecret;
        }

        return array_values(array_unique($candidates));
    }
}

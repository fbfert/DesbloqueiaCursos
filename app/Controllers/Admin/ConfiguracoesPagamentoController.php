<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Models\PagamentoGatewayConfiguracao;
use App\Services\AuditService;

class ConfiguracoesPagamentoController extends Controller
{
    private $configModel;
    private $auditService;

    public function __construct()
    {
        $this->configModel = new PagamentoGatewayConfiguracao();
        $this->auditService = new AuditService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/configuracoes-pagamento/index', array(
            'title' => 'ConfiguraÃ§Ãµes de Pagamento',
            'configuracao' => $this->configModel->toSafeArray(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function salvar(Request $request)
    {
        $resultado = $this->configModel->salvarAbacatePay($request->all(), Session::get('usuario_id'));
        if (empty($resultado['ok'])) {
            $this->flashErrors($resultado);
            return $this->redirect('/admin/configuracoes-pagamento');
        }

        $this->auditService->record(
            'pagamento_gateway_configuracoes.atualizada',
            'pagamento_gateway_configuracoes',
            isset($resultado['id']) ? (int) $resultado['id'] : null,
            array(
                'gateway' => 'abacatepay',
                'ativo' => !empty($request->input('ativo', 0)) ? 1 : 0,
                'ambiente' => $this->normalizeAmbiente($request->input('ambiente', 'sandbox')),
                'api_key_alterada' => trim((string) $request->input('api_key', '')) !== '' ? 1 : 0,
                'webhook_hmac_secret_alterado' => trim((string) $request->input('webhook_hmac_secret', '')) !== '' ? 1 : 0,
                'webhook_url_secret_alterado' => trim((string) $request->input('webhook_url_secret', '')) !== '' ? 1 : 0,
                'usuario_id' => Session::get('usuario_id'),
            ),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        $mensagem = 'Configurações de pagamento atualizadas com sucesso.';
        if (!empty($resultado['warnings']) && is_array($resultado['warnings'])) {
            $mensagem .= ' ' . implode(' ', array_map('strval', $resultado['warnings']));
        }

        Session::flash('success', $mensagem);
        return $this->redirect('/admin/configuracoes-pagamento');
    }

    public function testarAbacatePay(Request $request)
    {
        $config = $this->configModel->runtimeAbacatePayConfig();

        if (empty($config['enabled'])) {
            $this->registrarTeste('erro', 'O gateway AbacatePay estÃ¡ desativado.', Session::get('usuario_id'), $request);
            Session::flash('errors', array('O gateway AbacatePay estÃ¡ desativado.'));
            return $this->redirect('/admin/configuracoes-pagamento');
        }

        if (trim((string) ($config['api_key'] ?? '')) === '') {
            $this->registrarTeste('erro', 'ConfiguraÃ§Ã£o sem API Key.', Session::get('usuario_id'), $request);
            Session::flash('errors', array('ConfiguraÃ§Ã£o incompleta: a API Key nÃ£o estÃ¡ disponÃ­vel no banco nem no .env.'));
            return $this->redirect('/admin/configuracoes-pagamento');
        }

        $mensagem = 'ConfiguraÃ§Ã£o salva. NÃ£o foi executado teste remoto porque o service atual nÃ£o possui endpoint nÃ£o destrutivo de validaÃ§Ã£o.';
        $this->configModel->registrarUltimoTeste('ok', $mensagem, Session::get('usuario_id'));
        $this->auditService->record(
            'pagamento_gateway_configuracoes.teste',
            'pagamento_gateway_configuracoes',
            null,
            array(
                'gateway' => 'abacatepay',
                'resultado' => 'ok',
                'usuario_id' => Session::get('usuario_id'),
            ),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        Session::flash('success', $mensagem);
        return $this->redirect('/admin/configuracoes-pagamento');
    }

    public function gerarWebhookSecret(Request $request)
    {
        $novoSecret = $this->gerarSecretSeguro();
        if ($novoSecret === '') {
            Session::flash('errors', array('NÃ£o foi possÃ­vel gerar um novo secret.'));
            return $this->redirect('/admin/configuracoes-pagamento');
        }

        $resultado = $this->configModel->salvarAbacatePay(array(
            'ativo' => (int) $request->input('ativo', 0),
            'ambiente' => $request->input('ambiente', 'sandbox'),
            'webhook_url_secret' => $novoSecret,
        ), Session::get('usuario_id'));

        if (empty($resultado['ok'])) {
            $this->flashErrors($resultado);
            return $this->redirect('/admin/configuracoes-pagamento');
        }

        $this->auditService->record(
            'pagamento_gateway_configuracoes.webhook_secret_gerado',
            'pagamento_gateway_configuracoes',
            isset($resultado['id']) ? (int) $resultado['id'] : null,
            array(
                'gateway' => 'abacatepay',
                'webhook_url_secret_alterado' => 1,
                'usuario_id' => Session::get('usuario_id'),
            ),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        Session::flash('success', 'Novo secret de URL gerado com sucesso.');
        return $this->redirect('/admin/configuracoes-pagamento');
    }

    private function flashErrors(array $resultado)
    {
        $errors = array();

        if (!empty($resultado['errors']) && is_array($resultado['errors'])) {
            foreach ($resultado['errors'] as $error) {
                if (is_string($error)) {
                    $errors[] = $error;
                } elseif (is_array($error)) {
                    foreach ($error as $message) {
                        if (is_string($message)) {
                            $errors[] = $message;
                        }
                    }
                }
            }
        }

        if (empty($errors) && !empty($resultado['message'])) {
            $errors[] = (string) $resultado['message'];
        }

        if (empty($errors)) {
            $errors[] = 'NÃ£o foi possÃ­vel salvar as configuraÃ§Ãµes de pagamento.';
        }

        Session::flash('errors', $errors);
    }

    private function registrarTeste($status, $mensagem, $usuarioId, Request $request)
    {
        $this->configModel->registrarUltimoTeste($status, $mensagem, $usuarioId);
        Logger::info('pagamento_gateway_configuracoes.teste', array(
            'status' => $status,
            'usuario_id' => $usuarioId,
            'ip_address' => $request->ip(),
        ));
    }

    private function gerarSecretSeguro()
    {
        try {
            return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        } catch (\Throwable $exception) {
            Logger::error('pagamento_gateway_configuracoes.secret_erro', array(
                'message' => $exception->getMessage(),
            ));

            return '';
        }
    }

    private function normalizeAmbiente($value)
    {
        $value = strtolower(trim((string) $value));
        if (in_array($value, array('producao', 'produÃ§Ã£o', 'production', 'prod'), true)) {
            return 'producao';
        }

        return 'sandbox';
    }
}


<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Env;
use App\Core\Helpers;
use App\Support\Crypto;
use PDO;

class PagamentoGatewayConfiguracao
{
    private $current;

    public function buscarPorGateway($gateway)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM pagamento_gateway_configuracoes
             WHERE gateway = :gateway
             LIMIT 1'
        );

        $stmt->execute(array('gateway' => (string) $gateway));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->current = $row ?: null;

        return $this->current;
    }

    public function buscarAbacatePay()
    {
        return $this->buscarPorGateway('abacatepay');
    }

    public function salvarAbacatePay(array $dados, $usuarioId)
    {
        $current = $this->buscarAbacatePay();
        $appKeyAvailable = Crypto::hasKey();

        $apiKeyInput = isset($dados['api_key']) ? trim((string) $dados['api_key']) : '';
        $webhookHmacInput = isset($dados['webhook_hmac_secret']) ? trim((string) $dados['webhook_hmac_secret']) : '';
        $webhookUrlInput = isset($dados['webhook_url_secret']) ? trim((string) $dados['webhook_url_secret']) : '';
        $warnings = array();
        if (!$appKeyAvailable && ($apiKeyInput !== '' || $webhookHmacInput !== '' || $webhookUrlInput !== '')) {
            $warnings[] = 'APP_KEY ausente: os segredos informados não foram salvos. Configure a APP_KEY no .env para persistir API Key e secrets criptografados.';
        }

        if ($current) {
            $updatePayload = array(
                'ativo' => $this->normalizarBool($dados, 'ativo'),
                'ambiente' => $this->normalizarAmbiente(isset($dados['ambiente']) ? $dados['ambiente'] : 'sandbox'),
                'api_key_encrypted' => $this->resolveEncryptedValue($apiKeyInput, $current, 'api_key_encrypted', $appKeyAvailable),
                'webhook_hmac_secret_encrypted' => $this->resolveEncryptedValue($webhookHmacInput, $current, 'webhook_hmac_secret_encrypted', $appKeyAvailable),
                'webhook_url_secret_encrypted' => $this->resolveEncryptedValue($webhookUrlInput, $current, 'webhook_url_secret_encrypted', $appKeyAvailable),
                'webhook_url_publica' => $this->resolveWebhookUrlPublica($webhookUrlInput, $current),
                'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
                'id' => (int) $current['id'],
            );

            $stmt = Database::connection()->prepare(
                'UPDATE pagamento_gateway_configuracoes
                 SET ativo = :ativo,
                     ambiente = :ambiente,
                     api_key_encrypted = :api_key_encrypted,
                     webhook_hmac_secret_encrypted = :webhook_hmac_secret_encrypted,
                     webhook_url_secret_encrypted = :webhook_url_secret_encrypted,
                     webhook_url_publica = :webhook_url_publica,
                     atualizado_por = :atualizado_por,
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $stmt->execute($updatePayload);
            $this->current = $this->buscarAbacatePay();

            return array('ok' => true, 'id' => (int) $current['id'], 'warnings' => $warnings);
        }

        $insertPayload = array(
            'gateway' => 'abacatepay',
            'ativo' => $this->normalizarBool($dados, 'ativo'),
            'ambiente' => $this->normalizarAmbiente(isset($dados['ambiente']) ? $dados['ambiente'] : 'sandbox'),
            'api_key_encrypted' => $this->resolveEncryptedValue($apiKeyInput, $current, 'api_key_encrypted', $appKeyAvailable),
            'webhook_hmac_secret_encrypted' => $this->resolveEncryptedValue($webhookHmacInput, $current, 'webhook_hmac_secret_encrypted', $appKeyAvailable),
            'webhook_url_secret_encrypted' => $this->resolveEncryptedValue($webhookUrlInput, $current, 'webhook_url_secret_encrypted', $appKeyAvailable),
            'webhook_url_publica' => $this->resolveWebhookUrlPublica($webhookUrlInput, $current),
            'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
        );

        $stmt = Database::connection()->prepare(
            'INSERT INTO pagamento_gateway_configuracoes
             (gateway, ativo, ambiente, api_key_encrypted, webhook_hmac_secret_encrypted, webhook_url_secret_encrypted, webhook_url_publica, atualizado_por, created_at, updated_at)
             VALUES
             (:gateway, :ativo, :ambiente, :api_key_encrypted, :webhook_hmac_secret_encrypted, :webhook_url_secret_encrypted, :webhook_url_publica, :atualizado_por, NOW(), NOW())'
        );

        $stmt->execute($insertPayload);
        $this->current = $this->buscarAbacatePay();

        return array('ok' => true, 'id' => (int) Database::connection()->lastInsertId(), 'warnings' => $warnings);
    }

    public function registrarUltimoTeste($status, $mensagem, $usuarioId = null)
    {
        $current = $this->buscarAbacatePay();

        if (!$current) {
            $stmt = Database::connection()->prepare(
                'INSERT INTO pagamento_gateway_configuracoes
                 (gateway, ativo, ambiente, api_key_encrypted, webhook_hmac_secret_encrypted, webhook_url_secret_encrypted, webhook_url_publica, ultimo_teste_status, ultimo_teste_mensagem, ultimo_teste_em, atualizado_por, created_at, updated_at)
                 VALUES
                 (:gateway, 0, "sandbox", NULL, NULL, NULL, NULL, :ultimo_teste_status, :ultimo_teste_mensagem, NOW(), :atualizado_por, NOW(), NOW())'
            );

            $stmt->execute(array(
                'gateway' => 'abacatepay',
                'ultimo_teste_status' => $status,
                'ultimo_teste_mensagem' => $mensagem,
                'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
            ));

            $this->current = $this->buscarAbacatePay();

            return true;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE pagamento_gateway_configuracoes
             SET ultimo_teste_status = :ultimo_teste_status,
                 ultimo_teste_mensagem = :ultimo_teste_mensagem,
                 ultimo_teste_em = NOW(),
                 atualizado_por = :atualizado_por,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'ultimo_teste_status' => $status,
            'ultimo_teste_mensagem' => $mensagem,
            'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
            'id' => (int) $current['id'],
        ));

        $this->current = $this->buscarAbacatePay();

        return true;
    }

    public function getApiKey()
    {
        $current = $this->current ?: $this->buscarAbacatePay();
        $plain = $this->decryptField($current, 'api_key_encrypted');
        if ($plain !== null && $plain !== '') {
            return $plain;
        }

        return trim((string) Env::get('ABACATEPAY_API_KEY', ''));
    }

    public function getWebhookHmacSecret()
    {
        $current = $this->current ?: $this->buscarAbacatePay();
        $plain = $this->decryptField($current, 'webhook_hmac_secret_encrypted');
        if ($plain !== null && $plain !== '') {
            return $plain;
        }

        return trim((string) Env::get('ABACATEPAY_WEBHOOK_SECRET', ''));
    }

    public function getWebhookUrlSecret()
    {
        $current = $this->current ?: $this->buscarAbacatePay();
        $plain = $this->decryptField($current, 'webhook_url_secret_encrypted');
        if ($plain !== null && $plain !== '') {
            return $plain;
        }

        return trim((string) Env::get('ABACATEPAY_WEBHOOK_URL_SECRET', ''));
    }

    public function toSafeArray()
    {
        $current = $this->current ?: $this->buscarAbacatePay();
        $safe = array(
            'gateway' => 'abacatepay',
            'ativo' => 0,
            'ambiente' => 'sandbox',
            'api_key_masked' => '',
            'webhook_hmac_secret_masked' => '',
            'webhook_url_secret_masked' => '',
            'webhook_url_publica' => '',
            'ultimo_teste_status' => null,
            'ultimo_teste_mensagem' => null,
            'ultimo_teste_em' => null,
            'has_api_key' => false,
            'has_webhook_hmac_secret' => false,
            'has_webhook_url_secret' => false,
            'atualizado_por' => null,
            'created_at' => null,
            'updated_at' => null,
        );

        if (!$current) {
            $apiKeyPlain = $this->getApiKey();
            $webhookHmacPlain = $this->getWebhookHmacSecret();
            $webhookUrlPlain = $this->getWebhookUrlSecret();

            $safe['api_key_masked'] = $apiKeyPlain !== '' ? Crypto::mask($apiKeyPlain) : '';
            $safe['webhook_hmac_secret_masked'] = $webhookHmacPlain !== '' ? Crypto::mask($webhookHmacPlain) : '';
            $safe['webhook_url_secret_masked'] = $webhookUrlPlain !== '' ? Crypto::mask($webhookUrlPlain) : '';
            $safe['webhook_url_publica'] = $this->buildWebhookUrl($webhookUrlPlain);
            $safe['has_api_key'] = $apiKeyPlain !== '';
            $safe['has_webhook_hmac_secret'] = $webhookHmacPlain !== '';
            $safe['has_webhook_url_secret'] = $webhookUrlPlain !== '';
            return $safe;
        }

        $apiKeyPlain = $this->getApiKey();
        $webhookHmacPlain = $this->getWebhookHmacSecret();
        $webhookUrlPlain = $this->getWebhookUrlSecret();

        $safe['ativo'] = !empty($current['ativo']) ? 1 : 0;
        $safe['ambiente'] = isset($current['ambiente']) ? (string) $current['ambiente'] : 'sandbox';
        $safe['api_key_masked'] = $apiKeyPlain !== '' ? Crypto::mask($apiKeyPlain) : '';
        $safe['webhook_hmac_secret_masked'] = $webhookHmacPlain !== '' ? Crypto::mask($webhookHmacPlain) : '';
        $safe['webhook_url_secret_masked'] = $webhookUrlPlain !== '' ? Crypto::mask($webhookUrlPlain) : '';
        $safe['webhook_url_publica'] = !empty($current['webhook_url_publica']) ? (string) $current['webhook_url_publica'] : $this->buildWebhookUrl($webhookUrlPlain);
        $safe['ultimo_teste_status'] = isset($current['ultimo_teste_status']) ? $current['ultimo_teste_status'] : null;
        $safe['ultimo_teste_mensagem'] = isset($current['ultimo_teste_mensagem']) ? $current['ultimo_teste_mensagem'] : null;
        $safe['ultimo_teste_em'] = isset($current['ultimo_teste_em']) ? $current['ultimo_teste_em'] : null;
        $safe['has_api_key'] = $apiKeyPlain !== '';
        $safe['has_webhook_hmac_secret'] = $webhookHmacPlain !== '';
        $safe['has_webhook_url_secret'] = $webhookUrlPlain !== '';
        $safe['atualizado_por'] = isset($current['atualizado_por']) ? (int) $current['atualizado_por'] : null;
        $safe['created_at'] = isset($current['created_at']) ? $current['created_at'] : null;
        $safe['updated_at'] = isset($current['updated_at']) ? $current['updated_at'] : null;

        return $safe;
    }

    public function runtimeAbacatePayConfig()
    {
        $defaults = array(
            'source' => 'env',
            'enabled' => Env::get('ABACATEPAY_ENABLED', 'false') === 'true',
            'mode' => Env::get('ABACATEPAY_MODE', 'dev'),
            'environment' => Env::get('ABACATEPAY_ENVIRONMENT', 'sandbox'),
            'api_key' => trim((string) Env::get('ABACATEPAY_API_KEY', '')),
            'webhook_hmac_secret' => trim((string) Env::get('ABACATEPAY_WEBHOOK_SECRET', '')),
            'webhook_url_secret' => trim((string) Env::get('ABACATEPAY_WEBHOOK_URL_SECRET', '')),
            'base_url' => rtrim((string) Env::get('ABACATEPAY_BASE_URL', 'https://api.abacatepay.com/v2'), '/'),
            'webhook_url_publica' => '',
        );

        $current = $this->buscarAbacatePay();
        if (!$current || empty($current['ativo'])) {
            $defaults['webhook_url_publica'] = $this->buildWebhookUrl($defaults['webhook_url_secret']);
            return $defaults;
        }

        $runtime = $defaults;
        $runtime['source'] = 'database';
        $runtime['enabled'] = true;
        $runtime['environment'] = isset($current['ambiente']) && trim((string) $current['ambiente']) !== '' ? (string) $current['ambiente'] : $runtime['environment'];
        $runtime['mode'] = $runtime['environment'];

        $apiKey = $this->getApiKey();
        if ($apiKey !== '') {
            $runtime['api_key'] = $apiKey;
        }

        $webhookHmacSecret = $this->getWebhookHmacSecret();
        if ($webhookHmacSecret !== '') {
            $runtime['webhook_hmac_secret'] = $webhookHmacSecret;
        }

        $webhookUrlSecret = $this->getWebhookUrlSecret();
        if ($webhookUrlSecret !== '') {
            $runtime['webhook_url_secret'] = $webhookUrlSecret;
        }

        $runtime['webhook_url_publica'] = !empty($current['webhook_url_publica'])
            ? (string) $current['webhook_url_publica']
            : $this->buildWebhookUrl($runtime['webhook_url_secret']);

        return $runtime;
    }

    public function buildWebhookUrl($secret)
    {
        $secret = trim((string) $secret);
        if ($secret === '') {
            return '';
        }

        return Helpers::url('webhooks/abacatepay') . '?webhookSecret=' . rawurlencode($secret);
    }

    private function resolveEncryptedValue($input, array $current = null, $field = null, $canEncrypt = true)
    {
        $input = trim((string) $input);
        if ($input !== '') {
            if (!$canEncrypt) {
                if ($current && isset($current[$field])) {
                    return $current[$field];
                }

                return null;
            }

            return Crypto::encrypt($input);
        }

        if ($current && $field && isset($current[$field])) {
            return $current[$field];
        }

        return null;
    }

    private function resolveWebhookUrlPublica($input, array $current = null)
    {
        $input = trim((string) $input);
        if ($input !== '') {
            return $this->buildWebhookUrl($input);
        }

        if ($current && !empty($current['webhook_url_publica'])) {
            return (string) $current['webhook_url_publica'];
        }

        if ($current) {
            $storedSecret = $this->getWebhookUrlSecret();
            return $this->buildWebhookUrl($storedSecret);
        }

        return '';
    }

    private function decryptField(array $current = null, $field = null)
    {
        if (!$current || !$field || !isset($current[$field])) {
            return null;
        }

        $plain = Crypto::decrypt($current[$field]);
        if ($plain !== null && $plain !== '') {
            return $plain;
        }

        return null;
    }

    private function normalizarAmbiente($ambiente)
    {
        $ambiente = strtolower(trim((string) $ambiente));
        $map = array(
            'sandbox' => 'sandbox',
            'teste' => 'sandbox',
            'producao' => 'producao',
            'produção' => 'producao',
            'production' => 'producao',
            'prod' => 'producao',
        );

        return isset($map[$ambiente]) ? $map[$ambiente] : 'sandbox';
    }

    private function normalizarBool(array $dados, $key)
    {
        if (!array_key_exists($key, $dados)) {
            return 0;
        }

        $value = $dados[$key];
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        return in_array($value, array('1', 'true', 'on', 'sim', 'yes'), true) ? 1 : 0;
    }
}

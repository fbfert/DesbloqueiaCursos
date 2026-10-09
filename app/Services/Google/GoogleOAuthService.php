<?php

namespace App\Services\Google;

use App\Core\Helpers;
use App\Core\Logger;

/**
 * Fluxo OpenID Connect do site: Authorization Code + PKCE (S256) + state + nonce.
 *
 * Só identificação (`openid email profile`): nenhum token de acesso do Google é
 * guardado nem usado. Código, tokens e segredo nunca vão para o log.
 */
class GoogleOAuthService
{
    const URL_AUTORIZACAO = 'https://accounts.google.com/o/oauth2/v2/auth';
    const URL_TOKEN = 'https://oauth2.googleapis.com/token';
    const ESCOPOS = 'openid email profile';

    private $http;

    public function __construct(?GoogleHttp $http = null)
    {
        $this->http = $http ?: new GoogleHttp();
    }

    public static function redirectUri()
    {
        return Helpers::url('login/google/callback');
    }

    /** Valor aleatório URL-safe (state, nonce, code_verifier). */
    public static function aleatorio($bytes = 32)
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /** code_challenge S256 do PKCE (RFC 7636 §4.2). */
    public static function codeChallenge($verifier)
    {
        return rtrim(strtr(base64_encode(hash('sha256', (string) $verifier, true)), '+/', '-_'), '=');
    }

    public function urlAutorizacao($state, $nonce, $codeVerifier)
    {
        return self::URL_AUTORIZACAO . '?' . http_build_query(array(
            'client_id' => (string) GoogleConfig::get('client_id', ''),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => self::ESCOPOS,
            'state' => (string) $state,
            'nonce' => (string) $nonce,
            'code_challenge' => self::codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ), '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Troca o código de autorização pelo id_token.
     *
     * @return array ok => true, id_token | ok => false, erro
     */
    public function trocarCodigo($code, $codeVerifier)
    {
        $urlTeste = (string) GoogleConfig::get('token_url_teste', '');
        $r = $this->http->requisitar('POST', $urlTeste !== '' ? $urlTeste : self::URL_TOKEN, array('Content-Type: application/x-www-form-urlencoded'), http_build_query(array(
            'code' => (string) $code,
            'client_id' => (string) GoogleConfig::get('client_id', ''),
            'client_secret' => (string) GoogleConfig::get('client_secret', ''),
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
            'code_verifier' => (string) $codeVerifier,
        ), '', '&'));

        $dados = json_decode($r['corpo'], true);
        if ($r['status'] !== 200 || !is_array($dados) || empty($dados['id_token'])) {
            Logger::warning('Login Google: troca do código falhou.', array(
                'status' => $r['status'],
                'erro_rede' => $r['erro'],
                'erro_google' => is_array($dados) && isset($dados['error']) ? (string) $dados['error'] : null,
            ));
            return array('ok' => false, 'erro' => 'troca_codigo');
        }

        return array('ok' => true, 'id_token' => (string) $dados['id_token']);
    }
}

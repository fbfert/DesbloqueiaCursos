<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuthService;
use App\Services\Google\GoogleConfig;
use App\Services\Google\GoogleIdTokenVerifier;
use App\Services\Google\GoogleLoginService;
use App\Services\Google\GoogleOAuthService;
use App\Support\LoginDestino;
use App\Support\V2ErrorPage;

/**
 * Login com Google no site: GET /login/google → Google → GET /login/google/callback.
 *
 * Proteções: `state` de uso único (consumido antes de qualquer verificação) e válido
 * por 10 minutos, `nonce` conferido no id_token e PKCE. A sessão é aberta pelo mesmo
 * caminho do login por senha (AuthService::abrirSessao). Desligado (404) enquanto
 * não houver GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET.
 */
class GoogleAuthController extends Controller
{
    const CHAVE_SESSAO = 'google_oauth';
    const VALIDADE_STATE = 600;

    const MSG_CANCELADO = 'Login com o Google cancelado.';
    const MSG_EXPIRADO = 'Sua tentativa de login expirou. Tente novamente.';

    public function iniciar(Request $request)
    {
        if (!GoogleConfig::siteAtivo()) {
            return V2ErrorPage::notFound($request->path());
        }

        $verifier = GoogleOAuthService::aleatorio(48);
        $state = GoogleOAuthService::aleatorio();
        $nonce = GoogleOAuthService::aleatorio();

        Session::put(self::CHAVE_SESSAO, array(
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'origem' => trim((string) $request->query('origem', '')),
            'redirect' => (string) $request->query('redirect', ''),
            'criado_em' => time(),
        ));

        return $this->redirect((new GoogleOAuthService())->urlAutorizacao($state, $nonce, $verifier));
    }

    public function callback(Request $request)
    {
        if (!GoogleConfig::siteAtivo()) {
            return V2ErrorPage::notFound($request->path());
        }

        // Uso único: o state sai da sessão antes de qualquer verificação.
        $tentativa = Session::get(self::CHAVE_SESSAO);
        Session::forget(self::CHAVE_SESSAO);
        $tentativa = is_array($tentativa) ? $tentativa : array();
        // Sem tentativa na sessão (expirada ou repetida), volta ao login V2, de onde
        // o botão "Entrar com Google" sai.
        $origem = isset($tentativa['origem']) ? $tentativa['origem'] : 'v2';
        $redirect = isset($tentativa['redirect']) ? $tentativa['redirect'] : '';

        $erroGoogle = trim((string) $request->query('error', ''));
        if ($erroGoogle !== '') {
            return $this->recusar($erroGoogle === 'access_denied' ? self::MSG_CANCELADO : GoogleLoginService::MSG_FALHA, $origem, $redirect);
        }

        $state = (string) $request->query('state', '');
        if (empty($tentativa['state']) || $state === '' || !hash_equals((string) $tentativa['state'], $state)
            || (time() - (int) ($tentativa['criado_em'] ?? 0)) > self::VALIDADE_STATE) {
            return $this->recusar(self::MSG_EXPIRADO, $origem, $redirect);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return $this->recusar(GoogleLoginService::MSG_FALHA, $origem, $redirect);
        }

        $troca = (new GoogleOAuthService())->trocarCodigo($code, $tentativa['verifier']);
        if (empty($troca['ok'])) {
            return $this->recusar(GoogleLoginService::MSG_FALHA, $origem, $redirect);
        }

        $verificacao = (new GoogleIdTokenVerifier())->verificar(
            $troca['id_token'],
            array((string) GoogleConfig::get('client_id', '')),
            $tentativa['nonce']
        );
        if (empty($verificacao['ok'])) {
            \App\Core\Logger::warning('Login Google: id_token recusado no site.', array('motivo' => $verificacao['motivo'] ?? null));
            return $this->recusar(GoogleLoginService::MSG_FALHA, $origem, $redirect);
        }

        $resultado = (new GoogleLoginService())->resolverUsuario($verificacao['claims'], 'site', $request->ip(), $request->userAgent());
        if (empty($resultado['ok'])) {
            return $this->recusar($resultado['message'], $origem, $redirect);
        }

        $auth = new AuthService();
        $auth->abrirSessao($resultado['usuario']);
        Session::flash('success', 'Login realizado com sucesso.');

        $destino = LoginDestino::sucesso($origem, $redirect);
        if ($destino === null) {
            $destino = $auth->resolveLoginRedirect((int) $resultado['usuario']['id']);
            if ($destino === '/') {
                Session::flash('post_login_choice_modal', array('enabled' => true));
            }
        }

        // Conta recém-criada: oferece completar o cadastro (CPF) antes de seguir.
        if (!empty($resultado['novo'])) {
            Session::put('completar_cadastro_destino', $destino);
            return $this->redirect('/conta/completar');
        }

        return $this->redirect($destino);
    }

    private function recusar($mensagem, $origem, $redirect)
    {
        Session::flash('errors', array('login' => $mensagem));

        return $this->redirect(LoginDestino::erro($origem, $redirect));
    }
}

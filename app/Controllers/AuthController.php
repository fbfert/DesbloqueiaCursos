<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuthService;
use App\Support\SafeRedirect;

class AuthController extends Controller
{
    private $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function showRegister(Request $request)
    {
        return $this->view('auth/register', $this->flashData(array(
            'title' => 'Cadastro',
        )));
    }

    public function register(Request $request)
    {
        $result = $this->authService->register($request->all(), $request->ip(), $request->userAgent());

        if (!$result['ok']) {
            Session::flash('errors', $result['errors']);
            Session::flash('old', $request->all());
            return $this->redirect($this->resolveOrigemRedirect($request, '/v2/cadastro', '/cadastro'));
        }

        Session::flash('account_created', array(
            'nome' => (string) $request->input('nome'),
            'email' => (string) $request->input('email'),
        ));

        Session::flash('success', 'Conta criada com sucesso. Agora você já pode acessar sua conta.');
        return $this->redirect($this->resolveOrigemRedirect($request, '/v2/login', '/login'));
    }

    public function showLogin(Request $request)
    {
        $data = $this->flashData(array(
            'title' => 'Login',
        ));

        $data['accountCreated'] = Session::pullFlash('account_created');

        return $this->view('auth/login', $data);
    }

    public function login(Request $request)
    {
        $result = $this->authService->login(
            $request->input('login'),
            $request->input('senha'),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flash('errors', array('login' => $result['message']));
            Session::flash('old', array('login' => $request->input('login')));
            return $this->redirect($this->resolveLoginErrorRedirect($request));
        }

        Session::flash('success', 'Login realizado com sucesso.');

        $retornoV2 = $this->resolveLoginSuccessRedirect($request);
        if ($retornoV2 !== null) {
            return $this->redirect($retornoV2);
        }

        if (isset($result['redirect_to']) && $result['redirect_to'] === '/') {
            Session::flash('post_login_choice_modal', array('enabled' => true));
        }

        return $this->redirect(isset($result['redirect_to']) ? $result['redirect_to'] : '/');
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->ip(), $request->userAgent());
        Session::start();
        Session::flash('success', 'Você saiu da sessao.');

        return $this->redirect('/login');
    }

    public function logoutConfirm(Request $request)
    {
        return $this->view('auth/logout', $this->flashData(array(
            'title' => 'Sair da conta',
            'cancelUrl' => $this->resolveCancelUrl(),
        )));
    }

    public function showForgotPassword(Request $request)
    {
        return $this->view('auth/forgot-password', $this->flashData(array(
            'title' => 'Recuperar senha',
        )));
    }

    public function minhaPagina(Request $request)
    {
        $sessionPerfis = Session::get('usuario_perfis', array());
        $hasAdminAccess = Session::get('usuario_admin') || Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
        $hasProfessorAccess = Session::get('usuario_professor') || Session::get('is_professor') || in_array('professor', $sessionPerfis, true);

        if ($hasAdminAccess) {
            return $this->redirect('/admin/dashboard');
        }
        if ($hasProfessorAccess) {
            return $this->redirect('/professor/dashboard');
        }

        return $this->redirect('/meus-cursos');
    }

    public function showAccount(Request $request)
    {
        $usuarioId = Session::get('usuario_id');
        $conta = $this->authService->accountData($usuarioId);

        if (!$conta) {
            Session::flash('errors', array('Conta não encontrada.'));
            return $this->redirect('/login');
        }

        return $this->view('auth/account', $this->flashData(array(
            'title' => 'Minha conta',
            'conta' => $conta,
        )));
    }

    public function updateAccount(Request $request)
    {
        $usuarioId = Session::get('usuario_id');
        $result = $this->authService->updateAccount($usuarioId, $request->all(), $request->ip(), $request->userAgent());

        if (!$result['ok']) {
            Session::flash('errors', $result['errors']);
            Session::flash('old', $request->all());
            return $this->redirect('/minha-conta');
        }

        Session::flash('success', 'Dados atualizados com sucesso.');
        return $this->redirect('/minha-conta');
    }

    public function requestPasswordReset(Request $request)
    {
        $result = $this->authService->requestPasswordReset(
            $request->input('login'),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flash('errors', $result['errors']);
            Session::flash('old', array('login' => $request->input('login')));
            return $this->redirect($this->resolveOrigemRedirect($request, '/v2/recuperar-senha', '/recuperar-senha'));
        }

        Session::flash('success', 'Se os dados existirem, enviamos um link de recuperação para o e-mail cadastrado.');
        return $this->redirect($this->resolveOrigemRedirect($request, '/v2/recuperar-senha', '/recuperar-senha'));
    }

    public function showResetPassword(Request $request)
    {
        return $this->view('auth/reset-password', $this->flashData(array(
            'title' => 'Redefinir senha',
            'token' => $request->query('token'),
        )));
    }

    public function resetPassword(Request $request)
    {
        $result = $this->authService->resetPassword(
            $request->input('token'),
            $request->input('senha'),
            $request->input('senha_confirmacao'),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flash('errors', $result['errors']);
            Session::flash('old', array('token' => $request->input('token')));
            return $this->redirect('/recuperar-senha/redefinir?token=' . urlencode((string) $request->input('token')));
        }

        Session::flash('success', 'Senha redefinida com sucesso.');
        return $this->redirect('/login');
    }

    /**
     * Define para onde voltar quando o login falha.
     *
     * Segurança: NÃO aceita URL vinda do usuário. A origem é validada contra uma
     * lista branca interna que mapeia um token conhecido para um caminho interno
     * fixo. Sem o token (login original), o comportamento permanece idêntico ao
     * anterior: retorno para '/login'.
     */
    private function resolveLoginErrorRedirect(Request $request)
    {
        $origem = trim((string) $request->input('origem', ''));

        // Retorno V2 seguro (Fase 2.13): preserva ?redirect= (validado como
        // caminho interno /v2/...) para que o reenvio do login mantenha o destino.
        $retorno = SafeRedirect::v2Path($request->input('redirect', ''));
        if ($retorno !== null && ($origem === 'v2' || $origem === 'v2_aluno')) {
            return '/v2/login?redirect=' . rawurlencode($retorno);
        }

        $permitidos = array(
            'v2' => '/v2/login',
            // Veio da Área do Aluno V2: preserva a intenção para o retorno pós-login.
            'v2_aluno' => '/v2/login?origem=v2_aluno',
        );

        return isset($permitidos[$origem]) ? $permitidos[$origem] : '/login';
    }

    /**
     * Destino seguro APÓS login bem-sucedido, por origem em lista branca.
     *
     * Segurança: NÃO aceita URL do usuário. Apenas a flag `origem` é lida e
     * comparada a tokens fixos. Retorna null quando não há origem V2 aplicável,
     * preservando integralmente o destino padrão do sistema (legado).
     *
     * Prioridade do pós-login V2:
     *  1) `?redirect=` válido e interno (/v2/...) → destino solicitado (sem tela);
     *  2) `origem=v2_aluno` (veio da Área do Aluno V2) → `/v2/aluno`;
     *  3) `origem=v2` (login iniciado direto em /v2/login) sem redirect válido →
     *     tela V2 de escolha `/v2/pos-login` (nunca `/` nem o modal legado do V1);
     *  4) demais origens → null (comportamento legado inalterado).
     */
    private function resolveLoginSuccessRedirect(Request $request)
    {
        $origem = trim((string) $request->input('origem', ''));

        if ($origem !== 'v2' && $origem !== 'v2_aluno') {
            return null;
        }

        // (1) Destino V2 original preservado em ?redirect=, aceito SOMENTE quando
        // validado como caminho interno /v2/... . Nunca aceita URL externa/aberta.
        $retorno = SafeRedirect::v2Path($request->input('redirect', ''));
        if ($retorno !== null) {
            return $retorno;
        }

        // (2) Fluxo da Área do Aluno V2 já tem destino próprio.
        if ($origem === 'v2_aluno') {
            return '/v2/aluno';
        }

        // (3) Login direto no V2 sem destino: tela V2 de escolha (Minha Área /
        // Catálogo), mantendo o usuário integralmente no ambiente V2.
        return '/v2/pos-login';
    }

    /**
     * Retorno seguro por origem (lista branca por fluxo, reutilizada por
     * Cadastro V2 e Recuperação de Senha V2).
     *
     * Segurança: NÃO aceita URL vinda do usuário. A única entrada do usuário é a
     * flag `origem`, comparada estritamente a 'v2'. Tanto o alvo V2 quanto o alvo
     * padrão são caminhos internos FIXOS, definidos pelo chamador. Qualquer
     * origem ausente, externa ou diferente de 'v2' usa o destino padrão original,
     * mantendo o comportamento das páginas originais idêntico.
     */
    private function resolveOrigemRedirect(Request $request, $alvoV2, $alvoPadrao)
    {
        $origem = trim((string) $request->input('origem', ''));

        if ($origem !== 'v2') {
            return $alvoPadrao;
        }

        // Fase 2.13: propaga o retorno V2 seguro (?redirect=) através do fluxo de
        // cadastro/recuperação, para que após o login o usuário volte ao destino
        // original. Só quando o alvo é V2 e o retorno é interno /v2/... válido.
        $retorno = SafeRedirect::v2Path($request->input('redirect', ''));
        if ($retorno !== null && strpos($alvoV2, '/v2/') === 0 && strpos($alvoV2, '?') === false) {
            return $alvoV2 . '?redirect=' . rawurlencode($retorno);
        }

        return $alvoV2;
    }

    private function flashData(array $data)
    {
        return array_merge($data, array(
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
            'old' => Session::pullFlash('old', array()),
        ));
    }

    private function resolveCancelUrl()
    {
        $sessionPerfis = Session::get('usuario_perfis', array());
        $hasAdminAccess = Session::get('usuario_admin') || Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
        $hasProfessorAccess = Session::get('usuario_professor') || Session::get('is_professor') || in_array('professor', $sessionPerfis, true);

        if ($hasAdminAccess) {
            return '/admin';
        }

        if ($hasProfessorAccess) {
            return '/professor/dashboard';
        }

        return '/aluno/meus-cursos';
    }
}


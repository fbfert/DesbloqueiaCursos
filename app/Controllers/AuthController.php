<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuthService;

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
            return $this->redirect('/cadastro');
        }

        Session::flash('account_created', array(
            'nome' => (string) $request->input('nome'),
            'email' => (string) $request->input('email'),
        ));

        Session::flash('success', 'Conta criada com sucesso. Agora você já pode acessar sua conta.');
        return $this->redirect('/login');
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
            return $this->redirect('/login');
        }

        Session::flash('success', 'Login realizado com sucesso.');
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
            return $this->redirect('/recuperar-senha');
        }

        Session::flash('success', 'Se os dados existirem, enviamos um link de recuperação para o e-mail cadastrado.');
        return $this->redirect('/recuperar-senha');
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


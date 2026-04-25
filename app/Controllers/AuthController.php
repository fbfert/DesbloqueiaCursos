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

        Session::flash('success', 'Cadastro realizado com sucesso. Você ja pode acessar sua conta.');
        return $this->redirect('/login');
    }

    public function showLogin(Request $request)
    {
        return $this->view('auth/login', $this->flashData(array(
            'title' => 'Login',
        )));
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
        return $this->redirect(isset($result['redirect_to']) ? $result['redirect_to'] : '/meus-cursos');
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->ip(), $request->userAgent());
        Session::start();
        Session::flash('success', 'Você saiu da sessao.');

        return $this->redirect('/login');
    }

    public function showForgotPassword(Request $request)
    {
        return $this->view('auth/forgot-password', $this->flashData(array(
            'title' => 'Recuperar senha',
        )));
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

        Session::flash('success', 'Se os dados existirem, um token de recuperacao foi gerado.');

        if (!empty($result['token'])) {
            Session::flash('reset_token', $result['token']);
            return $this->redirect('/recuperar-senha/redefinir');
        }

        return $this->redirect('/recuperar-senha');
    }

    public function showResetPassword(Request $request)
    {
        return $this->view('auth/reset-password', $this->flashData(array(
            'title' => 'Redefinir senha',
            'token' => Session::pullFlash('reset_token', $request->query('token')),
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
            Session::flash('reset_token', $request->input('token'));
            return $this->redirect('/recuperar-senha/redefinir');
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
}


<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * Login V2 (Fase 2.4) — apenas renderização visual.
 *
 * Esta classe NÃO autentica, não verifica senha, não cria sessão e não acessa
 * o banco. Ela apenas renderiza a tela V2 e lê as flash messages reais já
 * produzidas pelo fluxo de autenticação existente (`AuthController@login` →
 * `AuthService`). O formulário V2 envia POST para o mesmo endpoint real
 * `/login`, com os mesmos campos (`login`, `senha`) e CSRF do sistema atual.
 *
 * O retorno seguro à tela V2 em caso de erro é controlado pelo servidor por
 * meio de uma flag interna em lista branca (`origem=v2` → `/v2/login`), tratada
 * em `AuthController`. Nenhum redirect controlado pelo usuário é aceito.
 */
class LoginController extends Controller
{
    public function show(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);

        // Origem interna validada por lista branca (NÃO é URL do usuário): define
        // apenas o token enviado no campo oculto, controlando o retorno seguro
        // pós-login. Valor inválido cai no padrão 'v2'.
        $origemPermitidas = array('v2', 'v2_aluno');
        $origemFlag = (string) $request->query('origem', 'v2');
        if (!in_array($origemFlag, $origemPermitidas, true)) {
            $origemFlag = 'v2';
        }

        $data = array(
            'title' => 'Acesse sua conta — Desbloqueia Cursos',
            'pageTitle' => 'Acesse sua conta — Desbloqueia Cursos',
            'pageDescription' => 'Entre na sua conta da Desbloqueia Cursos para acessar seus cursos, inscrições e certificados.',

            // Flash messages reais do fluxo de autenticação existente.
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
            'old' => Session::pullFlash('old', array()),
            'accountCreated' => Session::pullFlash('account_created'),

            // Endpoint e navegação reais (não inventar rotas).
            'loginAction' => '/login',
            'origemFlag' => $origemFlag,
            'homeHref' => '/v2/',
            'registerHref' => '/v2/cadastro',
            'forgotHref' => '/v2/recuperar-senha',

            // Já autenticado: oferece ir para a área atual (regra atual do sistema).
            'jaLogado' => $usuarioId > 0,
            'areaHref' => $this->resolveAreaHref(),
        );

        return new Response(View::render('v2/login', $data, false));
    }

    private function resolveAreaHref()
    {
        $sessionPerfis = Session::get('usuario_perfis', array());
        $hasAdminAccess = (bool) Session::get('usuario_admin') || (bool) Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
        $hasProfessorAccess = (bool) Session::get('usuario_professor') || (bool) Session::get('is_professor') || in_array('professor', $sessionPerfis, true);

        if ($hasAdminAccess) {
            return '/admin';
        }
        if ($hasProfessorAccess) {
            return '/professor/dashboard';
        }

        return '/meus-cursos';
    }
}

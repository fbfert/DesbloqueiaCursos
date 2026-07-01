<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * Recuperação de Senha V2 (Fase 2.5) — apenas renderização visual.
 *
 * Não gera token, não envia e-mail, não acessa o banco e não revela se o
 * usuário existe. Apenas renderiza a tela V2 e lê as flash messages reais
 * (`errors`, `success`, `old`) já produzidas pelo fluxo existente
 * (`AuthController@requestPasswordReset` → `AuthService::requestPasswordReset`).
 * O formulário V2 envia POST para o mesmo endpoint real `/recuperar-senha`, com
 * o mesmo campo (`login`) e CSRF do sistema atual.
 *
 * A resposta do sistema é deliberadamente neutra (não confirma a existência da
 * conta). O retorno à tela V2 usa a flag interna em lista branca (`origem=v2`).
 */
class RecuperarSenhaController extends Controller
{
    public function show(Request $request)
    {
        $data = array(
            'title' => 'Recuperar senha — Desbloqueia Cursos',
            'pageTitle' => 'Recuperar senha — Desbloqueia Cursos',
            'pageDescription' => 'Recupere o acesso à sua conta da Desbloqueia Cursos.',

            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
            'old' => Session::pullFlash('old', array()),

            // Endpoint e navegação reais (não inventar rotas).
            'recuperarAction' => '/recuperar-senha',
            'homeHref' => '/v2/',
            'loginHref' => '/v2/login',
        );

        return new Response(View::render('v2/recuperar-senha', $data, false));
    }
}

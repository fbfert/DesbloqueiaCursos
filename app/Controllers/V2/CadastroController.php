<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Support\SafeRedirect;

/**
 * Cadastro V2 (Fase 2.5) — apenas renderização visual.
 *
 * Não cria conta, não valida dados, não acessa o banco e não envia e-mail.
 * Apenas renderiza a tela V2 e lê as flash messages reais (`errors`, `success`,
 * `old`) já produzidas pelo fluxo de cadastro existente (`AuthController@register`
 * → `AuthService::register`). O formulário V2 envia POST para o mesmo endpoint
 * real `/cadastro`, com os mesmos campos e CSRF do sistema atual.
 *
 * O retorno seguro à tela V2 em caso de erro usa a flag interna em lista branca
 * (`origem=v2`), tratada no `AuthController`. A senha e a confirmação NUNCA são
 * repopuladas.
 */
class CadastroController extends Controller
{
    public function show(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);

        // Retorno V2 seguro (Fase 2.13) propagado do login para manter o destino
        // após cadastro → login. Validado como caminho interno /v2/... .
        $redirectSeguro = SafeRedirect::v2Path($request->query('redirect', ''));
        $redirectQuery = $redirectSeguro !== null ? '?redirect=' . rawurlencode($redirectSeguro) : '';

        $data = array(
            'title' => 'Criar conta — Desbloqueia Cursos',
            'pageTitle' => 'Criar conta — Desbloqueia Cursos',
            'pageDescription' => 'Crie sua conta na Desbloqueia Cursos para acessar cursos, acompanhar inscrições e certificados.',

            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
            'old' => Session::pullFlash('old', array()),

            // Endpoint e navegação reais (não inventar rotas).
            'cadastroAction' => '/cadastro',
            'homeHref' => '/v2/',
            'loginHref' => '/v2/login' . $redirectQuery,
            'redirectSeguro' => $redirectSeguro !== null ? $redirectSeguro : '',
            'termosHref' => '/termos-de-uso',
            'privacidadeHref' => '/politica-de-privacidade',

            'jaLogado' => $usuarioId > 0,
            'areaHref' => $this->resolveAreaHref(),
        );

        return new Response(View::render('v2/cadastro', $data, false));
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

        // Fase 2.13: aluno permanece no ambiente V2.
        return '/v2/aluno/';
    }
}

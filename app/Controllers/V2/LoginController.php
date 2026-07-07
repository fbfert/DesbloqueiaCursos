<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Support\SafeRedirect;

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

        // Retorno seguro (Fase 2.13): destino V2 original preservado em ?redirect=.
        // Validado como caminho interno /v2/... (SafeRedirect); nunca URL do usuário.
        $redirectSeguro = SafeRedirect::v2Path($request->query('redirect', ''));

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
            // Retorno V2 seguro para o campo oculto e para propagar aos links de
            // cadastro/recuperação (string vazia quando ausente/invalido).
            'redirectSeguro' => $redirectSeguro !== null ? $redirectSeguro : '',
            'homeHref' => '/v2/',
            'registerHref' => '/v2/cadastro' . ($redirectSeguro !== null ? '?redirect=' . rawurlencode($redirectSeguro) : ''),
            'forgotHref' => '/v2/recuperar-senha' . ($redirectSeguro !== null ? '?redirect=' . rawurlencode($redirectSeguro) : ''),

            // Já autenticado: oferece ir para a área atual (regra atual do sistema).
            'jaLogado' => $usuarioId > 0,
            'areaHref' => $this->resolveAreaHref(),
        );

        return new Response(View::render('v2/login', $data, false));
    }

    /**
     * Tela V2 de escolha pós-login (Minha Área × Catálogo). Só é alcançada após
     * login iniciado direto em /v2/login sem `redirect` válido (ver
     * AuthController::resolveLoginSuccessRedirect). Rota protegida por `auth.v2`;
     * somente leitura/apresentação, sem regra de negócio, sem modal/legado V1.
     */
    public function posLogin(Request $request)
    {
        $usuarioNome = trim((string) Session::get('usuario_nome', ''));
        $primeiro = '';
        if ($usuarioNome !== '') {
            $partes = preg_split('/\s+/', $usuarioNome);
            $primeiro = ($partes && !empty($partes[0])) ? (string) $partes[0] : $usuarioNome;
        }

        $data = array(
            'title' => 'Para onde você deseja ir? — Desbloqueia Cursos',
            'pageTitle' => 'Para onde você deseja ir? — Desbloqueia Cursos',
            'pageDescription' => 'Escolha entre a sua área do aluno e o catálogo de cursos.',
            'loggedIn' => true,
            'usuarioPrimeiroNome' => $primeiro,
            'areaHref' => \App\Support\V2Nav::ALUNO,
            'alunoHref' => \App\Support\V2Nav::ALUNO,
            'catalogoHref' => \App\Support\V2Nav::CATALOGO,
        );

        return new Response(View::render('v2/pos-login', $data, false));
    }

    /**
     * Logout V2 — encerra a sessão do aluno via o mesmo mecanismo do sistema
     * (AuthService::logout → Session::destroy) e redireciona SEMPRE para
     * /v2/login, mantendo o fluxo dentro do namespace visual V2. Acessível
     * apenas por POST com CSRF e sob auth.v2 (ver routes/web.php). Não altera a
     * autenticação de admin/professor, checkout, gateway, PIX ou webhooks.
     */
    public function logout(Request $request)
    {
        (new \App\Services\AuthService())->logout($request->ip(), $request->userAgent());

        // A sessão foi destruída; reabre para transportar a mensagem de saída
        // até a tela V2 de login (que lê Session::pullFlash('success')).
        Session::start();
        Session::flash('success', 'Você saiu da sua conta.');

        return $this->redirect('/v2/login');
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

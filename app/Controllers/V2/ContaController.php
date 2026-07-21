<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;

/**
 * Editar cadastro V2 (/v2/minha-conta) — casca visual V2 sobre o MESMO
 * fluxo real já existente em `AuthController::showAccount/updateAccount`.
 *
 * Nenhuma regra nova: toda validação/persistência continua em
 * `AuthService::updateAccount()` (mesma usada pelo `/minha-conta` legado).
 * Este controller só lê a sessão, delega e apresenta no design system V2.
 */
class ContaController extends Controller
{
    /** @var AuthService */
    private $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function editar(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId <= 0) {
            return Response::redirect('/v2/login?origem=v2_aluno');
        }

        $conta = $this->authService->accountData($usuarioId);
        if (!$conta) {
            Session::flash('errors', array('conta' => 'Conta não encontrada.'));
            return Response::redirect('/v2/aluno/');
        }

        $usuarioNome = trim((string) Session::get('usuario_nome', ''));

        $data = array_merge($this->dadosLayout($usuarioNome), array(
            'title' => 'Editar cadastro — Desbloqueia Cursos',
            'pageTitle' => 'Editar cadastro — Desbloqueia Cursos',
            'pageDescription' => 'Atualize seus dados cadastrais.',
            'conta' => $conta,
            'old' => Session::pullFlash('old', array()),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));

        return new Response(View::render('v2/conta', $data, false));
    }

    public function atualizar(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId <= 0) {
            return Response::redirect('/v2/login?origem=v2_aluno');
        }

        $resultado = $this->authService->updateAccount($usuarioId, $request->all(), $request->ip(), $request->userAgent());

        if (empty($resultado['ok'])) {
            Session::flash('errors', $resultado['errors']);
            Session::flash('old', $request->all());
            return Response::redirect('/v2/minha-conta');
        }

        Session::flash('success', 'Dados atualizados com sucesso.');
        return Response::redirect('/v2/minha-conta');
    }

    private function primeiroNome($nome)
    {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return '';
        }
        $partes = preg_split('/\s+/', $nome);
        return ($partes && !empty($partes[0])) ? (string) $partes[0] : $nome;
    }

    private function dadosLayout($usuarioNome)
    {
        $sessionPerfis = Session::get('usuario_perfis', array());
        $hasAdminAccess = (bool) Session::get('usuario_admin') || (bool) Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
        $hasProfessorAccess = (bool) Session::get('usuario_professor') || (bool) Session::get('is_professor') || in_array('professor', $sessionPerfis, true);

        $areaHref = '/v2/aluno';
        if ($hasAdminAccess) {
            $areaHref = '/admin';
        } elseif ($hasProfessorAccess) {
            $areaHref = '/professor/dashboard';
        }

        return array(
            'loggedIn' => true,
            'usuarioNome' => $usuarioNome,
            'usuarioPrimeiroNome' => $this->primeiroNome($usuarioNome),
            'areaHref' => $areaHref,
            'loginHref' => '/v2/login',
            'registerHref' => '/v2/cadastro',
            'catalogoHref' => '/v2/catalogo/',
            'categoriasHref' => '/v2/categorias/',
            'certificadosHref' => '/v2/certificados/validar/',
            'sobreHref' => '/sobre',
            'contatoHref' => '/contato',
            'homeHref' => '/v2/',
        );
    }
}

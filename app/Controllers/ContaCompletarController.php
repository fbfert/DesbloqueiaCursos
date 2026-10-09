<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Usuario;
use App\Services\ContaCpfService;
use App\Support\TemaPublico;

/**
 * "Complete seu cadastro" (login-google): logo após a conta ser criada pelo Google,
 * pede o CPF, com a opção "Fazer isso depois", que segue ao destino pós-login sem
 * bloquear o acesso. O CPF passa pelo ContaCpfService.
 */
class ContaCompletarController extends Controller
{
    const CHAVE_DESTINO = 'completar_cadastro_destino';

    public function show(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId <= 0) {
            return Response::redirect('/v2/login');
        }

        $usuario = (new Usuario())->findById($usuarioId);
        if (!$usuario || !Usuario::semCpf($usuario)) {
            return Response::redirect($this->destino(true));
        }

        $data = array(
            'title' => 'Complete seu cadastro — Desbloqueia Cursos',
            'pageTitle' => 'Complete seu cadastro — Desbloqueia Cursos',
            'pageDescription' => 'Informe seu CPF para receber seus certificados.',
            'homeHref' => '/v2/',
            'usuarioNome' => (string) ($usuario['nome'] ?? ''),
            'errors' => Session::pullFlash('errors', array()),
            'old' => Session::pullFlash('old', array()),
            'success' => Session::pullFlash('success'),
        );

        return new Response(View::render(TemaPublico::view('completar-cadastro'), $data, false));
    }

    public function salvar(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId <= 0) {
            return Response::redirect('/v2/login');
        }

        if ((string) $request->input('acao', '') === 'depois') {
            return Response::redirect($this->destino(true));
        }

        $resultado = (new ContaCpfService())->informar($usuarioId, $request->input('cpf', ''), 'site', $request->ip(), $request->userAgent());
        if (empty($resultado['ok'])) {
            if ($resultado['erro'] === 'cpf_ja_informado') {
                return Response::redirect($this->destino(true));
            }
            Session::flash('errors', array('cpf' => $resultado['message']));
            Session::flash('old', array('cpf' => (string) $request->input('cpf', '')));
            return Response::redirect('/conta/completar');
        }

        Session::flash('success', 'CPF salvo. Seus certificados poderão ser emitidos normalmente.');
        return Response::redirect($this->destino(true));
    }

    /** Destino guardado no login com Google; só caminho interno. */
    private function destino($consumir)
    {
        $destino = (string) Session::get(self::CHAVE_DESTINO, '');
        if ($consumir) {
            Session::forget(self::CHAVE_DESTINO);
        }
        if ($destino === '' || $destino[0] !== '/' || strpos($destino, '//') === 0 || strpos($destino, '\\') !== false) {
            return '/v2/aluno/';
        }

        return $destino;
    }
}

<?php

namespace App\Controllers\Api\App;

use App\Core\Request;
use App\Models\Usuario;
use App\Services\AppTokenService;
use App\Services\AuthService;
use App\Support\AppApi\UsuarioPresenter;
use App\Support\AppAuth;

/**
 * Perfil do aluno: `GET /me`, `POST /me` (telefone, cidade, estado) e
 * `POST /me/senha` (revoga os tokens dos outros aparelhos).
 */
class MeController extends AppController
{
    public function show(Request $request)
    {
        $usuario = (new Usuario())->findById($this->usuarioId());
        if (!$usuario) {
            return $this->erro('nao_autenticado', null, 401);
        }

        return $this->ok(UsuarioPresenter::usuario($usuario));
    }

    public function atualizar(Request $request)
    {
        $auth = new AuthService();
        $atual = $auth->accountData($this->usuarioId());
        if (!$atual) {
            return $this->erro('nao_autenticado', null, 401);
        }

        // Só contato e endereço mudam pelo app; nome, e-mail e CPF seguem os atuais
        // e passam pela mesma validação do site (AuthService::updateAccount).
        $entrada = $atual;
        foreach (array('telefone' => 30, 'cidade' => 120, 'estado' => 10) as $campo => $limite) {
            if ($request->input($campo, null) !== null) {
                $entrada[$campo] = $this->texto($request, $campo, $limite);
            }
        }

        $resultado = $auth->updateAccount($this->usuarioId(), $entrada, $request->ip(), $request->userAgent());
        if (empty($resultado['ok'])) {
            return $this->erro('validacao', 'Verifique os dados informados.', 422, (array) ($resultado['errors'] ?? array()));
        }

        return $this->ok(UsuarioPresenter::usuario((new Usuario())->findById($this->usuarioId())));
    }

    public function senha(Request $request)
    {
        $resultado = (new AuthService())->alterarSenha(
            $this->usuarioId(),
            is_scalar($request->input('senha_atual', '')) ? (string) $request->input('senha_atual', '') : '',
            is_scalar($request->input('senha_nova', '')) ? (string) $request->input('senha_nova', '') : '',
            is_scalar($request->input('senha_nova_confirmacao', '')) ? (string) $request->input('senha_nova_confirmacao', '') : '',
            $request->ip(),
            $request->userAgent()
        );
        if (empty($resultado['ok'])) {
            return $this->erro('validacao', 'Verifique os dados informados.', 422, (array) ($resultado['errors'] ?? array()));
        }

        (new AppTokenService())->revogarOutrosDispositivos($this->usuarioId(), (string) AppAuth::deviceId(), 'troca_senha');

        return $this->ok(array('ok' => true));
    }
}

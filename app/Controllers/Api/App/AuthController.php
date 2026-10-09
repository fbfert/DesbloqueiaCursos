<?php

namespace App\Controllers\Api\App;

use App\Core\Logger;
use App\Core\Request;
use App\Models\AppDispositivo;
use App\Models\Usuario;
use App\Services\AppLimiteTaxaService;
use App\Services\AppTokenService;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\Google\GoogleConfig;
use App\Services\Google\GoogleIdTokenVerifier;
use App\Services\Google\GoogleLoginService;
use App\Support\AppApi\UsuarioPresenter;
use App\Support\AppAuth;

/**
 * Autenticação do app: login, refresh rotativo, logout, cadastro e recuperação
 * de senha. Mesma política do site (AuthService); tokens em AppTokenService.
 */
class AuthController extends AppController
{
    public function login(Request $request)
    {
        $login = $this->texto($request, 'login', 191);
        $senha = $request->input('senha', '');
        $senha = is_scalar($senha) ? (string) $senha : '';
        $deviceId = $this->texto($request, 'device_id', 100);
        $deviceName = $this->texto($request, 'device_name', 150);

        $campos = array();
        if ($login === '') {
            $campos['login'] = 'Informe o e-mail ou CPF.';
        }
        if ($senha === '') {
            $campos['senha'] = 'Informe a senha.';
        }
        if (!self::deviceIdValido($deviceId)) {
            $campos['device_id'] = 'Identificador do dispositivo inválido.';
        }
        if ($campos) {
            return $this->erro('validacao', 'Verifique os dados informados.', 422, $campos);
        }

        $limite = $this->limitar($request, array('login_ip' => $this->ipReal($request), 'login_conta' => $login));
        if ($limite !== null) {
            return $limite;
        }

        $resultado = (new AuthService())->autenticarCredenciais($login, $senha, $request->ip(), $request->userAgent(), 'app_login');
        if (empty($resultado['ok'])) {
            $motivo = isset($resultado['motivo']) ? $resultado['motivo'] : 'credenciais_invalidas';
            if ($motivo === 'conta_bloqueada') {
                $minutos = max(1, (int) ($resultado['minutos_restantes'] ?? 1));
                return $this->erro('conta_bloqueada', 'Acesso temporariamente bloqueado. Tente novamente em ' . $minutos . ($minutos === 1 ? ' minuto.' : ' minutos.'), 423);
            }
            if ($motivo === 'conta_inativa') {
                return $this->erro('conta_inativa', 'Usuário sem permissão de acesso.', 403);
            }
            return $this->erro('credenciais_invalidas', 'E-mail, CPF ou senha incorretos.', 401);
        }

        $usuario = $resultado['usuario'];
        $par = (new AppTokenService())->emitirParaLogin((int) $usuario['id'], $deviceId, $deviceName !== '' ? $deviceName : null, $request->ip(), $request->userAgent());

        $this->auditar('app.login', (int) $usuario['id'], $request, array('device_id' => $deviceId, 'device_name' => $deviceName));

        $dados = UsuarioPresenter::tokens($par);
        $dados['usuario'] = UsuarioPresenter::usuario($usuario);

        return $this->ok($dados);
    }

    /**
     * Login com Google (login-google): o app envia o id_token do SDK nativo do
     * Google; a conta é resolvida pelas mesmas regras do site e a resposta tem o
     * formato do /auth/login, mais `usuario_novo`. 404 enquanto GOOGLE_APP_CLIENT_IDS
     * não estiver configurado.
     */
    public function google(Request $request)
    {
        if (!GoogleConfig::appAtivo()) {
            return $this->erro('nao_encontrado', 'Recurso não encontrado.', 404);
        }

        $idToken = $request->input('id_token', '');
        $idToken = is_scalar($idToken) ? trim((string) $idToken) : '';
        $nonce = $this->texto($request, 'nonce', 200);
        $deviceId = $this->texto($request, 'device_id', 100);
        $deviceName = $this->texto($request, 'device_name', 150);

        $campos = array();
        if ($idToken === '' || strlen($idToken) > 8192) {
            $campos['id_token'] = 'Informe o token do Google.';
        }
        if (!self::deviceIdValido($deviceId)) {
            $campos['device_id'] = 'Identificador do dispositivo inválido.';
        }
        if ($campos) {
            return $this->erro('validacao', 'Verifique os dados informados.', 422, $campos);
        }

        $limite = $this->limitar($request, array('login_ip' => $this->ipReal($request)));
        if ($limite !== null) {
            return $limite;
        }

        $verificacao = (new GoogleIdTokenVerifier())->verificar($idToken, (array) GoogleConfig::get('app_client_ids', array()), $nonce !== '' ? $nonce : null);
        if (empty($verificacao['ok'])) {
            Logger::warning('Login Google: id_token recusado no app.', array('motivo' => $verificacao['motivo'] ?? null));
            return $this->erro('google_token_invalido', 'Não foi possível confirmar sua conta Google. Tente novamente.', 401);
        }

        $resultado = (new GoogleLoginService())->resolverUsuario($verificacao['claims'], 'app', $request->ip(), $request->userAgent());
        if (empty($resultado['ok'])) {
            if ($resultado['erro'] === 'conta_inativa') {
                return $this->erro('conta_inativa', $resultado['message'], 403);
            }
            if ($resultado['erro'] === 'email_nao_verificado') {
                return $this->erro('email_nao_verificado', $resultado['message'], 409);
            }
            return $this->erro('login_google_recusado', $resultado['message'], 409);
        }

        $usuario = $resultado['usuario'];
        $par = (new AppTokenService())->emitirParaLogin((int) $usuario['id'], $deviceId, $deviceName !== '' ? $deviceName : null, $request->ip(), $request->userAgent());

        $this->auditar('app.login_google', (int) $usuario['id'], $request, array('device_id' => $deviceId, 'device_name' => $deviceName, 'usuario_novo' => !empty($resultado['novo'])));

        $dados = UsuarioPresenter::tokens($par);
        $dados['usuario'] = UsuarioPresenter::usuario($usuario);
        $dados['usuario_novo'] = !empty($resultado['novo']);

        return $this->ok($dados);
    }

    public function refresh(Request $request)
    {
        $refresh = $this->texto($request, 'refresh_token', 200);
        $deviceId = $this->texto($request, 'device_id', 100);

        if ($refresh === '' || !self::deviceIdValido($deviceId)) {
            return $this->erro('validacao', 'Verifique os dados informados.', 422, array_filter(array(
                'refresh_token' => $refresh === '' ? 'Informe o refresh token.' : null,
                'device_id' => !self::deviceIdValido($deviceId) ? 'Identificador do dispositivo inválido.' : null,
            )));
        }

        $tokens = new AppTokenService();
        $resultado = $tokens->renovar($refresh, $deviceId, $request->ip(), $request->userAgent());
        if (empty($resultado['ok'])) {
            $motivo = isset($resultado['motivo']) ? $resultado['motivo'] : 'invalido';
            if (in_array($motivo, array('reuso', 'revogado', 'dispositivo_divergente'), true)) {
                return $this->erro('sessao_revogada', 'Sua sessão foi encerrada. Entre novamente.', 401);
            }
            return $this->erro('nao_autenticado', 'Sua sessão expirou. Entre novamente.', 401);
        }

        $usuario = (new Usuario())->findById((int) $resultado['usuario_id']);
        if (!$usuario || (string) ($usuario['status'] ?? '') !== 'ativo') {
            $tokens->revogarDispositivo($deviceId, 'usuario_inativo');
            return $this->erro('nao_autenticado', 'Faça login para continuar.', 401);
        }

        return $this->ok(UsuarioPresenter::tokens($resultado));
    }

    public function logout(Request $request)
    {
        $usuarioId = $this->usuarioId();
        $deviceId = (string) AppAuth::deviceId();

        (new AppTokenService())->revogarDispositivo($deviceId, 'logout');
        try {
            (new AppDispositivo())->removerDoUsuario($usuarioId, $deviceId);
        } catch (\Throwable $e) {
            Logger::warning('app.logout.dispositivo_nao_removido', array('exception' => get_class($e)));
        }
        $this->auditar('app.logout', $usuarioId, $request, array('device_id' => $deviceId));

        return $this->ok(array('ok' => true));
    }

    public function cadastro(Request $request)
    {
        $limite = $this->limitar($request, array('cadastro_ip' => $this->ipReal($request)));
        if ($limite !== null) {
            return $limite;
        }

        $entrada = array(
            'nome' => $this->texto($request, 'nome', 150),
            'email' => $this->texto($request, 'email', 191),
            'cpf' => $this->texto($request, 'cpf', 20),
            'telefone' => $this->texto($request, 'telefone', 30),
            'senha' => is_scalar($request->input('senha', '')) ? (string) $request->input('senha', '') : '',
            'senha_confirmacao' => is_scalar($request->input('senha_confirmacao', '')) ? (string) $request->input('senha_confirmacao', '') : '',
            'aceite_termos' => self::verdadeiro($request->input('aceite_termos', false)),
            'aceite_privacidade' => self::verdadeiro($request->input('aceite_privacidade', false)),
            'aceite_marketing' => self::verdadeiro($request->input('aceite_marketing', false)),
        );
        if ($entrada['telefone'] === '') {
            $entrada['telefone'] = null;
        }

        $resultado = (new AuthService())->register($entrada, $request->ip(), $request->userAgent());
        if (empty($resultado['ok'])) {
            return $this->erro('validacao', 'Verifique os dados informados.', 422, (array) ($resultado['errors'] ?? array()));
        }

        $this->auditar('app.cadastro', (int) $resultado['usuario_id'], $request, array());

        return $this->ok(array('ok' => true), 201);
    }

    public function recuperarSenha(Request $request)
    {
        $login = $this->texto($request, 'login', 191);
        if ($login === '') {
            return $this->erro('validacao', 'Verifique os dados informados.', 422, array('login' => 'Informe o e-mail ou CPF.'));
        }

        $limite = $this->limitar($request, array('recuperar_ip' => $this->ipReal($request), 'recuperar_conta' => $login));
        if ($limite !== null) {
            return $limite;
        }

        // Sempre a mesma resposta: não revela se a conta existe. O link do e-mail abre o site.
        (new AuthService())->requestPasswordReset($login, $request->ip(), $request->userAgent());

        return $this->ok(array('ok' => true));
    }

    /** Aplica os limites; devolve a resposta 429 quando algum estourou. */
    private function limitar(Request $request, array $escopos)
    {
        $limitador = new AppLimiteTaxaService();
        $retry = 0;
        foreach ($escopos as $escopo => $valor) {
            $resultado = $limitador->registrar($escopo, $valor);
            if (empty($resultado['permitido'])) {
                $retry = max($retry, (int) $resultado['retry_after']);
            }
        }
        if ($retry <= 0) {
            return null;
        }

        Logger::warning('app.limite_taxa.bloqueado', array(
            'path' => $request->path(),
            'escopos' => array_keys($escopos),
            'retry_after' => $retry,
        ));

        $minutos = (int) ceil($retry / 60);
        return $this->erro(
            'muitas_tentativas',
            'Muitas tentativas. Tente novamente em ' . $minutos . ($minutos === 1 ? ' minuto.' : ' minutos.'),
            429,
            null,
            array('Retry-After' => (string) $retry)
        );
    }

    private function auditar($acao, $usuarioId, Request $request, array $extra)
    {
        try {
            (new AuditService())->record($acao, 'usuario', $usuarioId, array_merge(array(
                'ip_address' => $request->ip(),
                'app_version' => $request->header('X-App-Version'),
                'platform' => $request->header('X-App-Platform'),
            ), $extra), $usuarioId, $request->ip(), $request->userAgent());
        } catch (\Throwable $e) {
            // AuditService tem fallback próprio
        }
    }

    public static function deviceIdValido($deviceId)
    {
        return (bool) preg_match('/^[A-Za-z0-9._:-]{8,100}$/', (string) $deviceId);
    }

    private static function verdadeiro($valor)
    {
        if (is_bool($valor)) {
            return $valor;
        }
        return in_array(strtolower(trim((string) $valor)), array('1', 'true', 'on', 'sim', 'yes'), true);
    }
}

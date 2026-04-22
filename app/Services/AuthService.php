<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Core\Validator;
use App\Models\ConsentimentoUsuario;
use App\Models\Usuario;

class AuthService
{
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCK_MINUTES = 15;

    private $usuarios;
    private $consentimentos;
    private $accessLogs;

    public function __construct()
    {
        $this->usuarios = new Usuario();
        $this->consentimentos = new ConsentimentoUsuario();
        $this->accessLogs = new AccessLogService();
    }

    public function userId()
    {
        return Session::get('usuario_id');
    }

    public function check()
    {
        return $this->userId() !== null;
    }

    public function register(array $input, $ipAddress, $userAgent)
    {
        $errors = $this->validateRegistration($input);

        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $email = strtolower(trim($input['email']));
        $cpf = Validator::onlyDigits($input['cpf']);

        if ($this->usuarios->findByEmail($email)) {
            $errors['email'] = 'Este e-mail ja esta cadastrado.';
        }

        if ($this->usuarios->findByCpf($cpf)) {
            $errors['cpf'] = 'Este CPF ja esta cadastrado.';
        }

        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $usuarioId = $this->usuarios->create(array(
                'nome' => Validator::upperName($input['nome']),
                'email' => $email,
                'cpf' => $cpf,
                'telefone' => isset($input['telefone']) ? $input['telefone'] : null,
                'senha_hash' => password_hash($input['senha'], PASSWORD_DEFAULT),
            ));

            $this->consentimentos->createForUser($usuarioId, array(
                array(
                    'tipo' => 'termos_uso',
                    'versao' => '1.0',
                    'obrigatorio' => true,
                    'consentido' => !empty($input['aceite_termos']),
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ),
                array(
                    'tipo' => 'politica_privacidade',
                    'versao' => '1.0',
                    'obrigatorio' => true,
                    'consentido' => !empty($input['aceite_privacidade']),
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ),
                array(
                    'tipo' => 'comunicacoes_marketing',
                    'versao' => '1.0',
                    'obrigatorio' => false,
                    'consentido' => !empty($input['aceite_marketing']),
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ),
            ));

            $connection->commit();
        } catch (\Exception $exception) {
            $connection->rollBack();
            throw $exception;
        }

        $this->accessLogs->record($usuarioId, 'register', 'success', $ipAddress, $userAgent);

        return array('ok' => true, 'usuario_id' => $usuarioId);
    }

    public function login($login, $senha, $ipAddress, $userAgent)
    {
        $usuario = $this->usuarios->findByLogin($login);

        if (!$usuario) {
            $this->accessLogs->record(null, 'login', 'user_not_found', $ipAddress, $userAgent, array('login' => $login));
            return array('ok' => false, 'message' => 'Dados de acesso invalidos.');
        }

        if ($this->isBlocked($usuario)) {
            $this->accessLogs->record($usuario['id'], 'login', 'temporarily_blocked', $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso temporariamente bloqueado. Tente novamente mais tarde.');
        }

        if ($usuario['status'] !== 'ativo') {
            $this->accessLogs->record($usuario['id'], 'login', 'inactive_user', $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Usuario sem permissao de acesso.');
        }

        if (!password_verify((string) $senha, $usuario['senha_hash'])) {
            $attempts = $this->usuarios->incrementLoginAttempts($usuario['id'], $usuario['tentativas_login'], self::LOCK_MINUTES, self::MAX_LOGIN_ATTEMPTS);
            $result = $attempts >= self::MAX_LOGIN_ATTEMPTS ? 'invalid_password_blocked' : 'invalid_password';
            $this->accessLogs->record($usuario['id'], 'login', $result, $ipAddress, $userAgent);

            return array('ok' => false, 'message' => 'Dados de acesso invalidos.');
        }

        $this->usuarios->resetLoginAttempts($usuario['id']);
        Session::regenerate();
        Session::put('usuario_id', $usuario['id']);
        Session::put('usuario_nome', $usuario['nome']);
        Session::put('usuario_email', $usuario['email']);

        $this->accessLogs->record($usuario['id'], 'login', 'success', $ipAddress, $userAgent);

        return array('ok' => true, 'usuario' => $usuario);
    }

    public function logout($ipAddress, $userAgent)
    {
        $usuarioId = Session::get('usuario_id');
        $this->accessLogs->record($usuarioId, 'logout', 'success', $ipAddress, $userAgent);
        Session::destroy();
    }

    public function requestPasswordReset($login, $ipAddress, $userAgent)
    {
        $usuario = $this->usuarios->findByLogin($login);

        if (!$usuario) {
            $this->accessLogs->record(null, 'password_reset_request', 'user_not_found', $ipAddress, $userAgent, array('login' => $login));
            return array('ok' => true, 'token' => null);
        }

        $token = bin2hex(random_bytes(32));
        $this->usuarios->setRecoveryToken($usuario['id'], $token);
        $this->accessLogs->record($usuario['id'], 'password_reset_request', 'token_generated', $ipAddress, $userAgent);

        return array('ok' => true, 'token' => $token);
    }

    public function resetPassword($token, $senha, $senhaConfirmacao, $ipAddress, $userAgent)
    {
        $errors = array();

        if (strlen((string) $senha) < 8) {
            $errors['senha'] = 'A senha deve ter pelo menos 8 caracteres.';
        }

        if ($senha !== $senhaConfirmacao) {
            $errors['senha_confirmacao'] = 'A confirmacao da senha nao confere.';
        }

        $usuario = $this->usuarios->findByRecoveryToken($token);

        if (!$usuario) {
            $errors['token'] = 'Token invalido ou expirado.';
        }

        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $this->usuarios->updatePassword($usuario['id'], password_hash($senha, PASSWORD_DEFAULT));
        $this->accessLogs->record($usuario['id'], 'password_reset', 'success', $ipAddress, $userAgent);

        return array('ok' => true);
    }

    private function validateRegistration(array $input)
    {
        $errors = array();

        if (trim((string) (isset($input['nome']) ? $input['nome'] : '')) === '') {
            $errors['nome'] = 'Informe o nome.';
        }

        if (!Validator::email(isset($input['email']) ? $input['email'] : '')) {
            $errors['email'] = 'Informe um e-mail valido.';
        }

        if (!Validator::cpf(isset($input['cpf']) ? $input['cpf'] : '')) {
            $errors['cpf'] = 'Informe um CPF valido.';
        }

        if (strlen((string) (isset($input['senha']) ? $input['senha'] : '')) < 8) {
            $errors['senha'] = 'A senha deve ter pelo menos 8 caracteres.';
        }

        if ((isset($input['senha']) ? $input['senha'] : '') !== (isset($input['senha_confirmacao']) ? $input['senha_confirmacao'] : '')) {
            $errors['senha_confirmacao'] = 'A confirmacao da senha nao confere.';
        }

        if (empty($input['aceite_termos'])) {
            $errors['aceite_termos'] = 'O aceite dos termos de uso e obrigatorio.';
        }

        if (empty($input['aceite_privacidade'])) {
            $errors['aceite_privacidade'] = 'O aceite da politica de privacidade e obrigatorio.';
        }

        return $errors;
    }

    private function isBlocked(array $usuario)
    {
        if (empty($usuario['bloqueado_ate'])) {
            return false;
        }

        return strtotime($usuario['bloqueado_ate']) > time();
    }
}

<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Core\Validator;
use App\Models\ConsentimentoUsuario;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Models\UsuarioPerfil;

class AuthService
{
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCK_MINUTES = 15;

    private $usuarios;
    private $perfilModel;
    private $usuarioPerfilModel;
    private $consentimentos;
    private $accessLogs;
    private $emailService;
    private $globalConfigService;
    private $rbacService;

    public function __construct()
    {
        $this->usuarios = new Usuario();
        $this->perfilModel = new Perfil();
        $this->usuarioPerfilModel = new UsuarioPerfil();
        $this->consentimentos = new ConsentimentoUsuario();
        $this->accessLogs = new AccessLogService();
        $this->emailService = new EmailService();
        $this->globalConfigService = new ConfiguracaoGlobalService();
        $this->rbacService = new RbacService();
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

            $perfilAluno = $this->perfilModel->findBySlug('aluno');
            if ($perfilAluno) {
                $this->usuarioPerfilModel->sync($usuarioId, array((int) $perfilAluno['id']));
            }

            $connection->commit();
        } catch (\Exception $exception) {
            $connection->rollBack();
            throw $exception;
        }

        $this->accessLogs->record($usuarioId, 'register', 'success', $ipAddress, $userAgent);
        $this->emailService->welcome(
            array(
                'id' => $usuarioId,
                'nome' => $input['nome'],
                'email' => $email,
            ),
            $usuarioId,
            $ipAddress,
            $userAgent
        );

        return array('ok' => true, 'usuario_id' => $usuarioId);
    }

    public function login($login, $senha, $ipAddress, $userAgent)
    {
        $security = $this->globalConfigService->seguranca();
        if (!$this->loginPermittedByPolicy($login, isset($security['politica_login']) ? $security['politica_login'] : 'email_cpf')) {
            $this->accessLogs->record(null, 'login', 'policy_blocked', $ipAddress, $userAgent, array('login' => $login));
            return array('ok' => false, 'message' => 'Dados de acesso invalidos.');
        }

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
            $lockMinutes = isset($security['tempo_bloqueio_login_minutos']) ? (int) $security['tempo_bloqueio_login_minutos'] : self::LOCK_MINUTES;
            $maxAttempts = isset($security['max_tentativas_login']) ? (int) $security['max_tentativas_login'] : self::MAX_LOGIN_ATTEMPTS;
            $attempts = $this->usuarios->incrementLoginAttempts($usuario['id'], $usuario['tentativas_login'], $lockMinutes, $maxAttempts);
            $result = $attempts >= $maxAttempts ? 'invalid_password_blocked' : 'invalid_password';
            $this->accessLogs->record($usuario['id'], 'login', $result, $ipAddress, $userAgent);

            return array('ok' => false, 'message' => 'Dados de acesso invalidos.');
        }

        $this->usuarios->resetLoginAttempts($usuario['id']);
        Session::regenerate();
        Session::put('usuario_id', $usuario['id']);
        Session::put('usuario_nome', $usuario['nome']);
        Session::put('usuario_email', $usuario['email']);
        Session::put('usuario_cpf', isset($usuario['cpf']) ? (string) $usuario['cpf'] : '');
        Session::put('usuario_telefone', isset($usuario['telefone']) ? (string) $usuario['telefone'] : '');

        $this->accessLogs->record($usuario['id'], 'login', 'success', $ipAddress, $userAgent);

        return array(
            'ok' => true,
            'usuario' => $usuario,
            'redirect_to' => $this->resolveLoginRedirect((int) $usuario['id']),
        );
    }

    public function logout($ipAddress, $userAgent)
    {
        $usuarioId = Session::get('usuario_id');
        $this->accessLogs->record($usuarioId, 'logout', 'success', $ipAddress, $userAgent);
        Session::destroy();
    }

    public function accountData($usuarioId)
    {
        $usuario = $this->usuarios->findById((int) $usuarioId);
        if (!$usuario) {
            return null;
        }

        return array(
            'nome' => isset($usuario['nome']) ? (string) $usuario['nome'] : '',
            'email' => isset($usuario['email']) ? (string) $usuario['email'] : '',
            'cpf' => isset($usuario['cpf']) ? (string) $usuario['cpf'] : '',
            'telefone' => isset($usuario['telefone']) ? (string) $usuario['telefone'] : '',
            'cidade' => isset($usuario['cidade']) ? (string) $usuario['cidade'] : '',
            'estado' => isset($usuario['estado']) ? (string) $usuario['estado'] : '',
        );
    }

    public function updateAccount($usuarioId, array $input, $ipAddress, $userAgent)
    {
        $usuarioAtual = $this->usuarios->findById((int) $usuarioId);
        if (!$usuarioAtual) {
            return array('ok' => false, 'errors' => array('conta' => 'Usuário não encontrado.'));
        }

        $errors = array();
        $nome = trim((string) (isset($input['nome']) ? $input['nome'] : ''));
        $email = strtolower(trim((string) (isset($input['email']) ? $input['email'] : '')));
        $cpf = Validator::onlyDigits(isset($input['cpf']) ? $input['cpf'] : '');
        $telefone = trim((string) (isset($input['telefone']) ? $input['telefone'] : ''));
        $cidade = trim((string) (isset($input['cidade']) ? $input['cidade'] : ''));
        $estado = strtoupper(trim((string) (isset($input['estado']) ? $input['estado'] : '')));
        $novaSenha = (string) (isset($input['nova_senha']) ? $input['nova_senha'] : '');
        $confirmacaoNovaSenha = (string) (isset($input['nova_senha_confirmacao']) ? $input['nova_senha_confirmacao'] : '');

        if ($nome === '') {
            $errors['nome'] = 'Informe o nome.';
        }

        if (!Validator::email($email)) {
            $errors['email'] = 'Informe um e-mail válido.';
        }

        if (!Validator::cpf($cpf)) {
            $errors['cpf'] = 'Informe um CPF válido.';
        }

        if ($estado !== '' && !preg_match('/^[A-Z]{2}$/', $estado)) {
            $errors['estado'] = 'Informe um estado válido com 2 letras.';
        }

        if ($estado !== '' && $cidade === '') {
            $errors['cidade'] = 'Selecione uma cidade.';
        }

        if ($cidade !== '' && $estado === '') {
            $errors['estado'] = 'Selecione um estado.';
        }

        if ($novaSenha !== '' || $confirmacaoNovaSenha !== '') {
            if (strlen($novaSenha) < 8) {
                $errors['nova_senha'] = 'A nova senha deve ter pelo menos 8 caracteres.';
            }

            if ($novaSenha !== $confirmacaoNovaSenha) {
                $errors['nova_senha_confirmacao'] = 'A confirmação da nova senha não confere.';
            }
        }

        $emailExistente = $this->usuarios->findByEmail($email);
        if ($emailExistente && (int) $emailExistente['id'] !== (int) $usuarioId) {
            $errors['email'] = 'Este e-mail já está cadastrado.';
        }

        $cpfExistente = $this->usuarios->findByCpf($cpf);
        if ($cpfExistente && (int) $cpfExistente['id'] !== (int) $usuarioId) {
            $errors['cpf'] = 'Este CPF já está cadastrado.';
        }

        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $this->usuarios->updateProfile((int) $usuarioId, array(
            'nome' => Validator::upperName($nome),
            'email' => $email,
            'cpf' => $cpf,
            'telefone' => $telefone,
            'cidade' => $cidade !== '' ? $cidade : null,
            'estado' => $estado !== '' ? $estado : null,
        ));

        if ($novaSenha !== '') {
            $this->usuarios->updatePassword((int) $usuarioId, password_hash($novaSenha, PASSWORD_DEFAULT));
        }

        Session::put('usuario_nome', Validator::upperName($nome));
        Session::put('usuario_email', $email);
        Session::put('usuario_cpf', $cpf);
        Session::put('usuario_telefone', $telefone);
        $this->accessLogs->record($usuarioId, 'account_update', 'success', $ipAddress, $userAgent);

        return array('ok' => true);
    }

    public function requestPasswordReset($login, $ipAddress, $userAgent)
    {
        $security = $this->globalConfigService->seguranca();
        if (!$this->loginPermittedByPolicy($login, isset($security['politica_login']) ? $security['politica_login'] : 'email_cpf')) {
            $this->accessLogs->record(null, 'password_reset_request', 'policy_blocked', $ipAddress, $userAgent, array('login' => $login));
            return array('ok' => true, 'token' => null);
        }

        $usuario = $this->usuarios->findByLogin($login);

        if (!$usuario) {
            $this->accessLogs->record(null, 'password_reset_request', 'user_not_found', $ipAddress, $userAgent, array('login' => $login));
            return array('ok' => true, 'token' => null);
        }

        $token = bin2hex(random_bytes(32));
        $validade = isset($security['validade_reset_senha_minutos']) ? (int) $security['validade_reset_senha_minutos'] : 60;
        $this->usuarios->setRecoveryToken($usuario['id'], $token, $validade);
        $this->accessLogs->record($usuario['id'], 'password_reset_request', 'token_generated', $ipAddress, $userAgent);
        $this->emailService->passwordReset($usuario, $token, $usuario['id'], $ipAddress, $userAgent);

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

    private function loginPermittedByPolicy($login, $policy)
    {
        $login = trim((string) $login);

        if ($policy === 'email') {
            return Validator::email($login);
        }

        if ($policy === 'cpf') {
            return Validator::cpf($login);
        }

        return Validator::email($login) || Validator::cpf($login);
    }

    private function resolveLoginRedirect($usuarioId)
    {
        if ($this->canAccessAdminDashboard($usuarioId)) {
            return '/admin/dashboard';
        }

        if ($this->canAccessProfessorDashboard($usuarioId)) {
            return '/professor/dashboard';
        }

        return '/';
    }

    private function canAccessAdminDashboard($usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        return $this->rbacService->userHasAnyPermission($usuarioId, array(
            'rbac.dashboard.ver',
            'pedidos.ver',
            'financeiro.ver',
            'conteudo.ver',
            'marketing.ver',
            'certificados.ver',
            'configuracoes_globais.ver',
            'emails.ver',
            'academico.ver',
            'area_curso.gerenciar',
            'cupons.ver',
        ));
    }

    private function canAccessProfessorDashboard($usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        return $this->rbacService->userHasAnyPermission($usuarioId, array(
            'professor.ver',
            'professor.gerenciar',
            'catalogo.professor.ver',
            'area_curso.professor.ver',
            'financeiro.professor.ver',
            'academico.ver',
        ));
    }
}



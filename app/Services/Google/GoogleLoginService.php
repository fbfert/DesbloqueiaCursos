<?php

namespace App\Services\Google;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Validator;
use App\Models\ConsentimentoUsuario;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Models\UsuarioIdentidade;
use App\Models\UsuarioPerfil;
use App\Services\AccessLogService;
use App\Services\AuditService;
use App\Services\EmailService;
use App\Support\AppApi\Tempo;
use DateTimeImmutable;
use PDOException;

/**
 * Resolve a conta do portal a partir das claims de um id_token do Google já
 * validado (GoogleIdTokenVerifier). Usado pelo site e pelo app.
 *
 * Ordem: `sub` já vinculado → usuário com o mesmo e-mail, se o Google declarar o
 * e-mail verificado (vínculo automático, auditado e avisado por e-mail) → criação
 * de conta de aluno sem senha e sem CPF. Conta que não está ativa nunca entra; o
 * bloqueio temporário por senha errada não se aplica (não há senha aqui).
 */
class GoogleLoginService
{
    const PROVEDOR = 'google';

    const MSG_EMAIL_NAO_VERIFICADO = 'Seu e-mail no Google não está verificado.';
    const MSG_CONTA_INATIVA = 'Usuário sem permissão de acesso.';
    const MSG_FALHA = 'Não foi possível entrar com o Google agora. Tente novamente ou use e-mail e senha.';

    private $usuarios;
    private $identidades;
    private $auditoria;
    private $acessos;
    private $emails;

    public function __construct(?EmailService $emails = null)
    {
        $this->usuarios = new Usuario();
        $this->identidades = new UsuarioIdentidade();
        $this->auditoria = new AuditService();
        $this->acessos = new AccessLogService();
        $this->emails = $emails;
    }

    /**
     * @param string $canal 'site' | 'app'
     * @return array ok => true, usuario, novo, vinculado_agora | ok => false, erro, message
     */
    public function resolverUsuario(array $claims, $canal, $ipAddress, $userAgent)
    {
        $evento = $canal === 'app' ? 'app_login_google' : 'login_google';
        $sub = trim((string) ($claims['sub'] ?? ''));
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $verificado = ($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? '') === 'true';

        if ($sub === '') {
            return $this->falha('falha', null, $evento, 'sem_sub', $ipAddress, $userAgent);
        }

        // 1. Conta Google já vinculada: identifica pelo sub, nunca pelo e-mail.
        $identidade = $this->identidades->findByProvedorSub(self::PROVEDOR, $sub);
        if ($identidade) {
            $usuario = $this->usuarios->findById((int) $identidade['usuario_id']);
            if (!$usuario || $usuario['status'] !== 'ativo') {
                return $this->falha('conta_inativa', $usuario ? (int) $usuario['id'] : null, $evento, 'inactive_user', $ipAddress, $userAgent);
            }
            $this->identidades->tocarUso((int) $identidade['id'], $email);
            return $this->sucesso($usuario, false, false, $evento, $ipAddress, $userAgent);
        }

        if (!$verificado || $email === '' || !Validator::email($email)) {
            return $this->falha('email_nao_verificado', null, $evento, 'email_not_verified', $ipAddress, $userAgent);
        }

        // 2. Conta existente com o mesmo e-mail verificado: vínculo automático.
        $usuario = $this->usuarios->findByEmail($email);
        if ($usuario) {
            return $this->vincularExistente($usuario, $sub, $email, $canal, $evento, $ipAddress, $userAgent);
        }

        // 3. Ninguém com esse e-mail: cria a conta de aluno.
        return $this->criarConta($claims, $sub, $email, $canal, $evento, $ipAddress, $userAgent);
    }

    private function vincularExistente(array $usuario, $sub, $email, $canal, $evento, $ipAddress, $userAgent)
    {
        $usuarioId = (int) $usuario['id'];
        if ($usuario['status'] !== 'ativo') {
            return $this->falha('conta_inativa', $usuarioId, $evento, 'inactive_user', $ipAddress, $userAgent);
        }

        // A conta já tem OUTRA conta Google: não troca o vínculo em silêncio.
        if ($this->identidades->findByUsuario($usuarioId, self::PROVEDOR)) {
            Logger::warning('Login Google: e-mail de conta já vinculada a outra conta Google.', array('usuario_id' => $usuarioId));
            return $this->falha('falha', $usuarioId, $evento, 'other_google_account', $ipAddress, $userAgent);
        }

        try {
            $this->identidades->vincular($usuarioId, self::PROVEDOR, $sub, $email);
        } catch (PDOException $e) {
            // Corrida: outra requisição do mesmo login vinculou primeiro.
            $identidade = $this->identidades->findByProvedorSub(self::PROVEDOR, $sub);
            if ($identidade && (int) $identidade['usuario_id'] === $usuarioId) {
                return $this->sucesso($usuario, false, false, $evento, $ipAddress, $userAgent);
            }
            Logger::error('Login Google: falha ao vincular conta.', array('usuario_id' => $usuarioId, 'erro' => $e->getMessage()));
            return $this->falha('falha', $usuarioId, $evento, 'link_failed', $ipAddress, $userAgent);
        }

        $this->auditoria->record('autenticacao.google_vinculado', 'usuario', $usuarioId, array(
            'email_google' => $email,
            'canal' => $canal,
        ), $usuarioId, $ipAddress, $userAgent);

        try {
            $this->emails()->googleVinculado($usuario, $email, self::agoraLegivel(), $ipAddress, $userAgent);
        } catch (\Throwable $e) {
            Logger::error('Login Google: falha ao enviar o aviso de vínculo.', array('usuario_id' => $usuarioId, 'erro' => $e->getMessage()));
        }

        return $this->sucesso($usuario, false, true, $evento, $ipAddress, $userAgent);
    }

    private function criarConta(array $claims, $sub, $email, $canal, $evento, $ipAddress, $userAgent)
    {
        $nome = Validator::upperName((string) ($claims['name'] ?? ''));
        if ($nome === '') {
            $nome = Validator::upperName(trim((string) ($claims['given_name'] ?? '') . ' ' . (string) ($claims['family_name'] ?? '')));
        }
        if ($nome === '') {
            $nome = Validator::upperName(strstr($email, '@', true));
        }
        $nome = mb_substr($nome, 0, 150);
        $ua = $userAgent !== null ? mb_substr((string) $userAgent, 0, 255) : null;

        $pdo = Database::connection();
        $transacaoPropria = !$pdo->inTransaction();
        if ($transacaoPropria) {
            $pdo->beginTransaction();
        }

        try {
            $usuarioId = $this->usuarios->createPorProvedor($nome, $email, self::PROVEDOR);

            // Mesmo registro do cadastro por formulário; o aceite vem do aviso exibido
            // junto ao botão "Entrar com Google". A origem fica em cadastro_origem e na
            // auditoria (usuario_consentimentos não tem coluna de origem).
            (new ConsentimentoUsuario())->createForUser($usuarioId, array(
                array('tipo' => 'termos_uso', 'versao' => '1.0', 'obrigatorio' => true, 'consentido' => true, 'ip_address' => $ipAddress, 'user_agent' => $ua),
                array('tipo' => 'politica_privacidade', 'versao' => '1.0', 'obrigatorio' => true, 'consentido' => true, 'ip_address' => $ipAddress, 'user_agent' => $ua),
                array('tipo' => 'comunicacoes_marketing', 'versao' => '1.0', 'obrigatorio' => false, 'consentido' => false, 'ip_address' => $ipAddress, 'user_agent' => $ua),
            ));

            $perfilAluno = (new Perfil())->findBySlug('aluno');
            if ($perfilAluno) {
                (new UsuarioPerfil())->sync($usuarioId, array((int) $perfilAluno['id']));
            }

            $this->identidades->vincular($usuarioId, self::PROVEDOR, $sub, $email);

            $this->auditoria->record('autenticacao.google_conta_criada', 'usuario', $usuarioId, array(
                'email_google' => $email,
                'canal' => $canal,
                'consentimento' => 'aviso junto ao botão Entrar com Google',
            ), $usuarioId, $ipAddress, $ua);

            if ($transacaoPropria) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($transacaoPropria && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (!$e instanceof PDOException) {
                throw $e;
            }

            // Corrida no primeiro acesso: outra requisição já criou e vinculou.
            $identidade = $this->identidades->findByProvedorSub(self::PROVEDOR, $sub);
            if ($identidade) {
                $usuario = $this->usuarios->findById((int) $identidade['usuario_id']);
                if ($usuario && $usuario['status'] === 'ativo') {
                    return $this->sucesso($usuario, false, false, $evento, $ipAddress, $userAgent);
                }
            }

            // E-mail de conta excluída (o UNIQUE vale também para excluídas).
            Logger::error('Login Google: não foi possível criar a conta.', array('email' => $email, 'erro' => $e->getMessage()));
            return $this->falha('falha', null, $evento, 'create_failed', $ipAddress, $userAgent);
        }

        $usuario = $this->usuarios->findById($usuarioId);

        try {
            $this->emails()->welcome(array('id' => $usuarioId, 'nome' => $nome, 'email' => $email), $usuarioId, $ipAddress, $userAgent);
        } catch (\Throwable $e) {
            Logger::error('Login Google: falha ao enviar boas-vindas.', array('usuario_id' => $usuarioId, 'erro' => $e->getMessage()));
        }

        return $this->sucesso($usuario, true, false, $evento, $ipAddress, $userAgent);
    }

    /** Vínculo Google da conta, para "Minha conta" (ou null). */
    public function vinculoDoUsuario($usuarioId)
    {
        return $this->identidades->findByUsuario((int) $usuarioId, self::PROVEDOR);
    }

    /**
     * Remove o vínculo com o Google. Só para quem tem senha: sem senha a pessoa
     * ficaria sem forma de entrar. Passa pela lixeira e pela auditoria.
     */
    public function desvincular($usuarioId, $ipAddress = null, $userAgent = null)
    {
        $usuario = $this->usuarios->findById((int) $usuarioId);
        $vinculo = $this->vinculoDoUsuario($usuarioId);
        if (!$usuario || !$vinculo) {
            return array('ok' => false, 'message' => 'Nenhuma conta Google está vinculada.');
        }
        if (trim((string) ($usuario['senha_hash'] ?? '')) === '') {
            return array('ok' => false, 'message' => 'Defina uma senha antes de desvincular a conta Google, para não ficar sem acesso.');
        }

        (new \App\Services\TrashService())->record('usuario_identidade', (int) $vinculo['id'], 'Desvinculado pelo próprio usuário', $vinculo, (int) $usuarioId, $ipAddress, $userAgent);
        $this->identidades->remover((int) $vinculo['id']);
        $this->auditoria->record('autenticacao.google_desvinculado', 'usuario', (int) $usuarioId, array(
            'email_google' => $vinculo['email'],
        ), (int) $usuarioId, $ipAddress, $userAgent);

        return array('ok' => true);
    }

    private function sucesso(array $usuario, $novo, $vinculadoAgora, $evento, $ipAddress, $userAgent)
    {
        $this->acessos->record((int) $usuario['id'], $evento, 'success', $ipAddress, $userAgent, array(
            'novo' => (bool) $novo,
            'vinculado_agora' => (bool) $vinculadoAgora,
        ));

        return array('ok' => true, 'usuario' => $usuario, 'novo' => (bool) $novo, 'vinculado_agora' => (bool) $vinculadoAgora);
    }

    private function falha($erro, $usuarioId, $evento, $resultado, $ipAddress, $userAgent)
    {
        $this->acessos->record($usuarioId, $evento, $resultado, $ipAddress, $userAgent);
        $mensagens = array(
            'email_nao_verificado' => self::MSG_EMAIL_NAO_VERIFICADO,
            'conta_inativa' => self::MSG_CONTA_INATIVA,
            'falha' => self::MSG_FALHA,
        );

        return array('ok' => false, 'erro' => $erro, 'message' => $mensagens[$erro] ?? self::MSG_FALHA);
    }

    private function emails()
    {
        if ($this->emails === null) {
            $this->emails = new EmailService();
        }
        return $this->emails;
    }

    /** "09/10/2026 às 14:32", no fuso da aplicação. */
    public static function agoraLegivel()
    {
        return (new DateTimeImmutable('@' . Tempo::agoraTs()))->setTimezone(Tempo::fuso())->format('d/m/Y \à\s H:i');
    }
}

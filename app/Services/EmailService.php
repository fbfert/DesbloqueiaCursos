<?php

namespace App\Services;

use App\Core\Helpers;
use App\Core\Logger;
use App\Core\View;
use App\Models\EmailConfiguracao;
use App\Models\EmailEnvio;
use Exception;

class EmailService
{
    private $configModel;
    private $emailModel;
    private $modeloService;
    private $globalConfigService;
    private $auditService;
    private $configFallback;
    private $runtimeDiagnosticsLogged = false;

    public function __construct()
    {
        $this->configModel = new EmailConfiguracao();
        $this->emailModel = new EmailEnvio();
        $this->modeloService = new EmailModeloService();
        $this->globalConfigService = new ConfiguracaoGlobalService();
        $this->auditService = new AuditService();
        $this->configFallback = require BASE_PATH . '/config/mail.php';
    }

    public function configuration()
    {
        $stored = $this->configModel->current();

        if (!$stored) {
            $globalDefaults = $this->globalConfigService->emailDefaults();
            $fallbackFrom = $this->resolveSenderEmail(
                !empty($globalDefaults['from_email']) ? $globalDefaults['from_email'] : null,
                $this->configFallback['from_email'],
                $globalDefaults
            );
            $fallbackReplyTo = $this->resolveSenderEmail(
                !empty($globalDefaults['reply_to']) ? $globalDefaults['reply_to'] : null,
                $this->configFallback['reply_to'],
                $globalDefaults
            );

            return array_merge($this->configFallback, array(
                'from_email' => $fallbackFrom,
                'from_name' => !empty($globalDefaults['from_name']) ? $globalDefaults['from_name'] : $this->configFallback['from_name'],
                'reply_to' => $fallbackReplyTo,
            ));
        }

        $globalDefaults = $this->globalConfigService->emailDefaults();
        $storedFrom = $this->resolveSenderEmail(
            !empty($stored['from_email']) ? $stored['from_email'] : null,
            isset($stored['usuario']) ? $stored['usuario'] : null,
            $globalDefaults
        );
        $storedReplyTo = $this->resolveSenderEmail(
            !empty($stored['reply_to_email']) ? $stored['reply_to_email'] : null,
            isset($stored['usuario']) ? $stored['usuario'] : null,
            $globalDefaults
        );

        return array_merge($this->configFallback, array(
            'enabled' => (bool) $stored['ativo'],
            'host' => $stored['host'],
            'port' => (int) $stored['porta'],
            'username' => $stored['usuario'],
            'password' => $stored['senha'],
            'encryption' => $stored['criptografia'],
            'from_email' => $storedFrom,
            'from_name' => !empty($stored['from_name']) ? $stored['from_name'] : $globalDefaults['from_name'],
            'reply_to' => $storedReplyTo,
            'queue_processing' => (bool) $stored['fila_ativa'],
        ));
    }

    public function saveConfiguration(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $payload = array(
            'nome' => trim((string) (isset($data['nome']) ? $data['nome'] : 'SMTP principal')),
            'ativo' => !empty($data['ativo']),
            'host' => trim((string) (isset($data['host']) ? $data['host'] : '')),
            'porta' => (int) (isset($data['porta']) ? $data['porta'] : 587),
            'usuario' => isset($data['usuario']) ? trim((string) $data['usuario']) : null,
            'senha' => isset($data['senha']) && trim((string) $data['senha']) !== '' ? (string) $data['senha'] : null,
            'criptografia' => isset($data['criptografia']) ? trim((string) $data['criptografia']) : 'tls',
            'from_email' => trim((string) (isset($data['from_email']) ? $data['from_email'] : '')),
            'from_name' => trim((string) (isset($data['from_name']) ? $data['from_name'] : '')),
            'reply_to_email' => isset($data['reply_to_email']) ? trim((string) $data['reply_to_email']) : null,
            'fila_ativa' => !empty($data['fila_ativa']),
        );

        $id = $this->configModel->save($payload);

        $this->auditService->record(
            'emails.configuracao.atualizada',
            'emails_configuracao',
            $id,
            array(
                'ativo' => $payload['ativo'],
                'host' => $payload['host'],
                'porta' => $payload['porta'],
                'encryption' => $payload['criptografia'],
                'from_email' => $payload['from_email'],
                'from_name' => $payload['from_name'],
                'fila_ativa' => $payload['fila_ativa'],
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('emails.configuracao.atualizada', array('id' => $id));

        return array('ok' => true, 'id' => $id);
    }

    public function listQueue()
    {
        return $this->emailModel->listForAdmin();
    }

    public function resendExisting($emailEnvioId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $emailEnvioId = (int) $emailEnvioId;
        if ($emailEnvioId <= 0) {
            return array('ok' => false, 'message' => 'E-mail inválido.');
        }

        $stored = $this->emailModel->findById($emailEnvioId);
        if (!$stored) {
            return array('ok' => false, 'message' => 'E-mail não encontrado.');
        }

        $status = isset($stored['status']) ? (string) $stored['status'] : '';
        if (!in_array($status, array('pendente', 'falhou'), true)) {
            return array('ok' => false, 'message' => 'Este e-mail não pode ser reenviado.');
        }

        $data = array();
        if (!empty($stored['contexto_json'])) {
            $decoded = json_decode((string) $stored['contexto_json'], true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $evento = isset($stored['evento']) ? (string) $stored['evento'] : '';
        $template = isset($stored['template']) ? (string) $stored['template'] : '';

        $contextoRenderizacao = $this->modeloService->buildContext($data);

        $assuntoBase = isset($stored['assunto']) ? (string) $stored['assunto'] : '';
        $htmlBase = null;

        $modelo = $this->modeloService->findByEvento($evento);
        if ($modelo && empty($modelo['ativo'])) {
            Logger::info('emails.reenvio.modelo_desativado', array('email_id' => $emailEnvioId, 'evento' => $evento));
            return array('ok' => false, 'message' => 'Envio automatizado desativado para este modelo.');
        }

        if ($modelo) {
            if (!empty($modelo['assunto'])) {
                $assuntoBase = (string) $modelo['assunto'];
            }

            if (!empty($modelo['corpo_html'])) {
                $htmlBase = (string) $modelo['corpo_html'];
            }
        }

        $assuntoFinal = $this->modeloService->renderPlaceholders($assuntoBase, $contextoRenderizacao);

        if ($htmlBase !== null && trim($htmlBase) !== '') {
            $rendered = $this->modeloService->renderPlaceholders($htmlBase, $contextoRenderizacao);
        } else {
            $rendered = View::render($template, $data, false, 'emails');
        }

        $config = $this->configuration();
        $this->logRuntimeDiagnostics($config);

        if (empty($config['enabled']) || empty($config['host'])) {
            $erro = 'Configuração SMTP indisponível.';
            $this->emailModel->markFailed($emailEnvioId, $erro);
            Logger::error('emails.reenvio.falhou', array('email_id' => $emailEnvioId, 'erro' => $erro));
            return array('ok' => false, 'message' => $erro);
        }

        try {
            $response = $this->sendSmtpMessage($config, array(
                'from_email' => $config['from_email'],
                'from_name' => $config['from_name'],
                'reply_to' => $config['reply_to'],
                'to_email' => isset($stored['destinatario_email']) ? $stored['destinatario_email'] : null,
                'to_name' => isset($stored['destinatario_nome']) ? $stored['destinatario_nome'] : null,
                'subject' => $assuntoFinal,
                'html' => $rendered,
            ));

            $this->emailModel->markSent($emailEnvioId, $response);
            $this->auditService->record(
                'emails.reenviado',
                'email',
                $emailEnvioId,
                array('evento' => $evento, 'template' => $template),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('emails.reenviado', array('email_id' => $emailEnvioId, 'evento' => $evento));
            return array('ok' => true, 'email_id' => $emailEnvioId, 'response' => $response);
        } catch (Exception $exception) {
            $this->emailModel->markFailed($emailEnvioId, $exception->getMessage());
            $this->auditService->record(
                'emails.reenvio_falhou',
                'email',
                $emailEnvioId,
                array('erro' => $exception->getMessage()),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::error('emails.reenvio_falhou', array(
                'email_id' => $emailEnvioId,
                'evento' => $evento,
                'erro' => $exception->getMessage(),
            ));

            return array('ok' => false, 'message' => $exception->getMessage(), 'email_id' => $emailEnvioId);
        }
    }

    public function welcome(array $usuario, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->sendTemplate(
            'email.welcome',
            'welcome',
            isset($usuario['email']) ? $usuario['email'] : null,
            isset($usuario['nome']) ? $usuario['nome'] : null,
            'Bem-vindo ao {sistema.nome}',
            array('usuario' => $usuario),
            'usuario',
            isset($usuario['id']) ? $usuario['id'] : null,
            $actorUserId,
            $ipAddress,
            $userAgent
        );
    }

    public function passwordReset(array $usuario, $token, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->sendTemplate(
            'email.password_reset',
            'password_reset',
            isset($usuario['email']) ? $usuario['email'] : null,
            isset($usuario['nome']) ? $usuario['nome'] : null,
            'Recuperação de senha',
            array(
                'usuario' => $usuario,
                'token' => $token,
                'reset_url' => Helpers::url('recuperar-senha/redefinir?token=' . urlencode($token)),
            ),
            'usuario',
            isset($usuario['id']) ? $usuario['id'] : null,
            $actorUserId,
            $ipAddress,
            $userAgent
        );
    }

    public function pedidoCriado(array $pedido, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->sendTemplate(
            'email.pedido_criado',
            'pedido_criado',
            isset($pedido['pagador_email']) ? $pedido['pagador_email'] : null,
            isset($pedido['pagador_nome']) ? $pedido['pagador_nome'] : null,
            'Pedido {pedido.codigo} criado',
            array('pedido' => $pedido),
            'pedido',
            isset($pedido['id']) ? $pedido['id'] : null,
            $actorUserId,
            $ipAddress,
            $userAgent
        );
    }

    public function comprovanteEnviado(array $pedido, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->sendTemplate(
            'email.comprovante_enviado',
            'comprovante_enviado',
            isset($pedido['pagador_email']) ? $pedido['pagador_email'] : null,
            isset($pedido['pagador_nome']) ? $pedido['pagador_nome'] : null,
            'Comprovante PIX enviado - {pedido.codigo}',
            array('pedido' => $pedido),
            'pedido',
            isset($pedido['id']) ? $pedido['id'] : null,
            $actorUserId,
            $ipAddress,
            $userAgent
        );
    }

    public function pendencia(array $pedido, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->sendTemplate(
            'email.pendencia',
            'pendencia',
            isset($pedido['pagador_email']) ? $pedido['pagador_email'] : null,
            isset($pedido['pagador_nome']) ? $pedido['pagador_nome'] : null,
            'Pedido com pendência - {pedido.codigo}',
            array('pedido' => $pedido, 'observacao' => $observacao),
            'pedido',
            isset($pedido['id']) ? $pedido['id'] : null,
            $actorUserId,
            $ipAddress,
            $userAgent
        );
    }

    public function pedidoAprovado(array $pedido, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->sendTemplate(
            'email.pedido_aprovado',
            'pedido_aprovado',
            isset($pedido['pagador_email']) ? $pedido['pagador_email'] : null,
            isset($pedido['pagador_nome']) ? $pedido['pagador_nome'] : null,
            'Pedido aprovado - {pedido.codigo}',
            array('pedido' => $pedido, 'observacao' => $observacao),
            'pedido',
            isset($pedido['id']) ? $pedido['id'] : null,
            $actorUserId,
            $ipAddress,
            $userAgent
        );
    }

    public function pedidoExcluidoInatividade(array $pedido, array $cursos = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->sendTemplate(
            'email.pedido_excluido_inatividade',
            'pedido_excluido_inatividade',
            isset($pedido['pagador_email']) ? $pedido['pagador_email'] : null,
            isset($pedido['pagador_nome']) ? $pedido['pagador_nome'] : null,
            'Pedido excluído por inatividade - {pedido.codigo}',
            array(
                'pedido' => $pedido,
                'cursos' => $cursos,
            ),
            'pedido',
            isset($pedido['id']) ? $pedido['id'] : null,
            $actorUserId,
            $ipAddress,
            $userAgent
        );
    }

    public function cursoProximo(array $inscricao, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->sendTemplate(
            'email.curso_proximo',
            'curso_proximo',
            isset($inscricao['pagador_email']) ? $inscricao['pagador_email'] : null,
            isset($inscricao['pagador_nome']) ? $inscricao['pagador_nome'] : null,
            'Seu curso está próximo',
            array('inscricao' => $inscricao),
            'inscricao',
            isset($inscricao['id']) ? $inscricao['id'] : null,
            $actorUserId,
            $ipAddress,
            $userAgent
        );
    }

    public function concluido(array $inscricao, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->sendTemplate(
            'email.concluido',
            'concluido',
            isset($inscricao['pagador_email']) ? $inscricao['pagador_email'] : null,
            isset($inscricao['pagador_nome']) ? $inscricao['pagador_nome'] : null,
            'Curso concluído',
            array('inscricao' => $inscricao),
            'inscricao',
            isset($inscricao['id']) ? $inscricao['id'] : null,
            $actorUserId,
            $ipAddress,
            $userAgent
        );
    }

    public function certificadoDisponivel(array $inscricao, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->sendTemplate(
            'email.certificado_disponivel',
            'certificado_disponivel',
            isset($inscricao['pagador_email']) ? $inscricao['pagador_email'] : null,
            isset($inscricao['pagador_nome']) ? $inscricao['pagador_nome'] : null,
            'Certificado disponível',
            array('inscricao' => $inscricao),
            'inscricao',
            isset($inscricao['id']) ? $inscricao['id'] : null,
            $actorUserId,
            $ipAddress,
            $userAgent
        );
    }

    public function sendTemplate(
        $evento,
        $template,
        $destinatarioEmail,
        $destinatarioNome,
        $assunto,
        array $data = array(),
        $entidadeTipo = null,
        $entidadeId = null,
        $actorUserId = null,
        $ipAddress = null,
        $userAgent = null,
        $forceSend = false
    ) {
        if (!$destinatarioEmail) {
            return array('ok' => false, 'message' => 'Destinatário inválido.');
        }

        $modelo = $this->modeloService->findByEvento($evento);
        if ($modelo && !$forceSend && empty($modelo['ativo'])) {
            if (!empty($modelo['id'])) {
                $this->auditService->record(
                    'emails.modelo.desativado',
                    'email_modelo',
                    (int) $modelo['id'],
                    array(
                        'evento' => $evento,
                        'template' => $template,
                    ),
                    $actorUserId,
                    $ipAddress,
                    $userAgent
                );
            }

            Logger::info('emails.modelo.desativado', array('evento' => $evento, 'template' => $template));
            return array('ok' => true, 'skipped' => true, 'message' => 'Envio automatizado desativado para este modelo.');
        }

        $contextoRenderizacao = $this->modeloService->buildContext($data);
        $assuntoFinal = $assunto;
        $rendered = null;
        if ($modelo) {
            if (!empty($modelo['assunto'])) {
                $assuntoFinal = $modelo['assunto'];
            }

            if (!empty($modelo['corpo_html'])) {
                $rendered = $modelo['corpo_html'];
            }
        }

        $assuntoFinal = $this->modeloService->renderPlaceholders($assuntoFinal, $contextoRenderizacao);
        if ($rendered !== null && trim((string) $rendered) !== '') {
            $rendered = $this->modeloService->renderPlaceholders($rendered, $contextoRenderizacao);
        } else {
            $rendered = View::render($template, $data, false, 'emails');
        }
        $config = $this->configuration();

        $payload = array(
            'usuario_id' => $actorUserId,
            'entidade_tipo' => $entidadeTipo,
            'entidade_id' => $entidadeId,
            'evento' => $evento,
            'template' => $template,
            'destinatario_email' => strtolower(trim((string) $destinatarioEmail)),
            'destinatario_nome' => $destinatarioNome,
            'assunto' => $assuntoFinal,
            'contexto_json' => json_encode($data),
            'status' => 'pendente',
        );

        $emailId = $this->emailModel->create($payload);
        $this->logRuntimeDiagnostics($config);

        $this->auditService->record(
            'emails.fila.criada',
            'email',
            $emailId,
            array(
                'evento' => $evento,
                'template' => $template,
                'destinatario_email' => $payload['destinatario_email'],
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

            Logger::info('emails.fila.criada', array('email_id' => $emailId, 'evento' => $evento));

        if (empty($config['enabled']) || empty($config['host'])) {
            $erro = 'Configuração SMTP indisponível.';
            $this->emailModel->markFailed($emailId, $erro);
            $this->auditService->record(
                'emails.falhou',
                'email',
                $emailId,
                array('erro' => $erro),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::error('emails.falhou', array('email_id' => $emailId, 'erro' => $erro));
            return array('ok' => false, 'message' => $erro, 'email_id' => $emailId);
        }

        try {
            $response = $this->sendSmtpMessage($config, array(
                'from_email' => $config['from_email'],
                'from_name' => $config['from_name'],
                'reply_to' => $config['reply_to'],
                'to_email' => $payload['destinatario_email'],
                'to_name' => $payload['destinatario_nome'],
                'subject' => $assuntoFinal,
                'html' => $rendered,
            ));

            $this->emailModel->markSent($emailId, $response);

            $this->auditService->record(
                'emails.enviado',
                'email',
                $emailId,
                array(
                    'evento' => $evento,
                    'template' => $template,
                    'resposta' => $response,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('emails.enviado', array('email_id' => $emailId, 'evento' => $evento));

            return array('ok' => true, 'email_id' => $emailId, 'response' => $response);
        } catch (Exception $exception) {
            $this->emailModel->markFailed($emailId, $exception->getMessage());

            $this->auditService->record(
                'emails.falhou',
                'email',
                $emailId,
                array('erro' => $exception->getMessage()),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::error('emails.falhou', array(
                'email_id' => $emailId,
                'evento' => $evento,
                'erro' => $exception->getMessage(),
            ));

            return array('ok' => false, 'message' => $exception->getMessage(), 'email_id' => $emailId);
        }
    }

    public function sendCustomHtml(
        $evento,
        $template,
        $destinatarioEmail,
        $destinatarioNome,
        $assunto,
        $html,
        array $data = array(),
        $entidadeTipo = null,
        $entidadeId = null,
        $actorUserId = null,
        $ipAddress = null,
        $userAgent = null
    ) {
        if (!$destinatarioEmail) {
            return array('ok' => false, 'message' => 'Destinatário inválido.');
        }

        $contextoRenderizacao = $this->modeloService->buildContext($data);
        $assuntoFinal = $this->modeloService->renderPlaceholders($assunto, $contextoRenderizacao);
        $rendered = trim((string) $html) !== ''
            ? $this->modeloService->renderPlaceholders($html, $contextoRenderizacao)
            : View::render($template, $data, false, 'emails');

        $config = $this->configuration();
        $payload = array(
            'usuario_id' => $actorUserId,
            'entidade_tipo' => $entidadeTipo,
            'entidade_id' => $entidadeId,
            'evento' => $evento,
            'template' => $template,
            'destinatario_email' => strtolower(trim((string) $destinatarioEmail)),
            'destinatario_nome' => $destinatarioNome,
            'assunto' => $assuntoFinal,
            'contexto_json' => json_encode($data),
            'status' => 'pendente',
        );

        $emailId = $this->emailModel->create($payload);
        $this->logRuntimeDiagnostics($config);

        $this->auditService->record(
            'emails.fila.criada',
            'email',
            $emailId,
            array(
                'evento' => $evento,
                'template' => $template,
                'destinatario_email' => $payload['destinatario_email'],
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('emails.fila.criada', array('email_id' => $emailId, 'evento' => $evento));

        if (empty($config['enabled']) || empty($config['host'])) {
            $erro = 'Configuração SMTP indisponível.';
            $this->emailModel->markFailed($emailId, $erro);
            $this->auditService->record(
                'emails.falhou',
                'email',
                $emailId,
                array('erro' => $erro),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::error('emails.falhou', array('email_id' => $emailId, 'erro' => $erro));
            return array('ok' => false, 'message' => $erro, 'email_id' => $emailId);
        }

        try {
            $response = $this->sendSmtpMessage($config, array(
                'from_email' => $config['from_email'],
                'from_name' => $config['from_name'],
                'reply_to' => $config['reply_to'],
                'to_email' => $payload['destinatario_email'],
                'to_name' => $payload['destinatario_nome'],
                'subject' => $assuntoFinal,
                'html' => $rendered,
            ));

            $this->emailModel->markSent($emailId, $response);

            $this->auditService->record(
                'emails.enviado',
                'email',
                $emailId,
                array(
                    'evento' => $evento,
                    'template' => $template,
                    'resposta' => $response,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('emails.enviado', array('email_id' => $emailId, 'evento' => $evento));

            return array('ok' => true, 'email_id' => $emailId, 'response' => $response);
        } catch (Exception $exception) {
            $this->emailModel->markFailed($emailId, $exception->getMessage());

            $this->auditService->record(
                'emails.falhou',
                'email',
                $emailId,
                array('erro' => $exception->getMessage()),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::error('emails.falhou', array(
                'email_id' => $emailId,
                'evento' => $evento,
                'erro' => $exception->getMessage(),
            ));

            return array('ok' => false, 'message' => $exception->getMessage(), 'email_id' => $emailId);
        }
    }

    public function resend($emailId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $email = $this->emailModel->findById($emailId);
        if (!$email) {
            return array('ok' => false, 'message' => 'E-mail não encontrado.');
        }

        $contexto = array();
        if (!empty($email['contexto_json'])) {
            $contexto = json_decode($email['contexto_json'], true);
            if (!is_array($contexto)) {
                $contexto = array();
            }
        }

        return $this->sendTemplate(
            $email['evento'],
            $email['template'],
            $email['destinatario_email'],
            $email['destinatario_nome'],
            $email['assunto'],
            $contexto,
            $email['entidade_tipo'],
            $email['entidade_id'],
            $actorUserId,
            $ipAddress,
            $userAgent,
            true
        );
    }

    private function sendSmtpMessage(array $config, array $email)
    {
        $attemptedFallback = false;
        $lastException = null;

        foreach ($this->smtpEncryptionAttempts($config['encryption']) as $encryptionMode) {
            try {
                return $this->sendSmtpMessageWithMode($config, $email, $encryptionMode);
            } catch (Exception $exception) {
                $lastException = $exception;
                $allowPlaintextFallback = !empty($config['allow_plaintext_fallback']);
                if (!$this->shouldFallbackToPlain($encryptionMode, $exception) || $attemptedFallback) {
                    throw $exception;
                }

                if (!$allowPlaintextFallback) {
                    Logger::info('emails.smtp.tls.falhou.sem_fallback', array(
                        'host' => $config['host'],
                        'port' => $config['port'],
                        'encryption' => $encryptionMode,
                        'erro' => $exception->getMessage(),
                    ));
                    throw $exception;
                }

                $attemptedFallback = true;
                Logger::info('emails.smtp.tls.falhou.fallback', array(
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'encryption' => $encryptionMode,
                    'erro' => $exception->getMessage(),
                ));
            }
        }

        if ($lastException instanceof Exception) {
            throw $lastException;
        }

        throw new Exception('Falha ao enviar email.');
    }

    private function logRuntimeDiagnostics(array $config)
    {
        if ($this->runtimeDiagnosticsLogged) {
            return;
        }

        $this->runtimeDiagnosticsLogged = true;

        Logger::info('emails.smtp.runtime', array(
            'php_version' => PHP_VERSION,
            'app_env' => getenv('APP_ENV') !== false ? getenv('APP_ENV') : (require BASE_PATH . '/config/app.php')['env'],
            'openssl_loaded' => extension_loaded('openssl'),
            'stream_socket_enable_crypto' => function_exists('stream_socket_enable_crypto'),
            'host' => isset($config['host']) ? $config['host'] : null,
            'port' => isset($config['port']) ? (int) $config['port'] : null,
            'encryption' => isset($config['encryption']) ? $config['encryption'] : null,
            'allow_plaintext_fallback' => !empty($config['allow_plaintext_fallback']),
            'username_present' => !empty($config['username']),
            'from_email' => isset($config['from_email']) ? $config['from_email'] : null,
            'reply_to' => isset($config['reply_to']) ? $config['reply_to'] : null,
        ));
    }

    private function sendSmtpMessageWithMode(array $config, array $email, $encryptionMode)
    {
        $host = trim((string) $config['host']);
        $port = (int) $config['port'];
        $encryption = strtolower((string) $encryptionMode);
        $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

        $socket = null;
        $erroConexao = null;
        for ($tentativa = 1; $tentativa <= 2; $tentativa++) {
            $socket = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
            if ($socket) {
                break;
            }

            $erroConexao = $errstr;
            if ($tentativa === 1) {
                Logger::info('emails.smtp.conexao.retentativa', array(
                    'host' => $host,
                    'port' => $port,
                    'encryption' => $encryptionMode,
                    'erro' => $errstr,
                ));
                usleep(350000);
            }
        }

        if (!$socket) {
            throw new Exception('Falha na conexao SMTP: ' . $erroConexao);
        }

        $this->smtpRead($socket, array(220));
        $this->smtpCommand($socket, 'EHLO ' . $this->hostname(), array(250));

        if ($encryption === 'tls') {
            $this->smtpCommand($socket, 'STARTTLS', array(220));
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('Não foi possível iniciar TLS.');
            }

            $this->smtpCommand($socket, 'EHLO ' . $this->hostname(), array(250));
        }

        $username = trim((string) $config['username']);
        if ($username !== '') {
            $this->smtpCommand($socket, 'AUTH LOGIN', array(334));
            $this->smtpCommand($socket, base64_encode($username), array(334));
            $this->smtpCommand($socket, base64_encode((string) $config['password']), array(235));
        }

        $from = $this->formatAddress($email['from_email'], $email['from_name']);
        $to = $this->formatAddress($email['to_email'], $email['to_name']);
        $subject = $this->encodeHeader($email['subject']);

        $this->smtpCommand($socket, 'MAIL FROM:<' . $email['from_email'] . '>', array(250));
        $this->smtpCommand($socket, 'RCPT TO:<' . $email['to_email'] . '>', array(250, 251));
        $this->smtpCommand($socket, 'DATA', array(354));

        $message = array();
        $message[] = 'From: ' . $from;
        $message[] = 'To: ' . $to;
        $message[] = 'Subject: ' . $subject;
        if (!empty($email['reply_to'])) {
            $message[] = 'Reply-To: ' . $this->formatAddress($email['reply_to'], null);
        }
        $message[] = 'MIME-Version: 1.0';
        $message[] = 'Content-Type: text/html; charset=UTF-8';
        $message[] = 'Content-Transfer-Encoding: 8bit';
        $message[] = '';
        $message[] = $email['html'];

        fwrite($socket, implode("\r\n", $message) . "\r\n.\r\n");
        $response = $this->smtpRead($socket, array(250));

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return trim($response);
    }

    private function smtpEncryptionAttempts($encryption)
    {
        $encryption = strtolower(trim((string) $encryption));

        if ($encryption === 'tls') {
            return array('tls', 'nenhuma');
        }

        return array($encryption === '' ? 'nenhuma' : $encryption);
    }

    private function shouldFallbackToPlain($encryption, Exception $exception)
    {
        if ($encryption !== 'tls') {
            return false;
        }

        $message = strtolower($exception->getMessage());
        return strpos($message, 'tls') !== false
            || strpos($message, 'crypto') !== false
            || strpos($message, 'openssl') !== false
            || strpos($message, 'starttls') !== false;
    }

    private function resolveSenderEmail($primary, $secondary = null, array $fallbacks = array())
    {
        $candidatos = array(
            $primary,
            $secondary,
            isset($fallbacks['from_email']) ? $fallbacks['from_email'] : null,
            isset($fallbacks['reply_to']) ? $fallbacks['reply_to'] : null,
            isset($fallbacks['username']) ? $fallbacks['username'] : null,
            'no-reply@polorainbow.com.br',
        );

        foreach ($candidatos as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            if (filter_var($candidate, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            if (preg_match('/@localhost$/i', $candidate) === 1) {
                continue;
            }

            return $candidate;
        }

        return 'no-reply@polorainbow.com.br';
    }

    private function smtpCommand($socket, $command, array $expectedCodes)
    {
        fwrite($socket, $command . "\r\n");
        return $this->smtpRead($socket, $expectedCodes);
    }

    private function smtpRead($socket, array $expectedCodes = array())
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new Exception('Resposta vazia do servidor SMTP.');
        }

        if (!empty($expectedCodes)) {
            $code = (int) substr($response, 0, 3);
            if (!in_array($code, $expectedCodes, true)) {
                throw new Exception('Falha SMTP: ' . trim($response));
            }
        }

        return trim($response);
    }

    private function hostname()
    {
        $host = parse_url(Helpers::url('/'), PHP_URL_HOST);
        return $host ? $host : 'localhost';
    }

    private function formatAddress($email, $name = null)
    {
        $email = trim((string) $email);
        if ($name === null || trim((string) $name) === '') {
            return $email;
        }

        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}





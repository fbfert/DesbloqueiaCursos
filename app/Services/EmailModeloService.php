<?php

namespace App\Services;

use App\Core\Helpers;
use App\Core\Logger;
use App\Models\EmailModelo;
use App\Support\EmailHtmlDocument;
use App\Support\EmailPlaceholders;

class EmailModeloService
{
    private $modeloModel;
    private $globalConfigService;
    private $auditService;

    public function __construct()
    {
        $this->modeloModel = new EmailModelo();
        $this->globalConfigService = new ConfiguracaoGlobalService();
        $this->auditService = new AuditService();
    }

    public function listar($busca = '')
    {
        $defaults = $this->allDefaults();

        try {
            $modelos = array();
            foreach ($defaults as $evento => $default) {
                $stored = $this->modeloModel->findByEvento($evento);
                $modelos[] = $this->mergeWithDefault($stored, $default);
            }

            foreach ($this->modeloModel->allForAdmin() as $stored) {
                if (isset($defaults[$stored['evento']])) {
                    continue;
                }

                $modelos[] = $this->normalizeModel($stored);
            }

            $orderMap = array_flip(array_keys($defaults));
            usort($modelos, function (array $left, array $right) use ($orderMap) {
                $leftOrder = isset($orderMap[$left['evento']]) ? (int) $orderMap[$left['evento']] : 9999;
                $rightOrder = isset($orderMap[$right['evento']]) ? (int) $orderMap[$right['evento']] : 9999;

                if ($leftOrder !== $rightOrder) {
                    return $leftOrder <=> $rightOrder;
                }

                $leftName = isset($left['nome']) ? (string) $left['nome'] : '';
                $rightName = isset($right['nome']) ? (string) $right['nome'] : '';
                return strcmp($leftName, $rightName);
            });

            return $this->filtrarModelos($modelos, $busca);
        } catch (\Throwable $exception) {
            Logger::error('emails.modelo.listar.erro', array(
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));

            $fallback = array();
            foreach ($defaults as $default) {
                $fallback[] = $this->normalizeModel($default);
            }

            return $this->filtrarModelos($fallback, $busca);
        }
    }

    public function formData($id = null, $evento = null)
    {
        try {
            if ($id) {
                $modelo = $this->modeloModel->findById($id);
                if ($modelo) {
                    return $this->normalizeModel($this->mergeWithDefault($modelo, $this->defaultDefinition($modelo['evento'])));
                }
            }

            $evento = trim((string) $evento);
            if ($evento !== '') {
                $default = $this->defaultDefinition($evento);
                if ($default) {
                    return $this->normalizeModel($default);
                }
            }

            return array(
                'id' => 0,
                'evento' => '',
                'template' => '',
                'nome' => '',
                'assunto' => '',
                'corpo_html' => '',
                'gatilho_descricao' => '',
                'variaveis_json' => '[]',
                'variaveis_disponiveis' => '',
                'ativo' => 1,
                'editavel' => 1,
                'created_at' => null,
                'updated_at' => null,
                'deleted_at' => null,
                'is_default' => false,
            );
        } catch (\Throwable $exception) {
            Logger::error('emails.modelo.form_data.erro', array(
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));

            $default = $evento ? $this->defaultDefinition($evento) : null;
            if ($default) {
                return $this->normalizeModel($default);
            }

            return array(
                'id' => 0,
                'evento' => '',
                'template' => '',
                'nome' => '',
                'assunto' => '',
                'corpo_html' => '',
                'gatilho_descricao' => '',
                'variaveis_json' => '[]',
                'variaveis_disponiveis' => '',
                'ativo' => 1,
                'editavel' => 1,
                'created_at' => null,
                'updated_at' => null,
                'deleted_at' => null,
                'is_default' => false,
            );
        }
    }

    public function save(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        try {
            $id = !empty($data['id']) ? (int) $data['id'] : 0;
            $stored = $id > 0 ? $this->modeloModel->findById($id) : null;

            $evento = $stored ? (string) $stored['evento'] : trim((string) ($data['evento'] ?? ''));
            $template = $stored ? (string) $stored['template'] : trim((string) ($data['template'] ?? ''));
            $nome = trim((string) ($data['nome'] ?? ''));
            $assunto = trim((string) ($data['assunto'] ?? ''));
            $corpoHtml = isset($data['corpo_html']) ? EmailHtmlDocument::sanitize(trim((string) $data['corpo_html'])) : '';
            $gatilhoDescricao = trim((string) ($data['gatilho_descricao'] ?? ''));
            $variaveisJson = $this->normalizeVariablesJson(isset($data['variaveis_disponiveis']) ? $data['variaveis_disponiveis'] : (isset($data['variaveis_json']) ? $data['variaveis_json'] : null));
            $ativo = !empty($data['ativo']) ? 1 : 0;
            $editavel = isset($data['editavel']) ? !empty($data['editavel']) : true;

            $errors = array();
            if ($evento === '') {
                $errors['evento'] = 'Informe o evento do e-mail.';
            }

            if ($template === '') {
                $errors['template'] = 'Informe o template do e-mail.';
            }

            if ($nome === '') {
                $errors['nome'] = 'Informe o nome do modelo.';
            }

            if ($assunto === '') {
                $errors['assunto'] = 'Informe o assunto do e-mail.';
            }

            if ($corpoHtml === '') {
                $errors['corpo_html'] = 'Informe o corpo do e-mail.';
            }

            if ($evento !== '') {
                $exists = $this->modeloModel->findByEvento($evento);
                if ($exists && (int) $exists['id'] !== $id) {
                    $errors['evento'] = 'Já existe um modelo com este evento.';
                }
            }

            // Bloqueio de placeholders estruturalmente inválidos (corrompidos/desbalanceados).
            $problemasPlaceholder = array_merge(
                EmailPlaceholders::structuralIssues($corpoHtml),
                EmailPlaceholders::structuralIssues($assunto)
            );
            if (!empty($problemasPlaceholder)) {
                $problemasPlaceholder = array_values(array_unique($problemasPlaceholder));
                $errors['corpo_html'] = 'Placeholders inválidos no conteúdo: ' . implode(' ', $problemasPlaceholder) . ' Corrija antes de salvar.';
                Logger::warning('emails.modelo.placeholder_invalido', array(
                    'modelo_evento' => $evento,
                    'problemas' => $problemasPlaceholder,
                ));
            }

            if ($errors) {
                return array('ok' => false, 'errors' => $errors);
            }

            $payload = array(
                'evento' => $evento,
                'template' => $template,
                'nome' => $nome,
                'assunto' => $assunto,
                'corpo_html' => $corpoHtml,
                'gatilho_descricao' => $gatilhoDescricao !== '' ? $gatilhoDescricao : null,
                'variaveis_json' => $variaveisJson,
                'ativo' => $ativo,
                'editavel' => $editavel ? 1 : 0,
            );

            if ($id > 0 && $stored) {
                $anterior = $this->normalizeModel($this->mergeWithDefault($stored, $this->defaultDefinition($stored['evento'])));
                $this->modeloModel->update($id, $payload);
                $acao = 'emails.modelo.atualizado';
            } elseif ($id > 0) {
                return array('ok' => false, 'errors' => array('id' => 'Modelo de e-mail não encontrado.'));
            } else {
                $id = $this->modeloModel->create($payload);
                $anterior = null;
                $acao = 'emails.modelo.criado';
            }

            $novo = $this->normalizeModel($this->mergeWithDefault($this->modeloModel->findById($id), $this->defaultDefinition($payload['evento'])));

            $this->auditService->record(
                $acao,
                'email_modelo',
                $id,
                array(
                    'anterior' => isset($anterior) ? $anterior : null,
                    'novo' => $novo,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($acao, array('email_modelo_id' => $id, 'evento' => $payload['evento']));

            return array('ok' => true, 'id' => $id);
        } catch (\Throwable $exception) {
            Logger::error('emails.modelo.salvar.erro', array(
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));

            return array('ok' => false, 'errors' => array('geral' => 'Não foi possível salvar o modelo de e-mail no momento.'));
        }
    }

    public function duplicar(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        try {
            $id = !empty($data['id']) ? (int) $data['id'] : 0;
            if ($id <= 0) {
                return array('ok' => false, 'errors' => array('Modelo de e-mail não encontrado.'));
            }

            $stored = $this->modeloModel->findById($id);
            if (!$stored) {
                return array('ok' => false, 'errors' => array('Modelo de e-mail não encontrado.'));
            }

            $eventoBase = trim((string) ($data['evento'] ?? $stored['evento']));
            $templateBase = trim((string) ($data['template'] ?? $stored['template']));
            $payload = array(
                'evento' => $this->suffixUnique($eventoBase, 'evento'),
                'template' => $this->suffixUnique($templateBase, 'template'),
                'nome' => $this->nomeDaCopia((string) ($data['nome'] ?? $stored['nome'])),
                'assunto' => trim((string) ($data['assunto'] ?? $stored['assunto'])),
                'corpo_html' => trim((string) ($data['corpo_html'] ?? $stored['corpo_html'])),
                'gatilho_descricao' => trim((string) ($data['gatilho_descricao'] ?? ($stored['gatilho_descricao'] ?? ''))),
                'variaveis_json' => $this->normalizeVariablesJson(isset($data['variaveis_disponiveis']) ? $data['variaveis_disponiveis'] : (isset($stored['variaveis_json']) ? $stored['variaveis_json'] : '')),
                'ativo' => 0,
                'editavel' => 1,
            );

            return $this->save($payload, $actorUserId, $ipAddress, $userAgent);
        } catch (\Throwable $exception) {
            Logger::error('emails.modelo.copiar.erro', array(
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));

            return array('ok' => false, 'errors' => array('Não foi possível criar a cópia do modelo de e-mail.'));
        }
    }

    public function alternarAtivo($id, $ativo, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        try {
            $modelo = $this->modeloModel->findById($id);
            if (!$modelo) {
                return array('ok' => false, 'message' => 'Modelo de e-mail não encontrado.');
            }

            $this->modeloModel->updateActive($id, $ativo);

            $this->auditService->record(
                'emails.modelo.status_atualizado',
                'email_modelo',
                $id,
                array(
                    'ativo_anterior' => (int) $modelo['ativo'],
                    'ativo_novo' => !empty($ativo) ? 1 : 0,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('emails.modelo.status_atualizado', array('email_modelo_id' => $id, 'ativo' => !empty($ativo) ? 1 : 0));

            return array('ok' => true, 'id' => $id);
        } catch (\Throwable $exception) {
            Logger::error('emails.modelo.status.erro', array(
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));

            return array('ok' => false, 'message' => 'Não foi possível atualizar o status do modelo.');
        }
    }

    public function restaurarPadrao($id, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        try {
            $modelo = $this->modeloModel->findById($id);
            if (!$modelo) {
                return array('ok' => false, 'message' => 'Modelo de e-mail não encontrado.');
            }

            $default = $this->defaultDefinition($modelo['evento']);
            if (!$default) {
                return array('ok' => false, 'message' => 'Não existe conteúdo padrão para este modelo.');
            }

            $this->modeloModel->restoreDefault($id, $default);

            $this->auditService->record(
                'emails.modelo.restaurado_padrao',
                'email_modelo',
                $id,
                array(
                    'evento' => $modelo['evento'],
                    'template' => $modelo['template'],
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('emails.modelo.restaurado_padrao', array('email_modelo_id' => $id, 'evento' => $modelo['evento']));

            return array('ok' => true, 'id' => $id);
        } catch (\Throwable $exception) {
            Logger::error('emails.modelo.restaurar.erro', array(
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));

            return array('ok' => false, 'message' => 'Não foi possível restaurar o conteúdo padrão.');
        }
    }

    public function findByEvento($evento)
    {
        $evento = trim((string) $evento);
        if ($evento === '') {
            return null;
        }

        $default = $this->defaultDefinition($evento);
        $modelo = $this->modeloModel->findByEvento($evento);

        if ($modelo) {
            return $this->normalizeModel($this->mergeWithDefault($modelo, $default));
        }

        return $default ? $this->normalizeModel($default) : null;
    }

    public function isDefaultEvento($evento)
    {
        $evento = trim((string) $evento);
        if ($evento === '') {
            return false;
        }

        $defaults = $this->allDefaults();
        return isset($defaults[$evento]);
    }

    public function renderPlaceholders($content, array $context = array())
    {
        $content = (string) $content;
        if (trim($content) === '') {
            return '';
        }

        $flat = $this->resolverContextoFlat($context);

        return preg_replace_callback('/\{\{([a-zA-Z0-9_.]+)\}\}|\{([a-zA-Z0-9_.]+)\}/', function ($matches) use ($flat) {
            $key = !empty($matches[1]) ? $matches[1] : $matches[2];
            if (!array_key_exists($key, $flat)) {
                return '';
            }

            return $flat[$key];
        }, $content);
    }

    public function buildContext(array $data = array())
    {
        $institucional = $this->globalConfigService->institucional();
        $emailDefaults = $this->globalConfigService->emailDefaults();

        $sistema = array(
            'nome' => !empty($institucional['nome_fantasia']) ? $institucional['nome_fantasia'] : 'Desbloqueia Cursos',
            'login_url' => Helpers::url('v2/login'),
            'meus_cursos_url' => Helpers::url('v2/aluno/'),
            'area_curso_url' => Helpers::url('v2/aluno/'),
            'home_url' => Helpers::url('v2/'),
            'email_institucional' => !empty($institucional['email_institucional']) ? $institucional['email_institucional'] : null,
            'email_suporte' => !empty($institucional['email_suporte']) ? $institucional['email_suporte'] : null,
            'from_email' => !empty($emailDefaults['from_email']) ? $emailDefaults['from_email'] : null,
        );

        if (isset($data['sistema']) && is_array($data['sistema'])) {
            $sistema = array_merge($sistema, $data['sistema']);
        }

        $data['sistema'] = $sistema;
        $data['usuario'] = $this->resolverUsuarioContexto($data);
        $certificadoUrlDownload = $this->resolverCertificadoUrlDownload($data);
        if ($certificadoUrlDownload !== '') {
            $data['certificado_url_download'] = $certificadoUrlDownload;
        }

        return $data;
    }

    private function allDefaults()
    {
        return array_merge($this->defaults(), $this->pedidoRecuperacaoDefaults());
    }

    private function defaults()
    {
        return array(
            'email.welcome' => array(
                'evento' => 'email.welcome',
                'template' => 'welcome',
                'nome' => 'Boas-vindas',
                'assunto' => 'Bem-vindo ao {sistema.nome}',
                'corpo_html' => <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Bem-vindo ao {sistema.nome}</h1>
            <p>Olá, {usuario.nome}.</p>
            <p>Sua conta foi criada com sucesso. Você já pode acessar o portal com o e-mail {usuario.email}.</p>
            <p><a href="{sistema.login_url}">Entrar no portal</a></p>
        </div>
    </div>
</body>
</html>
HTML,
                'gatilho_descricao' => 'Enviado quando um novo usuário conclui o cadastro no sistema, a partir de AuthService::register.',
                'variaveis_json' => json_encode(array('{usuario.nome}', '{usuario.email}', '{sistema.nome}', '{sistema.login_url}'), JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'email.password_reset' => array(
                'evento' => 'email.password_reset',
                'template' => 'password_reset',
                'nome' => 'Recuperação de senha',
                'assunto' => 'Recuperação de senha',
                'corpo_html' => <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperação de senha</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Recuperação de senha</h1>
            <p>Olá, {usuario.nome}.</p>
            <p>Use o link abaixo para redefinir sua senha. O token expira em 60 minutos.</p>
            <p><a href="{reset_url}">Redefinir senha</a></p>
            <p style="word-break:break-all;">Token: {token}</p>
        </div>
    </div>
</body>
</html>
HTML,
                'gatilho_descricao' => 'Enviado quando o usuário solicita redefinição de senha.',
                'variaveis_json' => json_encode(array('{usuario.nome}', '{reset_url}', '{token}'), JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'email.pedido_criado' => array(
                'evento' => 'email.pedido_criado',
                'template' => 'pedido_criado',
                'nome' => 'Pedido criado',
                'assunto' => 'Pedido {pedido.codigo} criado',
                'corpo_html' => <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido criado</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Pedido criado</h1>
            <p>Seu pedido <strong>{pedido.codigo}</strong> foi criado.</p>
            <p>Total: {pedido.total}</p>
            <p>Agora você pode concluir o fluxo com o comprovante PIX.</p>
        </div>
    </div>
</body>
</html>
HTML,
                'gatilho_descricao' => 'Enviado após a criação do pedido no checkout, em PedidoService.',
                'variaveis_json' => json_encode(array('{pedido.codigo}', '{pedido.total}'), JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'email.comprovante_enviado' => array(
                'evento' => 'email.comprovante_enviado',
                'template' => 'comprovante_enviado',
                'nome' => 'Comprovante PIX enviado',
                'assunto' => 'Comprovante PIX enviado - {pedido.codigo}',
                'corpo_html' => <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante enviado</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Comprovante enviado</h1>
            <p>Recebemos o comprovante do pedido <strong>{pedido.codigo}</strong>.</p>
            <p>Você pode acompanhar a situação em <a href="{sistema.meus_cursos_url}">Meus cursos</a>.</p>
        </div>
    </div>
</body>
</html>
HTML,
                'gatilho_descricao' => 'Enviado quando o comprovante PIX é anexado ou reenviado.',
                'variaveis_json' => json_encode(array('{pedido.codigo}', '{sistema.meus_cursos_url}'), JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'email.pedido_aprovado' => array(
                'evento' => 'email.pedido_aprovado',
                'template' => 'pedido_aprovado',
                'nome' => 'Pedido aprovado',
                'assunto' => 'Pedido aprovado - {pedido.codigo}',
                'corpo_html' => <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido aprovado</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Pedido aprovado</h1>
            <p>Seu pedido <strong>{pedido.codigo}</strong> foi aprovado.</p>
            <p>Se houver inscrição vinculada, ela seguirá para o andamento normal do curso.</p>
        </div>
    </div>
</body>
</html>
HTML,
                'gatilho_descricao' => 'Enviado quando o pedido ou comprovante é aprovado.',
                'variaveis_json' => json_encode(array('{pedido.codigo}', '{observacao}'), JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'email.pedido_excluido_inatividade' => array(
                'evento' => 'email.pedido_excluido_inatividade',
                'template' => 'pedido_excluido_inatividade',
                'nome' => 'Pedido excluído por inatividade',
                'assunto' => 'Pedido excluído por inatividade',
                'corpo_html' => <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido excluído por inatividade</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Pedido excluído por inatividade</h1>
            <p>O pedido <strong>{{pedido_codigo}}</strong> foi excluído por inatividade.</p>
            <p>Curso: {{curso_nome}}<br>
            Valor não pago: {{valor_nao_pago}}</p>
            <p>Se você acredita que isso ocorreu por engano, entre em contato com a equipe de atendimento.</p>
        </div>
    </div>
</body>
</html>
HTML,
                'gatilho_descricao' => 'Enviado quando um pedido é excluído por inatividade na rotina administrativa de limpeza.',
                'variaveis_json' => json_encode(array('{{pedido_codigo}}', '{{curso_nome}}', '{{valor_nao_pago}}', '{{valor_total}}', '{{valor_pago}}', '{{aluno_nome}}', '{{aluno_email}}'), JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'email.presente_concedido' => array(
                'evento' => 'email.presente_concedido',
                'template' => 'presente_concedido',
                'nome' => 'Curso recebido como presente',
                'assunto' => 'Você ganhou acesso a um curso na Desbloqueia Cursos',
                'corpo_html' => <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curso recebido como presente</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Você ganhou acesso a um curso</h1>
            <p>Olá, {nome_usuario}!</p>
            <p>Você recebeu como presente o acesso ao curso <strong>{nome_curso}</strong>.</p>
            <p>{nome_turma}</p>
            <p>Prazo de acesso: <strong>{prazo_acesso}</strong></p>
            <p><a href="{link_meus_cursos}">Acessar meus cursos</a></p>
        </div>
    </div>
</body>
</html>
HTML,
                'gatilho_descricao' => 'Enviado quando um presente promocional é concedido a um usuário.',
                'variaveis_json' => json_encode(array('{nome_usuario}', '{nome_curso}', '{nome_turma}', '{prazo_acesso}', '{link_meus_cursos}', '{nome_plataforma}'), JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'email.pendencia' => array(
                'evento' => 'email.pendencia',
                'template' => 'pendencia',
                'nome' => 'Pedido ou inscrição com pendência',
                'assunto' => 'Pedido com pendência - {pedido.codigo}',
                'corpo_html' => <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendência</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Pedido com pendência</h1>
            <p>O pedido <strong>{pedido.codigo}</strong> precisa de ajuste.</p>
            <p>Observação: {observacao}</p>
            <p>Revise os dados e reenvie o comprovante, se necessário.</p>
        </div>
    </div>
</body>
</html>
HTML,
                'gatilho_descricao' => 'Enviado quando o pedido, comprovante ou inscrição recebe status de pendência.',
                'variaveis_json' => json_encode(array('{pedido.codigo}', '{observacao}'), JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'email.curso_proximo' => array(
                'evento' => 'email.curso_proximo',
                'template' => 'curso_proximo',
                'nome' => 'Curso próximo',
                'assunto' => 'Seu curso está próximo',
                'corpo_html' => <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curso próximo</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Seu curso está próximo</h1>
            <p>{inscricao.curso_nome}</p>
            <p>{inscricao.turma_nome}</p>
            <p>Participante: {inscricao.participante_nome}</p>
        </div>
    </div>
</body>
</html>
HTML,
                'gatilho_descricao' => 'Enviado quando as inscrições são geradas ou quando a inscrição muda para em_andamento.',
                'variaveis_json' => json_encode(array('{inscricao.participante_nome}', '{inscricao.curso_nome}', '{inscricao.turma_nome}'), JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'email.concluido' => array(
                'evento' => 'email.concluido',
                'template' => 'concluido',
                'nome' => 'Curso concluído',
                'assunto' => 'Curso concluído',
                'corpo_html' => <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concluído</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Curso concluído</h1>
            <p>Parabéns, {inscricao.participante_nome}.</p>
            <p>Seu curso {inscricao.curso_nome} foi concluído.</p>
        </div>
    </div>
</body>
</html>
HTML,
                'gatilho_descricao' => 'Enviado quando a inscrição muda para concluida ou concluida_sem_certificado.',
                'variaveis_json' => json_encode(array('{inscricao.participante_nome}', '{inscricao.curso_nome}'), JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'email.certificado_disponivel' => array(
                'evento' => 'email.certificado_disponivel',
                'template' => 'certificado_disponivel',
                'nome' => 'Certificado disponível',
                'assunto' => 'Certificado disponível',
                'corpo_html' => <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado disponível</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Certificado disponível</h1>
            <p>Seu certificado de {inscricao.curso_nome} está disponível.</p>
            <p>Acesse sua área do aluno para validar e baixar o documento.</p>
                </div>
    </div>
</body>
</html>
HTML,
                'gatilho_descricao' => 'Enviado quando a inscrição muda para certificado_emitido.',
                'variaveis_json' => json_encode(array('{usuario.nome}', '{inscricao.curso_nome}', '{certificado_url_download}'), JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
        );
    }

    private function pedidoRecuperacaoDefaults()
    {
        $placeholders = array('{{aluno_nome}}', '{{aluno_email}}', '{{pedido_codigo}}', '{{curso_nome}}', '{{valor_total}}', '{{valor_pago}}', '{{valor_pendente}}', '{{link_pagamento}}', '{{link_pedido}}', '{{data_pedido}}', '{{data_expiracao}}', '{{cupom_codigo}}', '{{whatsapp_atendimento}}', '{{link_descadastro_recuperacao}}');

        return array(
            'pedido_recuperacao_primeiro_lembrete' => array(
                'evento' => 'pedido_recuperacao_primeiro_lembrete',
                'template' => 'pedido_recuperacao_primeiro_lembrete',
                'nome' => 'Recuperação de pedido - 1º lembrete',
                'assunto' => 'Norminha aqui: sua inscrição ficou quase pronta',
                'corpo_html' => '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Recuperação de pedido</title></head><body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;"><div style="max-width:640px;margin:0 auto;padding:24px;"><div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;"><p style="margin:0 0 16px;"><a href="{{link_descadastro_recuperacao}}">Não quero mais receber este tipo de lembrete</a></p><p>Olá, {{aluno_nome}}!</p><p>Aqui é a Norminha, do Desbloqueia Cursos. Vi que você começou o pedido {{pedido_codigo}}, mas ele ainda não foi concluído.</p><p>Curso: {{curso_nome}}<br>Valor pendente: {{valor_pendente}}</p><p>Para continuar, acesse o resumo do seu pedido:<br>{{link_pedido}}</p><p>Se tiver qualquer dificuldade, fale com nossa equipe de atendimento.</p></div></div></body></html>',
                'gatilho_descricao' => 'Modelo de recuperação enviado manualmente no primeiro lembrete.',
                'variaveis_json' => json_encode($placeholders, JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'pedido_recuperacao_segundo_lembrete' => array(
                'evento' => 'pedido_recuperacao_segundo_lembrete',
                'template' => 'pedido_recuperacao_segundo_lembrete',
                'nome' => 'Recuperação de pedido - 2º lembrete',
                'assunto' => 'Seu pedido ainda está esperando por você',
                'corpo_html' => '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Recuperação de pedido</title></head><body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;"><div style="max-width:640px;margin:0 auto;padding:24px;"><div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;"><p style="margin:0 0 16px;"><a href="{{link_descadastro_recuperacao}}">Não quero mais receber este tipo de lembrete</a></p><p>Olá, {{aluno_nome}}!</p><p>Passando rapidinho para lembrar que o pedido {{pedido_codigo}} ainda está em aberto.</p><p>Curso: {{curso_nome}}<br>Valor pendente: {{valor_pendente}}</p><p>Você pode continuar pelo resumo do pedido:<br>{{link_pedido}}</p><p>Se precisar de ajuda, nossa equipe está por perto.</p></div></div></body></html>',
                'gatilho_descricao' => 'Segundo lembrete da recuperação de pedido.',
                'variaveis_json' => json_encode($placeholders, JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'pedido_recuperacao_terceiro_lembrete' => array(
                'evento' => 'pedido_recuperacao_terceiro_lembrete',
                'template' => 'pedido_recuperacao_terceiro_lembrete',
                'nome' => 'Recuperação de pedido - 3º lembrete',
                'assunto' => 'Norminha passando para te lembrar do seu curso',
                'corpo_html' => '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Recuperação de pedido</title></head><body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;"><div style="max-width:640px;margin:0 auto;padding:24px;"><div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;"><p style="margin:0 0 16px;"><a href="{{link_descadastro_recuperacao}}">Não quero mais receber este tipo de lembrete</a></p><p>Olá, {{aluno_nome}}!</p><p>Seu pedido {{pedido_codigo}} ainda não foi concluído, e eu não queria que você perdesse a chance de continuar seus estudos.</p><p>Curso: {{curso_nome}}<br>Valor pendente: {{valor_pendente}}</p><p>Acesse o resumo do pedido:<br>{{link_pedido}}</p><p>Qualquer dúvida, fale com a equipe do Desbloqueia Cursos.</p></div></div></body></html>',
                'gatilho_descricao' => 'Terceiro lembrete da recuperação de pedido.',
                'variaveis_json' => json_encode($placeholders, JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'pedido_recuperacao_ultimo_lembrete' => array(
                'evento' => 'pedido_recuperacao_ultimo_lembrete',
                'template' => 'pedido_recuperacao_ultimo_lembrete',
                'nome' => 'Recuperação de pedido - último lembrete',
                'assunto' => 'Último lembrete sobre seu pedido no Desbloqueia Cursos',
                'corpo_html' => '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Recuperação de pedido</title></head><body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;"><div style="max-width:640px;margin:0 auto;padding:24px;"><div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;"><p style="margin:0 0 16px;"><a href="{{link_descadastro_recuperacao}}">Não quero mais receber este tipo de lembrete</a></p><p>Olá, {{aluno_nome}}!</p><p>Este é um último lembrete da Norminha sobre o pedido {{pedido_codigo}}, que ainda está em aberto.</p><p>Curso: {{curso_nome}}<br>Valor pendente: {{valor_pendente}}</p><p>Para continuar, acesse:<br>{{link_pedido}}</p><p>Se você não quiser seguir com este pedido, não precisa fazer nada.</p></div></div></body></html>',
                'gatilho_descricao' => 'Último lembrete da recuperação de pedido.',
                'variaveis_json' => json_encode($placeholders, JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'pedido_recuperacao_quase_expirando' => array(
                'evento' => 'pedido_recuperacao_quase_expirando',
                'template' => 'pedido_recuperacao_quase_expirando',
                'nome' => 'Recuperação de pedido - quase expirando',
                'assunto' => 'Seu pedido pode expirar em breve',
                'corpo_html' => '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Recuperação de pedido</title></head><body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;"><div style="max-width:640px;margin:0 auto;padding:24px;"><div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;"><p style="margin:0 0 16px;"><a href="{{link_descadastro_recuperacao}}">Não quero mais receber este tipo de lembrete</a></p><p>Olá, {{aluno_nome}}!</p><p>A Norminha passou para avisar que seu pedido {{pedido_codigo}} ainda está em aberto e pode expirar em breve.</p><p>Curso: {{curso_nome}}<br>Valor pendente: {{valor_pendente}}</p><p>Acesse o resumo do pedido:<br>{{link_pedido}}</p><p>Se precisar de ajuda, entre em contato com nossa equipe.</p></div></div></body></html>',
                'gatilho_descricao' => 'Lembrete para pedidos que estão próximos do prazo final.',
                'variaveis_json' => json_encode($placeholders, JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
            'pedido_recuperacao_com_cupom' => array(
                'evento' => 'pedido_recuperacao_com_cupom',
                'template' => 'pedido_recuperacao_com_cupom',
                'nome' => 'Recuperação de pedido - com cupom',
                'assunto' => 'A Norminha trouxe uma ajudinha para você concluir seu curso',
                'corpo_html' => '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Recuperação de pedido</title></head><body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;"><div style="max-width:640px;margin:0 auto;padding:24px;"><div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;"><p style="margin:0 0 16px;"><a href="{{link_descadastro_recuperacao}}">Não quero mais receber este tipo de lembrete</a></p><p>Olá, {{aluno_nome}}!</p><p>Vi que seu pedido {{pedido_codigo}} ainda está em aberto e trouxe uma ajudinha para você continuar.</p><p>Curso: {{curso_nome}}<br>Valor pendente: {{valor_pendente}}<br>Cupom: {{cupom_codigo}}</p><p>Acesse o resumo do pedido:<br>{{link_pedido}}</p><p>Use o cupom informado, se ele ainda estiver válido, e finalize sua inscrição.</p></div></div></body></html>',
                'gatilho_descricao' => 'Modelo de recuperação com cupom informado manualmente.',
                'variaveis_json' => json_encode($placeholders, JSON_UNESCAPED_UNICODE),
                'ativo' => 1,
                'editavel' => 1,
            ),
        );
    }

    private function defaultDefinition($evento)
    {
        $defaults = $this->allDefaults();
        return isset($defaults[$evento]) ? $defaults[$evento] : null;
    }

    private function mergeWithDefault(?array $stored = null, ?array $default = null)
    {
        if (!$stored && !$default) {
            return array();
        }

        $merged = $default ?: array();
        if ($stored) {
            $merged = array_merge($merged, $stored);
            if (isset($stored['corpo_html']) && trim((string) $stored['corpo_html']) !== '') {
                $merged['corpo_html'] = $stored['corpo_html'];
            }
            if (isset($stored['assunto']) && trim((string) $stored['assunto']) !== '') {
                $merged['assunto'] = $stored['assunto'];
            }
            if (isset($stored['gatilho_descricao']) && trim((string) $stored['gatilho_descricao']) !== '') {
                $merged['gatilho_descricao'] = $stored['gatilho_descricao'];
            }
            if (isset($stored['variaveis_json']) && trim((string) $stored['variaveis_json']) !== '') {
                $merged['variaveis_json'] = $stored['variaveis_json'];
            }
        }

        return $merged;
    }

    public function filtrarModelos(array $modelos, $busca)
    {
        $termo = $this->normalizarTextoBusca($busca);
        if ($termo === '') {
            return $modelos;
        }

        $filtrados = array();
        foreach ($modelos as $modelo) {
            if ($this->modeloCorrespondeBusca($modelo, $termo)) {
                $filtrados[] = $modelo;
            }
        }

        return $filtrados;
    }

    private function modeloCorrespondeBusca(array $modelo, $termo)
    {
        $ativo = !empty($modelo['ativo']);
        $status = $ativo ? 'ativo ativado habilitado' : 'inativo desativado desabilitado';

        $campos = array(
            isset($modelo['nome']) ? (string) $modelo['nome'] : '',
            isset($modelo['assunto']) ? (string) $modelo['assunto'] : '',
            isset($modelo['evento']) ? (string) $modelo['evento'] : '',
            isset($modelo['template']) ? (string) $modelo['template'] : '',
            isset($modelo['gatilho_descricao']) ? (string) $modelo['gatilho_descricao'] : '',
            isset($modelo['corpo_html']) ? strip_tags((string) $modelo['corpo_html']) : '',
            isset($modelo['variaveis_disponiveis']) ? (string) $modelo['variaveis_disponiveis'] : '',
            $status,
        );

        foreach ($campos as $campo) {
            if ($campo === '') {
                continue;
            }

            if (strpos($this->normalizarTextoBusca($campo), $termo) !== false) {
                return true;
            }
        }

        return false;
    }

    private function normalizarTextoBusca($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $valor = mb_strtolower($valor, 'UTF-8');
        } else {
            $valor = strtolower($valor);
        }

        $acentos = array(
            'Ã¡' => 'a', 'Ã ' => 'a', 'Ã£' => 'a', 'Ã¢' => 'a', 'Ã¤' => 'a',
            'Ã©' => 'e', 'Ã¨' => 'e', 'Ãª' => 'e', 'Ã«' => 'e',
            'Ã­' => 'i', 'Ã¬' => 'i', 'Ã®' => 'i', 'Ã¯' => 'i',
            'Ã³' => 'o', 'Ã²' => 'o', 'Ãµ' => 'o', 'Ã´' => 'o', 'Ã¶' => 'o',
            'Ãº' => 'u', 'Ã¹' => 'u', 'Ã»' => 'u', 'Ã¼' => 'u',
            'Ã§' => 'c', 'Ã±' => 'n',
        );

        return strtr($valor, $acentos);
    }

    private function normalizeModel(array $modelo)
    {
        $variaveis = $this->decodeVariables(isset($modelo['variaveis_json']) ? $modelo['variaveis_json'] : null);
        if (isset($modelo['evento']) && (string) $modelo['evento'] === 'email.certificado_disponivel' && !in_array('{certificado_url_download}', $variaveis, true)) {
            $variaveis[] = '{certificado_url_download}';
        }
        if (isset($modelo['evento']) && (string) $modelo['evento'] === 'email.certificado_disponivel' && !in_array('{usuario.nome}', $variaveis, true)) {
            $variaveis[] = '{usuario.nome}';
        }
        $modelo['variaveis_json'] = json_encode($variaveis, JSON_UNESCAPED_UNICODE);
        $modelo['variaveis_disponiveis'] = implode(PHP_EOL, $variaveis);
        $modelo['ativo'] = !empty($modelo['ativo']) ? 1 : 0;
        $modelo['editavel'] = !empty($modelo['editavel']) ? 1 : 0;
        $modelo['is_default'] = empty($modelo['id']);
        $modelo['is_default_event'] = $this->isDefaultEvento(isset($modelo['evento']) ? $modelo['evento'] : '');

        if (!isset($modelo['corpo_html']) || trim((string) $modelo['corpo_html']) === '') {
            $default = $this->defaultDefinition($modelo['evento']);
            if ($default && isset($default['corpo_html'])) {
                $modelo['corpo_html'] = $default['corpo_html'];
            }
        }

        if (!isset($modelo['assunto']) || trim((string) $modelo['assunto']) === '') {
            $default = $this->defaultDefinition($modelo['evento']);
            if ($default && isset($default['assunto'])) {
                $modelo['assunto'] = $default['assunto'];
            }
        }

        if (!isset($modelo['gatilho_descricao']) || trim((string) $modelo['gatilho_descricao']) === '') {
            $default = $this->defaultDefinition($modelo['evento']);
            if ($default && isset($default['gatilho_descricao'])) {
                $modelo['gatilho_descricao'] = $default['gatilho_descricao'];
            }
        }

        return $modelo;
    }

    private function resolverUsuarioContexto(array $data)
    {
        $usuario = isset($data['usuario']) && is_array($data['usuario']) ? $data['usuario'] : array();

        $nome = isset($usuario['nome']) ? trim((string) $usuario['nome']) : '';
        if ($nome === '') {
            $candidatosNome = array(
                isset($data['usuario_nome']) ? $data['usuario_nome'] : null,
                isset($data['aluno_nome']) ? $data['aluno_nome'] : null,
                isset($data['participante_nome']) ? $data['participante_nome'] : null,
                isset($data['pagador_nome']) ? $data['pagador_nome'] : null,
                isset($data['inscricao']['aluno_nome']) ? $data['inscricao']['aluno_nome'] : null,
                isset($data['inscricao']['participante_nome']) ? $data['inscricao']['participante_nome'] : null,
                isset($data['inscricao']['pagador_nome']) ? $data['inscricao']['pagador_nome'] : null,
                isset($data['certificado']['nome_participante']) ? $data['certificado']['nome_participante'] : null,
                isset($data['aluno']['nome']) ? $data['aluno']['nome'] : null,
                isset($data['pedido']['pagador_nome']) ? $data['pedido']['pagador_nome'] : null,
            );

            foreach ($candidatosNome as $candidato) {
                $candidato = trim((string) $candidato);
                if ($candidato !== '') {
                    $nome = $candidato;
                    break;
                }
            }

            if ($nome === '') {
                $nome = 'Aluno(a)';
            }
        }

        $email = isset($usuario['email']) ? trim((string) $usuario['email']) : '';
        if ($email === '') {
            $candidatosEmail = array(
                isset($data['usuario_email']) ? $data['usuario_email'] : null,
                isset($data['aluno_email']) ? $data['aluno_email'] : null,
                isset($data['participante_email']) ? $data['participante_email'] : null,
                isset($data['pagador_email']) ? $data['pagador_email'] : null,
                isset($data['inscricao']['aluno_email']) ? $data['inscricao']['aluno_email'] : null,
                isset($data['inscricao']['pagador_email']) ? $data['inscricao']['pagador_email'] : null,
                isset($data['aluno']['email']) ? $data['aluno']['email'] : null,
                isset($data['pedido']['pagador_email']) ? $data['pedido']['pagador_email'] : null,
            );

            foreach ($candidatosEmail as $candidato) {
                $candidato = trim((string) $candidato);
                if ($candidato !== '' && filter_var($candidato, FILTER_VALIDATE_EMAIL) !== false) {
                    $email = strtolower($candidato);
                    break;
                }
            }
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $email = strtolower($email);
        }

        if ($email === '' && !empty($data['sistema']['email_suporte'])) {
            $email = trim((string) $data['sistema']['email_suporte']);
        }

        $cpf = isset($usuario['cpf']) ? trim((string) $usuario['cpf']) : '';
        if ($cpf === '') {
            $candidatosCpf = array(
                isset($data['usuario_cpf']) ? $data['usuario_cpf'] : null,
                isset($data['aluno_cpf']) ? $data['aluno_cpf'] : null,
                isset($data['participante_cpf']) ? $data['participante_cpf'] : null,
                isset($data['inscricao']['aluno_cpf']) ? $data['inscricao']['aluno_cpf'] : null,
                isset($data['inscricao']['participante_cpf']) ? $data['inscricao']['participante_cpf'] : null,
            );

            foreach ($candidatosCpf as $candidato) {
                $candidato = trim((string) $candidato);
                if ($candidato !== '') {
                    $cpf = $candidato;
                    break;
                }
            }
        }

        $usuario['nome'] = $nome;
        $usuario['email'] = $email;
        $usuario['cpf'] = $cpf;

        return $usuario;
    }

    private function nomeDaCopia($valor)
    {
        $valor = trim((string) $valor);
        $valor = preg_replace('/^c[oó]pia de\s+/iu', '', $valor);
        return 'Cópia de ' . $valor;
    }

    private function resolverCertificadoUrlDownload(array $data)
    {
        $candidatos = array(
            isset($data['certificado_url_download']) ? trim((string) $data['certificado_url_download']) : '',
            isset($data['certificado_pdf_url']) ? trim((string) $data['certificado_pdf_url']) : '',
            isset($data['inscricao']['certificado_url_download']) ? trim((string) $data['inscricao']['certificado_url_download']) : '',
            isset($data['inscricao']['certificado_pdf_url']) ? trim((string) $data['inscricao']['certificado_pdf_url']) : '',
        );

        if (isset($data['certificado']) && is_array($data['certificado']) && !empty($data['certificado']['codigo'])) {
            $candidatos[] = Helpers::url('certificados/pdf?codigo=' . urlencode((string) $data['certificado']['codigo']));
        }

        if (isset($data['inscricao']) && is_array($data['inscricao']) && !empty($data['inscricao']['certificado_codigo'])) {
            $candidatos[] = Helpers::url('certificados/pdf?codigo=' . urlencode((string) $data['inscricao']['certificado_codigo']));
        }

        if (!empty($data['certificado_codigo'])) {
            $candidatos[] = Helpers::url('certificados/pdf?codigo=' . urlencode((string) $data['certificado_codigo']));
        }

        foreach ($candidatos as $candidato) {
            $candidato = trim((string) $candidato);
            if ($candidato !== '') {
                return $candidato;
            }
        }

        return '';
    }

    private function suffixUnique($valor, $field)
    {
        $base = trim((string) $valor);
        $base = preg_replace('/-copia(?:-\d+)?$/i', '', $base);
        if ($base === '') {
            $base = $field === 'template' ? 'modelo' : 'evento';
        }

        $candidate = $base . '-copia';
        $index = 2;
        while ($this->existsUniqueValue($field, $candidate)) {
            $candidate = $base . '-copia-' . $index;
            $index++;
        }

        return $candidate;
    }

    private function existsUniqueValue($field, $value)
    {
        if ($field === 'evento') {
            return (bool) $this->modeloModel->findByEvento($value);
        }

        $stmt = \App\Core\Database::connection()->prepare(
            'SELECT id
             FROM emails_modelos
             WHERE template = :template
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('template' => (string) $value));

        return (bool) $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function decodeVariables($json)
    {
        if (is_array($json)) {
            $values = $json;
        } else {
            $json = trim((string) $json);
            if ($json === '') {
                return array();
            }

            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $values = $decoded;
            } else {
                $lines = preg_split('/\r\n|\r|\n/', $json);
                $values = $lines !== false ? $lines : array();
            }
        }

        $clean = array();
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $clean[] = $value;
        }

        return array_values(array_unique($clean));
    }

    private function normalizeVariablesJson($value)
    {
        $values = $this->decodeVariables($value);
        return json_encode($values, JSON_UNESCAPED_UNICODE);
    }

    private function montarMapaFlat(array $context)
    {
        $flat = $this->flattenContext($this->buildContext($context));

        foreach (EmailPlaceholders::aliasGroups() as $grupo) {
            $valor = null;
            foreach ($grupo as $chave) {
                if (isset($flat[$chave]) && $flat[$chave] !== '') {
                    $valor = $flat[$chave];
                    break;
                }
            }
            if ($valor === null) {
                continue;
            }
            foreach ($grupo as $chave) {
                if (!isset($flat[$chave]) || $flat[$chave] === '') {
                    $flat[$chave] = $valor;
                }
            }
        }

        return $flat;
    }

    private function resolverContextoFlat(array $context)
    {
        $flat = $this->montarMapaFlat($context);

        foreach (EmailPlaceholders::inventory() as $item) {
            $chave = $item['key'];
            if ((!isset($flat[$chave]) || $flat[$chave] === '') && $item['fallback'] !== '') {
                $flat[$chave] = htmlspecialchars((string) $item['fallback'], ENT_QUOTES, 'UTF-8');
            }
        }

        return $flat;
    }

    public function analisarPlaceholders($content, array $context = array(), $evento = '')
    {
        $content = (string) $content;
        $flat = $this->montarMapaFlat($context);

        $encontrados = array();
        if (preg_match_all('/\{\{([a-zA-Z0-9_.]+)\}\}|\{([a-zA-Z0-9_.]+)\}/', $content, $todos, PREG_SET_ORDER)) {
            foreach ($todos as $match) {
                $key = isset($match[1]) && $match[1] !== '' ? $match[1] : (isset($match[2]) ? $match[2] : '');
                if ($key !== '') {
                    $encontrados[$key] = true;
                }
            }
        }
        $encontrados = array_keys($encontrados);

        $resolvidos = array();
        $semContexto = array();
        $desconhecidos = array();
        foreach ($encontrados as $key) {
            $temValor = isset($flat[$key]) && $flat[$key] !== '';
            if (!EmailPlaceholders::isKnownKey($key)) {
                $desconhecidos[] = $key;
            } elseif ($temValor) {
                $resolvidos[] = $key;
            } else {
                $semContexto[] = $key;
            }
        }

        $criticos = array();
        foreach (EmailPlaceholders::requiredKeysForEvent($evento) as $req) {
            if (in_array($req, $encontrados, true) && !(isset($flat[$req]) && $flat[$req] !== '')) {
                $criticos[] = $req;
            }
        }
        $anyReq = EmailPlaceholders::requiredAnyForEvent($evento);
        if (!empty($anyReq)) {
            $usado = false;
            $ok = false;
            foreach ($anyReq as $req) {
                if (in_array($req, $encontrados, true)) {
                    $usado = true;
                    if (isset($flat[$req]) && $flat[$req] !== '') {
                        $ok = true;
                    }
                }
            }
            if ($usado && !$ok) {
                $criticos[] = implode('|', $anyReq);
            }
        }

        return array(
            'encontrados' => $encontrados,
            'resolvidos' => $resolvidos,
            'sem_contexto' => $semContexto,
            'desconhecidos' => $desconhecidos,
            'criticos_pendentes' => array_values(array_unique($criticos)),
        );
    }

    public function inventarioPlaceholders($evento = null)
    {
        return ($evento === null || $evento === '')
            ? EmailPlaceholders::inventory()
            : EmailPlaceholders::forEvent($evento);
    }

    public function analisarModeloParaAdmin($assunto, $corpo, $evento = '')
    {
        $tokens = array();
        foreach (array((string) $assunto, (string) $corpo) as $parte) {
            if (preg_match_all('/\{\{([a-zA-Z0-9_.]+)\}\}|\{([a-zA-Z0-9_.]+)\}/', $parte, $todos, PREG_SET_ORDER)) {
                foreach ($todos as $match) {
                    $key = isset($match[1]) && $match[1] !== '' ? $match[1] : (isset($match[2]) ? $match[2] : '');
                    if ($key !== '') {
                        $tokens[$key] = ($match[1] !== '' ? '{{' . $key . '}}' : '{' . $key . '}');
                    }
                }
            }
        }

        $desconhecidos = array();
        $incompativeis = array();
        foreach ($tokens as $key => $literal) {
            if (!EmailPlaceholders::isKnownKey($key)) {
                $desconhecidos[] = $literal;
                continue;
            }
            if ($evento !== '' && !$this->placeholderCompativelComEvento($key, $evento)) {
                $incompativeis[] = $literal;
            }
        }

        return array(
            'desconhecidos' => array_values(array_unique($desconhecidos)),
            'incompativeis' => array_values(array_unique($incompativeis)),
        );
    }

    private function placeholderCompativelComEvento($key, $evento)
    {
        foreach (EmailPlaceholders::inventory() as $item) {
            if ($item['key'] !== $key) {
                continue;
            }
            foreach ($item['eventos'] as $glob) {
                if (EmailPlaceholders::eventMatches($evento, $glob)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    private function flattenContext(array $context, $prefix = '')
    {
        $flat = array();

        foreach ($context as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenContext($value, $name));
                continue;
            }

            $flat[$name] = $this->formatPlaceholderValue($key, $value);
        }

        return $flat;
    }

    private function formatPlaceholderValue($key, $value)
    {
        if ($value === null) {
            return '';
        }

        $stringValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        if ($stringValue === '') {
            return '';
        }

        if (is_numeric($value) && preg_match('/(total|valor|preco|desconto|acrescimo|subtotal|pago|pendente|saldo|nao_pago)$/i', (string) $key)) {
            $formatado = number_format((float) $value, 2, ',', '.');
            $chavesMoeda = array('total', 'valor_total', 'valor_pago', 'valor_pendente');
            if (in_array(strtolower((string) $key), $chavesMoeda, true)) {
                $formatado = 'R$ ' . $formatado;
            }
            return htmlspecialchars($formatado, ENT_QUOTES, 'UTF-8');
        }

        return htmlspecialchars($stringValue, ENT_QUOTES, 'UTF-8');
    }
}

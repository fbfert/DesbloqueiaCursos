<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\CertificadoAssinante;
use App\Models\CertificadoTemplate;
use Exception;

class CertificadoTemplateService
{
    private $templateModel;
    private $assinanteModel;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->templateModel = new CertificadoTemplate();
        $this->assinanteModel = new CertificadoAssinante();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function listTemplates()
    {
        return $this->templateModel->allActive();
    }

    public function listAdmin()
    {
        return $this->templateModel->listAdmin();
    }

    public function defaultTemplate()
    {
        return $this->templateModel->defaultTemplate();
    }

    public function find($id)
    {
        return $this->templateModel->findById($id);
    }

    public function formData($id = null)
    {
        if ($id) {
            return $this->templateModel->findById($id);
        }

        return array(
            'nome' => '',
            'slug' => '',
            'descricao' => '',
            'status' => 'rascunho',
            'contexto' => 'global',
            'curso_id' => null,
            'turma_id' => null,
            'padrao' => 0,
            'ativo' => 1,
            'orientacao' => 'paisagem',
            'tamanho_papel' => 'A4',
            'margem_top' => null,
            'margem_bottom' => null,
            'margem_left' => null,
            'margem_right' => null,
            'corpo_html' => '',
            'html_segunda_pagina' => '',
            'css' => '',
            'imagem_fundo' => '',
            'logo' => '',
            'assinatura_url' => '',
            'cor_fundo' => '#ffffff',
            'cor_texto' => '#111827',
            'observacoes' => '',
        );
    }

    public function save(array $input, $actorUserId = null, $ipAddress = null, $userAgent = null, array $files = array())
    {
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $stored = $id > 0 ? $this->templateModel->findById($id) : null;
        $payload = $this->normalizePayload($input, $stored, $actorUserId);
        $errors = $this->validate($payload, $id);
        if (!empty($errors)) {
            return array('ok' => false, 'errors' => $errors);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        $uploadedArquivos = array();

        try {
            if ($id > 0 && $stored) {
                $this->templateModel->update($id, $payload);
                $resultId = $id;
            } else {
                $resultId = $this->templateModel->create($payload);
            }

            $uploadErrors = array();
            $input = $this->aplicarUploads($input, $files, $resultId, $uploadErrors, $uploadedArquivos);
            if (!empty($uploadErrors)) {
                $this->removerUploadsTemporarios($uploadedArquivos);
                $pdo->rollBack();
                return array('ok' => false, 'errors' => $uploadErrors);
            }

            $payloadUpload = $this->normalizePayload($input, $stored ? array_merge($stored, array('id' => $resultId)) : array('id' => $resultId), $actorUserId);
            $payload = array_merge($payload, array(
                'imagem_fundo' => $payloadUpload['imagem_fundo'],
                'logo' => $payloadUpload['logo'],
                'assinatura_url' => $payloadUpload['assinatura_url'],
            ));

            $this->templateModel->update($resultId, $payload);

            if (!empty($payload['padrao']) && (int) $payload['padrao'] === 1 && $this->isGlobalContext($payload)) {
                $this->templateModel->unsetDefaultGlobal($resultId);
            }

            $this->auditService->record(
                $id > 0 ? 'certificados_template.atualizado' : 'certificados_template.criado',
                'certificados_template',
                $resultId,
                $payload,
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            $pdo->commit();

            Logger::info('certificados_template.salvo', array('id' => $resultId));
            return array('ok' => true, 'id' => $resultId);
        } catch (Exception $exception) {
            $pdo->rollBack();
            $this->removerUploadsTemporarios($uploadedArquivos);
            Logger::error('certificados_template.salvar_falhou', array(
                'message' => $exception->getMessage(),
                'id' => $id,
            ));
            return array(
                'ok' => false,
                'errors' => array('Não foi possível salvar o template. Tente novamente.'),
            );
        }
    }

    public function duplicar($id, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $original = $this->templateModel->findById($id);
        if (!$original) {
            return array('ok' => false, 'message' => 'Template não encontrado.');
        }

        $novo = $original;
        unset($novo['id']);
        $novo['nome'] = 'Cópia - ' . (string) $original['nome'];
        $novo['padrao'] = 0;
        $novo['slug'] = $this->slugUnico((string) ($original['slug'] ?? 'template') . '-copia');
        $novo['criado_por'] = $actorUserId ? (int) $actorUserId : null;
        $novo['atualizado_por'] = $actorUserId ? (int) $actorUserId : null;
        $novo['curso_id'] = !empty($original['curso_id']) ? (int) $original['curso_id'] : null;
        $novo['turma_id'] = !empty($original['turma_id']) ? (int) $original['turma_id'] : null;
        $novo['contexto'] = !empty($original['contexto']) ? (string) $original['contexto'] : 'global';
        $novo['status'] = !empty($original['status']) ? (string) $original['status'] : ((int) ($original['ativo'] ?? 1) === 1 ? 'ativo' : 'inativo');
        $novo['ativo'] = (int) ($original['ativo'] ?? 1);

        $payload = $this->normalizePayload($novo, null, $actorUserId);
        $errors = $this->validate($payload, 0);
        if (!empty($errors)) {
            return array('ok' => false, 'errors' => $errors);
        }

        $newId = $this->templateModel->create($payload);
        $this->auditService->record(
            'certificados_template.duplicado',
            'certificados_template',
            $newId,
            array('origem_id' => (int) $id),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        return array('ok' => true, 'id' => $newId);
    }

    public function definirPadrao($id, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $template = $this->templateModel->findById($id);
        if (!$template) {
            return array('ok' => false, 'message' => 'Template não encontrado.');
        }

        if (!$this->isGlobalContext($template)) {
            return array('ok' => false, 'message' => 'A definição de padrão está disponível somente para templates globais.');
        }

        $payload = $this->normalizePayload(array_merge($template, array('padrao' => 1)), $template, $actorUserId);
        $this->templateModel->update($id, $payload);
        $this->templateModel->unsetDefaultGlobal($id);

        $this->auditService->record(
            'certificados_template.padrao_definido',
            'certificados_template',
            $id,
            array('padrao' => 1),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        return array('ok' => true, 'id' => (int) $id);
    }

    public function excluir($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $template = $this->templateModel->findById($id);
        if (!$template) {
            return array('ok' => false, 'message' => 'Template não encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('certificados_template', (int) $id, $justificativa, $template, $actorUserId, $ipAddress, $userAgent);
            $this->templateModel->softDelete($id, $actorUserId);
            $this->auditService->record('certificados_template.excluido', 'certificados_template', $id, array('justificativa' => $justificativa), $actorUserId, $ipAddress, $userAgent);
            $pdo->commit();
        } catch (Exception $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return array('ok' => true);
    }

    public function assinantesDoCurso($cursoId, $templateId = null)
    {
        return $this->assinanteModel->forCurso($cursoId, $templateId);
    }

    private function normalizePayload(array $input, ?array $stored, $actorUserId)
    {
        $nome = trim((string) ($input['nome'] ?? ($stored['nome'] ?? '')));
        $slugRaw = trim((string) ($input['slug'] ?? ($stored['slug'] ?? '')));
        $slug = $this->sanitizeSlug($slugRaw !== '' ? $slugRaw : $nome);
        if ($slug === '') {
            $slug = $this->sanitizeSlug('template');
        }

        $descricao = isset($input['descricao']) ? trim((string) $input['descricao']) : ($stored['descricao'] ?? null);
        $status = isset($input['status']) ? trim((string) $input['status']) : ($stored['status'] ?? 'rascunho');
        $contexto = isset($input['contexto']) ? trim((string) $input['contexto']) : ($stored['contexto'] ?? 'global');
        $cursoId = isset($input['curso_id']) && (string) $input['curso_id'] !== '' ? (int) $input['curso_id'] : null;
        $turmaId = isset($input['turma_id']) && (string) $input['turma_id'] !== '' ? (int) $input['turma_id'] : null;

        $ativo = !empty($input['ativo']) ? 1 : 0;
        if ($status === 'ativo') {
            $ativo = 1;
        }
        if ($status === 'inativo') {
            $ativo = 0;
        }

        $padrao = !empty($input['padrao']) ? 1 : 0;

        $corpoHtml = isset($input['corpo_html']) ? trim((string) $input['corpo_html']) : ($stored['corpo_html'] ?? null);
        if ($corpoHtml !== '') {
            $corpoHtml = $this->normalizarHtmlCertificadoLegado($corpoHtml);
        }
        $htmlSegundaPagina = isset($input['html_segunda_pagina']) ? trim((string) $input['html_segunda_pagina']) : ($stored['html_segunda_pagina'] ?? null);
        $css = isset($input['css']) ? trim((string) $input['css']) : ($stored['css'] ?? null);
        $imagemFundo = array_key_exists('imagem_fundo', $input)
            ? $this->normalizarImagemPublica(trim((string) $input['imagem_fundo']))
            : (isset($stored['imagem_fundo']) ? $stored['imagem_fundo'] : null);
        $logo = array_key_exists('logo', $input)
            ? $this->normalizarImagemPublica(trim((string) $input['logo']))
            : (isset($stored['logo']) ? $stored['logo'] : null);
        $assinaturaUrl = array_key_exists('assinatura_url', $input)
            ? $this->normalizarImagemPublica(trim((string) $input['assinatura_url']))
            : (isset($stored['assinatura_url']) ? $stored['assinatura_url'] : null);
        $observacoes = isset($input['observacoes']) ? trim((string) $input['observacoes']) : ($stored['observacoes'] ?? null);
        $corFundo = isset($input['cor_fundo']) ? trim((string) $input['cor_fundo']) : ($stored['cor_fundo'] ?? null);
        $corTexto = isset($input['cor_texto']) ? trim((string) $input['cor_texto']) : ($stored['cor_texto'] ?? null);

        $orientacao = isset($input['orientacao']) ? trim((string) $input['orientacao']) : ($stored['orientacao'] ?? 'paisagem');
        $tamanhoPapel = isset($input['tamanho_papel']) ? trim((string) $input['tamanho_papel']) : ($stored['tamanho_papel'] ?? 'A4');

        $margemTop = isset($input['margem_top']) && (string) $input['margem_top'] !== '' ? (float) $input['margem_top'] : null;
        $margemBottom = isset($input['margem_bottom']) && (string) $input['margem_bottom'] !== '' ? (float) $input['margem_bottom'] : null;
        $margemLeft = isset($input['margem_left']) && (string) $input['margem_left'] !== '' ? (float) $input['margem_left'] : null;
        $margemRight = isset($input['margem_right']) && (string) $input['margem_right'] !== '' ? (float) $input['margem_right'] : null;

        return array(
            'nome' => $nome,
            'slug' => $this->slugUnico($slug, isset($stored['id']) ? (int) $stored['id'] : null),
            'descricao' => $descricao !== '' ? $descricao : null,
            'status' => $status !== '' ? $status : 'rascunho',
            'contexto' => $contexto !== '' ? $contexto : 'global',
            'curso_id' => $cursoId > 0 ? $cursoId : null,
            'turma_id' => $turmaId > 0 ? $turmaId : null,
            'corpo_html' => $corpoHtml !== '' ? $corpoHtml : null,
            'html_segunda_pagina' => $htmlSegundaPagina !== '' ? $htmlSegundaPagina : null,
            'css' => $css !== '' ? $css : null,
            'imagem_fundo' => $imagemFundo !== '' ? $imagemFundo : null,
            'logo' => $logo !== '' ? $logo : null,
            'assinatura_url' => $assinaturaUrl !== '' ? $assinaturaUrl : null,
            'observacoes' => $observacoes !== '' ? $observacoes : null,
            'cor_fundo' => $corFundo !== '' ? $corFundo : null,
            'cor_texto' => $corTexto !== '' ? $corTexto : null,
            'ativo' => $ativo,
            'padrao' => $padrao,
            'orientacao' => $orientacao !== '' ? $orientacao : 'paisagem',
            'tamanho_papel' => $tamanhoPapel !== '' ? $tamanhoPapel : 'A4',
            'margem_top' => $margemTop,
            'margem_bottom' => $margemBottom,
            'margem_left' => $margemLeft,
            'margem_right' => $margemRight,
            'criado_por' => $stored ? ($stored['criado_por'] ?? null) : ($actorUserId ? (int) $actorUserId : null),
            'atualizado_por' => $actorUserId ? (int) $actorUserId : null,
        );
    }

    private function aplicarUploads(array $input, array $files, $templateId, array &$errors, array &$uploadedArquivos = array())
    {
        if (isset($files['logo_upload']) && !empty($files['logo_upload']['tmp_name'])) {
            $resultado = $this->salvarImagemTemplateUpload($files['logo_upload'], 'logo', $templateId);
            if (empty($resultado['ok'])) {
                $errors['logo_upload'] = isset($resultado['message']) ? $resultado['message'] : 'Não foi possível enviar a logo do template.';
            } else {
                $input['logo'] = $resultado['path'];
                $uploadedArquivos[] = $resultado['absolute_path'];
            }
        }

        if (isset($files['imagem_fundo_upload']) && !empty($files['imagem_fundo_upload']['tmp_name'])) {
            $resultado = $this->salvarImagemTemplateUpload($files['imagem_fundo_upload'], 'imagem_fundo', $templateId);
            if (empty($resultado['ok'])) {
                $errors['imagem_fundo_upload'] = isset($resultado['message']) ? $resultado['message'] : 'Não foi possível enviar a imagem de fundo do template.';
            } else {
                $input['imagem_fundo'] = $resultado['path'];
                $uploadedArquivos[] = $resultado['absolute_path'];
            }
        }

        if (isset($files['assinatura_upload']) && !empty($files['assinatura_upload']['tmp_name'])) {
            $resultado = $this->salvarImagemTemplateUpload($files['assinatura_upload'], 'assinatura', $templateId);
            if (empty($resultado['ok'])) {
                $errors['assinatura_upload'] = isset($resultado['message']) ? $resultado['message'] : 'Não foi possível enviar a assinatura do template.';
            } else {
                $input['assinatura_url'] = $resultado['path'];
                $uploadedArquivos[] = $resultado['absolute_path'];
            }
        }

        return $input;
    }

    private function salvarImagemTemplateUpload(array $arquivo, $tipo, $templateId)
    {
        $rotulo = $tipo === 'imagem_fundo' ? 'imagem de fundo' : ($tipo === 'assinatura' ? 'assinatura' : 'logo');

        if (!isset($arquivo['error']) || (int) $arquivo['error'] !== UPLOAD_ERR_OK) {
            return array('ok' => false, 'message' => 'Upload de ' . $rotulo . ' inválido.');
        }

        if (empty($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
            return array('ok' => false, 'message' => 'Arquivo de ' . $rotulo . ' inválido.');
        }

        $tamanho = isset($arquivo['size']) ? (int) $arquivo['size'] : 0;
        if ($tamanho <= 0) {
            return array('ok' => false, 'message' => 'O arquivo de ' . $rotulo . ' está vazio.');
        }

        if ($tamanho > 2 * 1024 * 1024) {
            return array('ok' => false, 'message' => 'A ' . $rotulo . ' deve ter no máximo 2 MB.');
        }

        $nomeOriginal = isset($arquivo['name']) ? (string) $arquivo['name'] : '';
        $extensao = strtolower((string) pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        $extensoesPermitidas = $tipo === 'assinatura'
            ? array('jpg', 'jpeg', 'png', 'webp', 'gif')
            : array('jpg', 'jpeg', 'png', 'webp');
        if (!in_array($extensao, $extensoesPermitidas, true)) {
            $formatos = $tipo === 'assinatura' ? 'JPG, JPEG, PNG, WEBP ou GIF' : 'JPG, JPEG, PNG ou WEBP';
            return array('ok' => false, 'message' => 'Formato de ' . $rotulo . ' não permitido. Use ' . $formatos . '.');
        }

        $mime = $this->detectarMimeType($arquivo['tmp_name']);
        $mimesPermitidos = $tipo === 'assinatura'
            ? array('image/jpeg', 'image/png', 'image/webp', 'image/gif')
            : array('image/jpeg', 'image/png', 'image/webp');
        if (!in_array(strtolower((string) $mime), $mimesPermitidos, true)) {
            return array('ok' => false, 'message' => 'Tipo de arquivo de ' . $rotulo . ' não permitido.');
        }

        $templateId = (int) $templateId;
        if ($templateId <= 0) {
            return array('ok' => false, 'message' => 'Não foi possível identificar o template para salvar o arquivo.');
        }

        $diretorioAbsoluto = $this->publicRootPath() . '/' . ($tipo === 'assinatura' ? 'assets/uploads/certificados/assinaturas' : 'uploads/certificados/templates/' . $templateId);
        if (!is_dir($diretorioAbsoluto) && !@mkdir($diretorioAbsoluto, 0775, true) && !is_dir($diretorioAbsoluto)) {
            return array('ok' => false, 'message' => 'Não foi possível criar a pasta de uploads do template.');
        }

        try {
            $nomeSeguro = $this->nomeArquivoUploadTemplate($tipo, $extensao, $templateId);
        } catch (Exception $exception) {
            $nomeSeguro = $this->nomeArquivoUploadTemplateFallback($tipo, $extensao, $templateId);
        }

        $destinoAbsoluto = $diretorioAbsoluto . '/' . $nomeSeguro;
        if (!move_uploaded_file($arquivo['tmp_name'], $destinoAbsoluto)) {
            return array('ok' => false, 'message' => 'Não foi possível salvar a ' . $rotulo . ' enviada.');
        }

        return array(
            'ok' => true,
            'path' => $tipo === 'assinatura'
                ? '/assets/uploads/certificados/assinaturas/' . $nomeSeguro
                : '/uploads/certificados/templates/' . $templateId . '/' . $nomeSeguro,
            'absolute_path' => $destinoAbsoluto,
        );
    }

    private function normalizarHtmlCertificadoLegado($html)
    {
        $html = trim((string) $html);
        if ($html === '') {
            return $html;
        }

        $temLegacy = stripos($html, 'border:2px solid #0f2742') !== false
            || stripos($html, 'border:2px solid #d8bd72') !== false
            || stripos($html, 'border:1px solid #0f2742') !== false
            || stripos($html, 'border-top:1px solid #111827') !== false
            || stripos($html, 'border-top:1px solid #d8c39b') !== false
            || stripos($html, 'certificado-linha-topo') !== false;

        if (!$temLegacy) {
            return $html;
        }

        $html = preg_replace(
            '/^<table cellpadding="0" cellspacing="0" border="0" style="width:100%; border-collapse:collapse; font-family:Georgia, \'Times New Roman\', serif; color:#111827; background:#ffffff;">\s*<tr>\s*<td style="border:2px solid #0f2742; padding:6px;">\s*<table cellpadding="0" cellspacing="0" border="0" style="width:100%; border-collapse:collapse;">\s*<tr>\s*<td style="border:2px solid #d8bd72; padding:6px;">\s*<table cellpadding="0" cellspacing="0" border="0" style="width:100%; border-collapse:collapse;">\s*<tr>\s*<td style="border:1px solid #0f2742; padding:18px 30px 18px 30px;">\s*/is',
            '<div class="certificado-documento">',
            $html,
            1
        );

        $html = preg_replace(
            '/\s*<\/td>\s*<\/tr>\s*<\/table>\s*<\/td>\s*<\/tr>\s*<\/table>\s*<\/td>\s*<\/tr>\s*<\/table>\s*$/is',
            '</div>',
            $html,
            1
        );

        $html = str_replace(
            '<div style="border-top:1px solid #111827; padding-top:5px; font-size:11.5px; line-height:15px; color:#111827; text-align:center;">',
            '<div style="width:65%; margin:0 auto; border-top:1px solid #111827; padding-top:5px; font-size:11.5px; line-height:15px; color:#111827; text-align:center;">',
            $html
        );

        return $html;
    }

    private function nomeArquivoUploadTemplate($tipo, $extensao, $templateId = null)
    {
        $prefixo = $tipo === 'imagem_fundo' ? 'fundo' : ($tipo === 'assinatura' ? 'assinatura-template-' . (int) $templateId : 'logo');
        return $prefixo . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensao;
    }

    private function nomeArquivoUploadTemplateFallback($tipo, $extensao, $templateId = null)
    {
        $prefixo = $tipo === 'imagem_fundo' ? 'fundo' : ($tipo === 'assinatura' ? 'assinatura-template-' . (int) $templateId : 'logo');
        return $prefixo . '-' . date('YmdHis') . '-' . mt_rand(100000, 999999) . '.' . $extensao;
    }

    private function removerUploadsTemporarios(array $arquivos)
    {
        foreach ($arquivos as $arquivo) {
            $arquivo = str_replace('\\', '/', (string) $arquivo);
            if ($arquivo === '') {
                continue;
            }

            if (is_file($arquivo)) {
                @unlink($arquivo);
            }
        }
    }

    private function detectarMimeType($arquivoTmp)
    {
        if (!is_file($arquivoTmp)) {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = @finfo_file($finfo, $arquivoTmp);
                @finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($arquivoTmp);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return null;
    }

    private function normalizarImagemPublica($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        if (preg_match('#^data:#i', $valor) || preg_match('#^https?://#i', $valor)) {
            return $valor;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $valor)) {
            return null;
        }

        $valor = str_replace('\\', '/', $valor);

        $basePublic = str_replace('\\', '/', BASE_PATH . '/public_html');
        if (strpos($valor, $basePublic) === 0) {
            $valor = substr($valor, strlen($basePublic));
        }

        if (strpos($valor, 'public_html/') === 0) {
            $valor = substr($valor, strlen('public_html'));
        }
        if (strpos($valor, 'public/') === 0) {
            $valor = substr($valor, strlen('public'));
        }

        $valor = preg_replace('#/+#', '/', $valor);
        if ($valor === '') {
            return null;
        }

        if ($valor[0] !== '/') {
            $valor = '/' . ltrim($valor, '/');
        }

        return $valor;
    }

    private function publicRootPath()
    {
        $candidatos = array(
            BASE_PATH . '/public_html',
            BASE_PATH . '/public',
            BASE_PATH,
        );

        foreach ($candidatos as $candidato) {
            if (is_dir($candidato)) {
                return $candidato;
            }
        }

        return BASE_PATH;
    }

    private function validate(array $payload, $id = 0)
    {
        $errors = array();

        if (trim((string) $payload['nome']) === '') {
            $errors[] = 'Informe o nome do template.';
        }

        if (trim((string) $payload['slug']) === '') {
            $errors[] = 'Informe uma chave (slug) para o template.';
        }

        if (!in_array((string) $payload['status'], array('rascunho', 'ativo', 'inativo'), true)) {
            $errors[] = 'Status inválido.';
        }

        if (!in_array((string) $payload['orientacao'], array('paisagem', 'retrato'), true)) {
            $errors[] = 'Orientação inválida.';
        }

        if (!in_array((string) $payload['tamanho_papel'], array('A4', 'Carta'), true)) {
            $errors[] = 'Tamanho de papel inválido.';
        }

        foreach (array('margem_top', 'margem_bottom', 'margem_left', 'margem_right') as $campo) {
            if ($payload[$campo] === null) {
                continue;
            }
            if (!is_numeric($payload[$campo]) || (float) $payload[$campo] < 0) {
                $errors[] = 'Margens devem ser números válidos.';
                break;
            }
        }

        if (!empty($payload['padrao']) && (int) $payload['padrao'] === 1 && !$this->isGlobalContext($payload)) {
            $errors[] = 'O padrão global só pode ser definido para templates com contexto global.';
        }

        return $errors;
    }

    private function sanitizeSlug($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[\\s_]+/', '-', $value);
        $value = preg_replace('/[^a-z0-9\\-]+/', '', $value);
        $value = trim($value, '-');
        return substr($value, 0, 80);
    }

    private function slugUnico($slug, $ignoreId = null)
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            $slug = 'template';
        }

        $candidate = $slug;
        $suffix = 1;

        while (true) {
            $found = $this->templateModel->findBySlug($candidate);
            if (!$found) {
                return $candidate;
            }

            if ($ignoreId && (int) $found['id'] === (int) $ignoreId) {
                return $candidate;
            }

            $suffix++;
            $candidate = substr($slug, 0, max(1, 80 - (strlen((string) $suffix) + 1))) . '-' . $suffix;
        }
    }

    private function isGlobalContext(array $payload)
    {
        $contexto = isset($payload['contexto']) ? (string) $payload['contexto'] : 'global';
        $cursoId = !empty($payload['curso_id']) ? (int) $payload['curso_id'] : 0;
        $turmaId = !empty($payload['turma_id']) ? (int) $payload['turma_id'] : 0;

        if ($cursoId > 0 || $turmaId > 0) {
            return false;
        }

        return $contexto === '' || $contexto === 'global';
    }
}

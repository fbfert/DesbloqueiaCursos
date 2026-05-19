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
            'css' => '',
            'imagem_fundo' => '',
            'logo' => '',
            'cor_fundo' => '#ffffff',
            'cor_texto' => '#111827',
            'observacoes' => '',
        );
    }

    public function save(array $input, $actorUserId = null, $ipAddress = null, $userAgent = null)
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

        try {
            if ($id > 0 && $stored) {
                $this->templateModel->update($id, $payload);
                $resultId = $id;
            } else {
                $resultId = $this->templateModel->create($payload);
            }

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
            Logger::error('certificados_template.salvar_falhou', array('message' => $exception->getMessage()));
            throw $exception;
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
        $css = isset($input['css']) ? trim((string) $input['css']) : ($stored['css'] ?? null);
        $imagemFundo = isset($input['imagem_fundo']) ? trim((string) $input['imagem_fundo']) : ($stored['imagem_fundo'] ?? null);
        $logo = isset($input['logo']) ? trim((string) $input['logo']) : ($stored['logo'] ?? null);
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
            'css' => $css !== '' ? $css : null,
            'imagem_fundo' => $imagemFundo !== '' ? $imagemFundo : null,
            'logo' => $logo !== '' ? $logo : null,
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

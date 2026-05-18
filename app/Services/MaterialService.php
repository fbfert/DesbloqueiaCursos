<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Aula;
use App\Models\Material;
use App\Models\Modulo;
use App\Support\HtmlSanitizer;
use Exception;

class MaterialService
{
    private const STATUS_VALIDOS = array('rascunho', 'publicado', 'oculto');
    private const TIPOS_VALIDOS = array(
        'arquivo_protegido',
        'link_externo',
        'video_externo',
        'embed_controlado',
    );

    private $materialModel;
    private $moduloModel;
    private $aulaModel;
    private $fileStorageService;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->materialModel = new Material();
        $this->moduloModel = new Modulo();
        $this->aulaModel = new Aula();
        $this->fileStorageService = new FileStorageService();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function listarPorContexto($cursoId, $turmaId = null, $moduloId = null, $aulaId = null)
    {
        return $this->materialModel->listForContext($cursoId, $turmaId, $moduloId, $aulaId);
    }

    public function findById($id)
    {
        return $this->materialModel->findById($id);
    }

    public function absolutePath(array $material)
    {
        if (empty($material['arquivo_caminho'])) {
            return null;
        }

        return $this->fileStorageService->privatePath($material['arquivo_caminho']);
    }

    public function salvar(array $data, ?array $arquivo = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $materialExistente = $id > 0 ? $this->materialModel->findById($id) : null;
        if ($id > 0 && !$materialExistente) {
            return array('ok' => false, 'message' => 'Material nao encontrado.');
        }

        $tipoMaterialInformado = isset($data['tipo_material']) ? (string) $data['tipo_material'] : '';
        if ($tipoMaterialInformado === '' && !empty($materialExistente['tipo_material'])) {
            $tipoMaterialInformado = (string) $materialExistente['tipo_material'];
        }
        if ($tipoMaterialInformado === '' && $arquivo && !empty($arquivo['tmp_name'])) {
            $tipoMaterialInformado = 'arquivo_protegido';
        }
        if ($tipoMaterialInformado === '' && !empty($data['url'])) {
            $tipoMaterialInformado = 'link_externo';
        }

        $tipoMaterial = $this->normalizarTipoMaterial($tipoMaterialInformado);
        if ($tipoMaterial === null) {
            return array('ok' => false, 'message' => 'Tipo de material invalido.');
        }

        $status = $this->determinarStatus($data, true);
        $cursoId = (int) $data['curso_evento_id'];
        $turmaId = !empty($data['turma_id']) ? (int) $data['turma_id'] : null;
        $aulaId = !empty($data['aula_id']) ? (int) $data['aula_id'] : 0;
        if ($aulaId <= 0) {
            return array('ok' => false, 'message' => 'Selecione uma aula para o material.');
        }

        $aula = $this->aulaModel->findById($aulaId);
        if (!$aula) {
            return array('ok' => false, 'message' => 'Aula nao encontrada para o material.');
        }

        if ((int) $aula['curso_evento_id'] !== $cursoId) {
            return array('ok' => false, 'message' => 'A aula informada nao pertence ao curso selecionado.');
        }

        $turmaAula = !empty($aula['turma_id']) ? (int) $aula['turma_id'] : null;
        if ($turmaAula !== $turmaId) {
            return array('ok' => false, 'message' => 'A aula informada nao pertence a turma selecionada.');
        }

        $moduloId = !empty($data['modulo_id']) ? (int) $data['modulo_id'] : 0;
        if ($moduloId <= 0) {
            $moduloId = !empty($aula['modulo_id']) ? (int) $aula['modulo_id'] : 0;
        }

        if ($moduloId <= 0) {
            return array('ok' => false, 'message' => 'Selecione um modulo valido para o material.');
        }

        $modulo = $this->moduloModel->findById($moduloId);
        if (!$modulo) {
            return array('ok' => false, 'message' => 'Modulo nao encontrado para o material.');
        }

        if ((int) $modulo['curso_evento_id'] !== $cursoId) {
            return array('ok' => false, 'message' => 'Modulo informado nao pertence ao curso selecionado.');
        }

        $turmaModulo = !empty($modulo['turma_id']) ? (int) $modulo['turma_id'] : null;
        if ($turmaModulo !== $turmaId) {
            return array('ok' => false, 'message' => 'Modulo informado nao pertence a turma selecionada.');
        }

        if (!empty($aula['modulo_id']) && (int) $aula['modulo_id'] !== $moduloId) {
            return array('ok' => false, 'message' => 'A aula selecionada nao pertence ao modulo informado.');
        }

        $titulo = trim((string) $data['titulo']);
        if ($titulo === '') {
            return array('ok' => false, 'message' => 'Informe o titulo do material.');
        }

        $descricao = isset($data['descricao']) ? HtmlSanitizer::clean((string) $data['descricao'], 'basic') : null;
        $url = isset($data['url']) ? trim((string) $data['url']) : '';
        $arquivoAtual = $materialExistente;
        $requerArquivo = $this->tipoMaterialEhArquivo($tipoMaterial);
        $requerUrl = $this->tipoMaterialRequerUrl($tipoMaterial);

        if ($requerUrl && $url === '') {
            return array('ok' => false, 'message' => 'Informe a URL do material.');
        }

        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            return array('ok' => false, 'message' => 'URL do material invalida.');
        }

        if ($tipoMaterial === 'embed_controlado' && !$this->embedUrlPermitida($url)) {
            return array('ok' => false, 'message' => 'Embed permitido apenas para domínios autorizados.');
        }

        $payload = array(
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId,
            'modulo_id' => $moduloId,
            'aula_id' => $aulaId,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'tipo_material' => $tipoMaterial,
            'arquivo_caminho' => null,
            'arquivo_nome_original' => null,
            'arquivo_mime_type' => null,
            'arquivo_tamanho_bytes' => null,
            'tipo_arquivo' => $this->tipoArquivoPadrao($tipoMaterial),
            'url' => $url !== '' ? $url : null,
            'visivel' => $status === 'publicado' ? 1 : 0,
            'status' => $status,
            'criado_por' => $id > 0 ? null : ($actorUserId ? (int) $actorUserId : null),
            'atualizado_por' => $actorUserId ? (int) $actorUserId : null,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        );

        if ($requerArquivo) {
            if ($arquivo && !empty($arquivo['tmp_name'])) {
                $diretorio = 'cursos/' . $cursoId . '/aulas/' . $aulaId;
                $upload = $this->fileStorageService->storeUploadedFile($arquivo, $diretorio, 'material', array(
                    'max_size_bytes' => 20 * 1024 * 1024,
                    'allowed_extensions' => array('pdf', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'txt'),
                    'allowed_mime_types' => array(
                        'application/pdf',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'text/plain',
                    ),
                ));
                $payload['arquivo_caminho'] = $upload['relative_path'];
                $payload['arquivo_nome_original'] = $upload['original_name'];
                $payload['arquivo_mime_type'] = $upload['mime_type'];
                $payload['arquivo_tamanho_bytes'] = $upload['size'];
                $payload['tipo_arquivo'] = strtolower((string) pathinfo($upload['original_name'], PATHINFO_EXTENSION));
            } elseif ($arquivoAtual && !empty($arquivoAtual['arquivo_caminho'])) {
                $payload['arquivo_caminho'] = $arquivoAtual['arquivo_caminho'];
                $payload['arquivo_nome_original'] = $arquivoAtual['arquivo_nome_original'];
                $payload['arquivo_mime_type'] = $arquivoAtual['arquivo_mime_type'];
                $payload['arquivo_tamanho_bytes'] = $arquivoAtual['arquivo_tamanho_bytes'];
                $payload['tipo_arquivo'] = !empty($arquivoAtual['tipo_arquivo']) ? $arquivoAtual['tipo_arquivo'] : $payload['tipo_arquivo'];
            } else {
                return array('ok' => false, 'message' => 'Arquivo do material nao informado.');
            }
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $antigo = $materialExistente;
                $this->materialModel->update($payload, $id);
                $acao = 'area_curso.material.atualizado';
            } else {
                $antigo = null;
                $id = $this->materialModel->create($payload);
                $acao = 'area_curso.material.criado';
            }

            $this->auditService->record($acao, 'material', $id, array('anterior' => $antigo, 'novo' => $payload), $actorUserId, $ipAddress, $userAgent);
            Logger::info($acao, array('material_id' => $id));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.material.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function prepararAcesso(array $material)
    {
        $tipoMaterial = isset($material['tipo_material']) ? (string) $material['tipo_material'] : 'arquivo_protegido';

        if ($this->tipoMaterialEhArquivo($tipoMaterial)) {
            $absolutePath = $this->absolutePath($material);
            if (!$absolutePath) {
                return null;
            }

            return array(
                'tipo' => 'arquivo',
                'absolute_path' => $absolutePath,
                'content_type' => !empty($material['arquivo_mime_type']) ? $material['arquivo_mime_type'] : 'application/octet-stream',
                'filename' => basename($material['arquivo_nome_original'] ?: $material['arquivo_caminho']),
            );
        }

        if (!empty($material['url'])) {
            return array(
                'tipo' => 'url',
                'url' => $material['url'],
            );
        }

        return null;
    }

    public function excluir($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $material = $this->materialModel->findById($id);
        if (!$material) {
            return array('ok' => false, 'message' => 'Material nao encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('material', $id, $justificativa, $material, $actorUserId, $ipAddress, $userAgent);
            $this->materialModel->softDelete($id);
            $this->auditService->record('area_curso.material.excluido', 'material', $id, array('justificativa' => $justificativa), $actorUserId, $ipAddress, $userAgent);
            Logger::info('area_curso.material.excluido', array('material_id' => $id));
            $pdo->commit();
            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.material.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    private function normalizarTipoMaterial($tipoMaterial)
    {
        $tipoMaterial = strtolower(trim((string) $tipoMaterial));

        $mapa = array(
            'arquivo' => 'arquivo_protegido',
            'arquivo_protegido' => 'arquivo_protegido',
            'link' => 'link_externo',
            'link_externo' => 'link_externo',
            'video' => 'video_externo',
            'video_externo' => 'video_externo',
            'embed' => 'embed_controlado',
            'embed_controlado' => 'embed_controlado',
        );

        return isset($mapa[$tipoMaterial]) ? $mapa[$tipoMaterial] : null;
    }

    private function tipoMaterialEhArquivo($tipoMaterial)
    {
        return $tipoMaterial === 'arquivo_protegido';
    }

    private function tipoMaterialRequerUrl($tipoMaterial)
    {
        return in_array($tipoMaterial, array('link_externo', 'video_externo', 'embed_controlado'), true);
    }

    private function tipoArquivoPadrao($tipoMaterial)
    {
        switch ($tipoMaterial) {
            case 'link_externo':
                return 'link';
            case 'video_externo':
                return 'video';
            case 'embed_controlado':
                return 'embed';
            default:
                return 'arquivo';
        }
    }

    private function determinarStatus(array $data, $defaultPublicada = false)
    {
        if (isset($data['status']) && $data['status'] !== '') {
            return $this->normalizarStatus($data['status'], $defaultPublicada);
        }

        if (array_key_exists('visivel', $data)) {
            return !empty($data['visivel']) ? 'publicado' : 'oculto';
        }

        return $defaultPublicada ? 'publicado' : 'rascunho';
    }

    private function normalizarStatus($status, $defaultPublicada = false)
    {
        $status = is_string($status) ? trim($status) : '';

        if ($status === '') {
            return $defaultPublicada ? 'publicado' : 'rascunho';
        }

        if (!in_array($status, self::STATUS_VALIDOS, true)) {
            return $defaultPublicada ? 'publicado' : 'rascunho';
        }

        return $status;
    }

    private function embedUrlPermitida($url)
    {
        if ($url === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        $host = strtolower((string) $host);
        $permitidos = array(
            'youtube.com',
            'www.youtube.com',
            'youtu.be',
            'youtube-nocookie.com',
            'www.youtube-nocookie.com',
            'vimeo.com',
            'www.vimeo.com',
            'player.vimeo.com',
            'drive.google.com',
            'docs.google.com',
        );

        foreach ($permitidos as $permitido) {
            if ($host === $permitido || substr($host, -strlen('.' . $permitido)) === '.' . $permitido) {
                return true;
            }
        }

        return false;
    }
}



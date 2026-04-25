<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Aula;
use App\Models\Material;
use App\Models\Modulo;
use Exception;

class MaterialService
{
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
        $payload = array(
            'curso_evento_id' => (int) $data['curso_evento_id'],
            'turma_id' => !empty($data['turma_id']) ? (int) $data['turma_id'] : null,
            'modulo_id' => !empty($data['modulo_id']) ? (int) $data['modulo_id'] : null,
            'aula_id' => !empty($data['aula_id']) ? (int) $data['aula_id'] : null,
            'titulo' => trim((string) $data['titulo']),
            'descricao' => isset($data['descricao']) ? trim((string) $data['descricao']) : null,
            'tipo_arquivo' => isset($data['tipo_arquivo']) ? trim((string) $data['tipo_arquivo']) : 'outro',
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        );

        $materialExistente = $id > 0 ? $this->materialModel->findById($id) : null;
        if ($id > 0 && !$materialExistente) {
            return array('ok' => false, 'message' => 'Material nao encontrado.');
        }

        if ($payload['curso_evento_id'] <= 0) {
            return array('ok' => false, 'message' => 'Curso invalido para o material.');
        }

        $validacaoContexto = $this->validarRelacionamentos($payload);
        if (empty($validacaoContexto['ok'])) {
            return $validacaoContexto;
        }

        if ($arquivo && !empty($arquivo['tmp_name'])) {
            $diretorio = 'area-curso/curso-' . (int) $payload['curso_evento_id'];
            if (!empty($payload['turma_id'])) {
                $diretorio .= '/turma-' . (int) $payload['turma_id'];
            }
            $upload = $this->fileStorageService->storeUploadedFile($arquivo, $diretorio, 'material', array(
                'max_size_bytes' => 20 * 1024 * 1024,
                'allowed_extensions' => array('pdf', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'odt', 'ods', 'odp', 'txt', 'zip'),
                'allowed_mime_types' => array(
                    'application/pdf',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/csv',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.oasis.opendocument.text',
                    'application/vnd.oasis.opendocument.spreadsheet',
                    'application/vnd.oasis.opendocument.presentation',
                    'text/plain',
                    'application/zip',
                    'application/x-zip-compressed',
                ),
            ));
            $payload['arquivo_caminho'] = $upload['relative_path'];
            $payload['arquivo_nome_original'] = $upload['original_name'];
            $payload['arquivo_mime_type'] = $upload['mime_type'];
            $payload['arquivo_tamanho_bytes'] = $upload['size'];
        } elseif ($materialExistente) {
            $payload['arquivo_caminho'] = $materialExistente['arquivo_caminho'];
            $payload['arquivo_nome_original'] = $materialExistente['arquivo_nome_original'];
            $payload['arquivo_mime_type'] = $materialExistente['arquivo_mime_type'];
            $payload['arquivo_tamanho_bytes'] = $materialExistente['arquivo_tamanho_bytes'];
        } else {
            return array('ok' => false, 'message' => 'Arquivo do material nao informado.');
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
            Logger::error('area_curso.material.falhou', array('message' => $exception->getMêssage()));
            throw $exception;
        }
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
            Logger::error('area_curso.material.excluir_falhou', array('message' => $exception->getMêssage()));
            throw $exception;
        }
    }

    private function validarRelacionamentos(array $payload)
    {
        $turmaId = !empty($payload['turma_id']) ? (int) $payload['turma_id'] : null;

        if (!empty($payload['modulo_id'])) {
            $modulo = $this->moduloModel->findById((int) $payload['modulo_id']);
            if (!$modulo) {
                return array('ok' => false, 'message' => 'Modulo nao encontrado para o material.');
            }

            if ((int) $modulo['curso_evento_id'] !== (int) $payload['curso_evento_id']) {
                return array('ok' => false, 'message' => 'Modulo informado nao pertence ao curso selecionado.');
            }

            $turmaModulo = !empty($modulo['turma_id']) ? (int) $modulo['turma_id'] : null;
            if ($turmaModulo !== $turmaId) {
                return array('ok' => false, 'message' => 'Modulo informado nao pertence a turma selecionada.');
            }
        }

        if (!empty($payload['aula_id'])) {
            $aula = $this->aulaModel->findById((int) $payload['aula_id']);
            if (!$aula) {
                return array('ok' => false, 'message' => 'Aula nao encontrada para o material.');
            }

            if ((int) $aula['curso_evento_id'] !== (int) $payload['curso_evento_id']) {
                return array('ok' => false, 'message' => 'Aula informada nao pertence ao curso selecionado.');
            }

            $turmaAula = !empty($aula['turma_id']) ? (int) $aula['turma_id'] : null;
            if ($turmaAula !== $turmaId) {
                return array('ok' => false, 'message' => 'Aula informada nao pertence a turma selecionada.');
            }

            if (!empty($payload['modulo_id']) && (int) $aula['modulo_id'] !== (int) $payload['modulo_id']) {
                return array('ok' => false, 'message' => 'A aula informada nao pertence ao modulo selecionado.');
            }
        }

        return array('ok' => true);
    }
}


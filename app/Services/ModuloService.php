<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Modulo;
use Exception;

class ModuloService
{
    private const STATUS_VALIDOS = array('rascunho', 'publicado', 'oculto');

    private $moduloModel;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->moduloModel = new Modulo();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function listarPorContexto($cursoId, $turmaId = null)
    {
        return $this->moduloModel->listForContext($cursoId, $turmaId);
    }

    public function salvar(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $status = $this->determinarStatus($data, true);
        $payload = array(
            'curso_evento_id' => (int) $data['curso_evento_id'],
            'turma_id' => !empty($data['turma_id']) ? (int) $data['turma_id'] : null,
            'titulo' => trim((string) $data['titulo']),
            'descricao' => isset($data['descricao']) ? trim((string) $data['descricao']) : null,
            'status' => $status,
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'criado_por' => $id > 0 ? null : ($actorUserId ? (int) $actorUserId : (isset($data['criado_por']) ? (int) $data['criado_por'] : null)),
            'atualizado_por' => isset($data['atualizado_por']) ? (int) $data['atualizado_por'] : $actorUserId,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        );

        if ($payload['curso_evento_id'] <= 0) {
            return array('ok' => false, 'message' => 'Curso invalido para o modulo.');
        }

        if (empty($payload['titulo'])) {
            return array('ok' => false, 'message' => 'Informe o titulo do modulo.');
        }

        if ($id > 0 && !$this->moduloModel->findById($id)) {
            return array('ok' => false, 'message' => 'Modulo nao encontrado.');
        }

        $payload['visivel'] = $payload['status'] === 'publicado' ? 1 : 0;
        $payload['atualizado_por'] = $actorUserId ? (int) $actorUserId : $payload['atualizado_por'];
        if ($id > 0) {
            $payload['criado_por'] = null;
        } elseif (!$payload['criado_por']) {
            $payload['criado_por'] = $actorUserId ? (int) $actorUserId : null;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $antigo = $this->moduloModel->findById($id);
                $this->moduloModel->update($payload, $id);
                $acao = 'area_curso.modulo.atualizado';
            } else {
                $id = $this->moduloModel->create($payload);
                $antigo = null;
                $acao = 'area_curso.modulo.criado';
            }

            $this->auditService->record(
                $acao,
                'modulo',
                $id,
                array('anterior' => $antigo, 'novo' => $payload),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($acao, array('modulo_id' => $id));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.modulo.falhou', array(
                'message' => $exception->getMessage(),
            ));
            throw $exception;
        }
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

    public function excluir($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $modulo = $this->moduloModel->findById($id);
        if (!$modulo) {
            return array('ok' => false, 'message' => 'Modulo nao encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('modulo', $id, $justificativa, $modulo, $actorUserId, $ipAddress, $userAgent);
            $this->moduloModel->softDelete($id);

            $this->auditService->record(
                'area_curso.modulo.excluido',
                'modulo',
                $id,
                array('justificativa' => $justificativa),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('area_curso.modulo.excluido', array('modulo_id' => $id));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.modulo.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }
}



<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Aula;
use App\Models\Modulo;
use Exception;

class AulaService
{
    private const STATUS_VALIDOS = array('rascunho', 'publicado', 'oculto');

    private $aulaModel;
    private $moduloModel;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->aulaModel = new Aula();
        $this->moduloModel = new Modulo();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function listarPorModulo($moduloId)
    {
        return $this->aulaModel->listForModulo($moduloId);
    }

    public function salvar(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $status = $this->determinarStatus($data, true);
        $payload = array(
            'modulo_id' => (int) $data['modulo_id'],
            'curso_evento_id' => (int) $data['curso_evento_id'],
            'turma_id' => !empty($data['turma_id']) ? (int) $data['turma_id'] : null,
            'titulo' => trim((string) $data['titulo']),
            'conteudo' => isset($data['conteudo']) ? trim((string) $data['conteudo']) : null,
            'tipo' => isset($data['tipo']) ? trim((string) $data['tipo']) : 'texto',
            'url_video' => isset($data['url_video']) ? trim((string) $data['url_video']) : null,
            'duracao_minutos' => isset($data['duracao_minutos']) && $data['duracao_minutos'] !== '' ? (int) $data['duracao_minutos'] : null,
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'status' => $status,
            'criado_por' => $id > 0 ? null : ($actorUserId ? (int) $actorUserId : (isset($data['criado_por']) ? (int) $data['criado_por'] : null)),
            'atualizado_por' => isset($data['atualizado_por']) ? (int) $data['atualizado_por'] : $actorUserId,
            'obrigatoria' => !empty($data['obrigatoria']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        );

        $modulo = $this->moduloModel->findById($payload['modulo_id']);
        if (!$modulo) {
            return array('ok' => false, 'message' => 'Modulo nao encontrado para a aula.');
        }

        if ((int) $modulo['curso_evento_id'] !== (int) $payload['curso_evento_id']) {
            return array('ok' => false, 'message' => 'Modulo informado nao pertence ao curso selecionado.');
        }

        $turmaModulo = !empty($modulo['turma_id']) ? (int) $modulo['turma_id'] : null;
        $turmaPayload = !empty($payload['turma_id']) ? (int) $payload['turma_id'] : null;
        if ($turmaModulo !== $turmaPayload) {
            return array('ok' => false, 'message' => 'Modulo informado nao pertence a turma selecionada.');
        }

        if ($id > 0 && !$this->aulaModel->findById($id)) {
            return array('ok' => false, 'message' => 'Aula nao encontrada.');
        }

        if (empty($payload['titulo'])) {
            return array('ok' => false, 'message' => 'Informe o titulo da aula.');
        }

        $payload['visivel'] = $payload['status'] === 'publicado' ? 1 : 0;
        $payload['atualizado_por'] = $actorUserId ? (int) $actorUserId : $payload['atualizado_por'];
        if ($id <= 0 && !$payload['criado_por']) {
            $payload['criado_por'] = $actorUserId ? (int) $actorUserId : null;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $antigo = $this->aulaModel->findById($id);
                $this->aulaModel->update($payload, $id);
                $acao = 'area_curso.aula.atualizada';
            } else {
                $id = $this->aulaModel->create($payload);
                $antigo = null;
                $acao = 'area_curso.aula.criada';
            }

            $this->auditService->record($acao, 'aula', $id, array('anterior' => $antigo, 'novo' => $payload), $actorUserId, $ipAddress, $userAgent);
            Logger::info($acao, array('aula_id' => $id));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.aula.falhou', array('message' => $exception->getMessage()));
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
        $aula = $this->aulaModel->findById($id);
        if (!$aula) {
            return array('ok' => false, 'message' => 'Aula nao encontrada.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('aula', $id, $justificativa, $aula, $actorUserId, $ipAddress, $userAgent);
            $this->aulaModel->softDelete($id);
            $this->auditService->record('area_curso.aula.excluida', 'aula', $id, array('justificativa' => $justificativa), $actorUserId, $ipAddress, $userAgent);
            Logger::info('area_curso.aula.excluida', array('aula_id' => $id));
            $pdo->commit();
            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.aula.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }
}



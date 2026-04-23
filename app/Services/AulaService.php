<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Aula;
use Exception;

class AulaService
{
    private $aulaModel;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->aulaModel = new Aula();
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
            'obrigatoria' => !empty($data['obrigatoria']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        );

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

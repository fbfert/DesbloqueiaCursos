<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Modulo;
use Exception;

class ModuloService
{
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
        $payload = array(
            'curso_evento_id' => (int) $data['curso_evento_id'],
            'turma_id' => !empty($data['turma_id']) ? (int) $data['turma_id'] : null,
            'titulo' => trim((string) $data['titulo']),
            'descricao' => isset($data['descricao']) ? trim((string) $data['descricao']) : null,
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        );

        if ($payload['curso_evento_id'] <= 0) {
            return array('ok' => false, 'message' => 'Curso invalido para o modulo.');
        }

        if ($id > 0 && !$this->moduloModel->findById($id)) {
            return array('ok' => false, 'message' => 'Modulo nao encontrado.');
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
                'message' => $exception->getMêssage(),
            ));
            throw $exception;
        }
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
            Logger::error('area_curso.modulo.excluir_falhou', array('message' => $exception->getMêssage()));
            throw $exception;
        }
    }
}


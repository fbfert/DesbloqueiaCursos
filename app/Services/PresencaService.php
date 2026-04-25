<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Aula;
use App\Models\Inscrição;
use App\Models\Presenca;
use Exception;

class PresencaService
{
    private $presencaModel;
    private $inscricaoModel;
    private $aulaModel;
    private $aptidaoService;
    private $auditService;
    private $trashService;
    private $scopeService;

    public function __construct(array $dependencies = array())
    {
        $this->presencaModel = isset($dependencies['presencaModel']) ? $dependencies['presencaModel'] : new Presenca();
        $this->inscricaoModel = isset($dependencies['inscricaoModel']) ? $dependencies['inscricaoModel'] : new Inscrição();
        $this->aulaModel = isset($dependencies['aulaModel']) ? $dependencies['aulaModel'] : new Aula();
        $this->aptidaoService = isset($dependencies['aptidaoService']) ? $dependencies['aptidaoService'] : new AptidãoCertificadoService();
        $this->auditService = isset($dependencies['auditService']) ? $dependencies['auditService'] : new AuditService();
        $this->trashService = isset($dependencies['trashService']) ? $dependencies['trashService'] : new TrashService();
        $this->scopeService = isset($dependencies['scopeService']) ? $dependencies['scopeService'] : new ProfessorAcademicScopeService(array(
            'inscricaoModel' => $this->inscricaoModel,
            'aulaModel' => $this->aulaModel,
        ));
    }

    public function listarContexto($cursoId, $turmaId = null)
    {
        return array('presencas' => $this->presencaModel->listForContext($cursoId, $turmaId));
    }

    public function listarPorInscrição($inscricaoId)
    {
        return array('presencas' => $this->presencaModel->listForInscrição($inscricaoId));
    }

    public function registrar(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $cursoId = isset($data['curso_evento_id']) ? (int) $data['curso_evento_id'] : 0;
        $turmaId = !empty($data['turma_id']) ? (int) $data['turma_id'] : null;

        $validacaoContexto = $this->scopeService->validarContexto($cursoId, $turmaId);
        if (empty($validacaoContexto['ok'])) {
            return $validacaoContexto;
        }

        $validacaoInscrição = $this->scopeService->validarInscriçãoNoContexto(isset($data['inscricao_id']) ? (int) $data['inscricao_id'] : 0, $cursoId, $turmaId);
        if (empty($validacaoInscrição['ok'])) {
            return $validacaoInscrição;
        }

        if (!empty($data['aula_id'])) {
            $validacaoAula = $this->scopeService->validarAulaNoContexto((int) $data['aula_id'], $cursoId, $turmaId);
            if (empty($validacaoAula['ok'])) {
                return $validacaoAula;
            }

            $validacaoAulaInscrição = $this->scopeService->validarAulaInscriçãoConsistentes((int) $data['aula_id'], (int) $data['inscricao_id']);
            if (empty($validacaoAulaInscrição['ok'])) {
                return $validacaoAulaInscrição;
            }
        }

        $inscricao = $this->inscricaoModel->findById(isset($data['inscricao_id']) ? (int) $data['inscricao_id'] : 0);
        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscrição nao encontrada.');
        }

        $payload = array(
            'inscricao_id' => (int) $inscricao['id'],
            'pedido_id' => (int) $inscricao['pedido_id'],
            'pedido_item_id' => (int) $inscricao['pedido_item_id'],
            'participante_pedido_id' => (int) $inscricao['participante_pedido_id'],
            'usuario_id' => !empty($inscricao['usuario_id']) ? (int) $inscricao['usuario_id'] : null,
            'curso_evento_id' => (int) $inscricao['curso_evento_id'],
            'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
            'aula_id' => !empty($data['aula_id']) ? (int) $data['aula_id'] : null,
            'data_presenca' => isset($data['data_presenca']) && $data['data_presenca'] !== '' ? $data['data_presenca'] : date('Y-m-d'),
            'status' => isset($data['status']) ? trim((string) $data['status']) : 'presente',
            'observacao' => isset($data['observacao']) ? trim((string) $data['observacao']) : null,
            'marcado_por_usuario_id' => $actorUserId,
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $id = $this->presencaModel->upsert($payload);
            $this->auditService->record(
                'academico.presenca.registrada',
                'presenca',
                $id,
                $payload,
                $actorUserId,
                $ipAddress,
                $userAgent
            );
            Logger::info('academico.presenca.registrada', array('presenca_id' => $id));

            $this->aptidaoService->recalcularInscrição((int) $inscricao['id'], $actorUserId, $ipAddress, $userAgent, $cursoId, $turmaId);
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('academico.presenca.falhou', array('message' => $exception->getMêssage()));
            throw $exception;
        }
    }

    public function excluir($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $registro = $this->presencaModel->findById($id);
        if (!$registro) {
            return array('ok' => false, 'message' => 'Presenca nao encontrada.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('presenca', $id, $justificativa, $registro, $actorUserId, $ipAddress, $userAgent);
            $this->presencaModel->softDelete($id);
            $this->auditService->record('academico.presenca.excluida', 'presenca', $id, array('justificativa' => $justificativa), $actorUserId, $ipAddress, $userAgent);
            Logger::info('academico.presenca.excluida', array('presenca_id' => $id));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('academico.presenca.excluir_falhou', array('message' => $exception->getMêssage()));
            throw $exception;
        }
    }
}


<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\CursoEvento;
use App\Models\LmsCriterioConclusao;
use App\Models\Turma;
use Exception;

class LmsCriterioConclusaoService
{
    private const CRITERIOS_ATIVIDADES_VALIDOS = array('nenhuma', 'todas_enviadas', 'todas_corrigidas', 'media_minima');

    private $cursoModel;
    private $turmaModel;
    private $criterioModel;
    private $auditService;

    public function __construct()
    {
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->criterioModel = new LmsCriterioConclusao();
        $this->auditService = new AuditService();
    }

    public function resolver($cursoId, $turmaId = null)
    {
        $cursoId = (int) $cursoId;
        $turmaId = $turmaId !== null ? (int) $turmaId : null;

        $curso = $cursoId > 0 ? $this->cursoModel->findById($cursoId) : null;
        $turma = $turmaId !== null && $turmaId > 0 ? $this->turmaModel->findById($turmaId) : null;
        $persistido = $cursoId > 0 ? $this->criterioModel->findAtivo($cursoId, $turmaId) : null;

        $criterios = array(
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
            'exigir_progresso' => 1,
            'progresso_minimo' => 75.00,
            'exigir_atividades' => 0,
            'criterio_atividades' => 'nenhuma',
            'nota_minima_atividades' => null,
            'exigir_presenca' => 0,
            'presenca_minima' => 75.00,
            'exigir_avaliacao' => 0,
            'nota_minima_avaliacao' => null,
            'aviso_certificado_manual' => 'A conclusão exibida aqui não emite certificado automaticamente.',
            'origem' => 'padrao',
        );

        if ($curso) {
            $criterios['progresso_minimo'] = isset($curso['percentual_minimo_conclusao']) && $curso['percentual_minimo_conclusao'] !== null
                ? (float) $curso['percentual_minimo_conclusao'] : $criterios['progresso_minimo'];
            $criterios['exigir_presenca'] = !empty($curso['exige_presenca']) ? 1 : $criterios['exigir_presenca'];
            $criterios['presenca_minima'] = isset($curso['percentual_minimo_presenca']) && $curso['percentual_minimo_presenca'] !== null
                ? (float) $curso['percentual_minimo_presenca'] : $criterios['presenca_minima'];
            $criterios['exigir_avaliacao'] = !empty($curso['exige_avaliacao']) ? 1 : $criterios['exigir_avaliacao'];
            $criterios['nota_minima_avaliacao'] = isset($curso['nota_minima']) && $curso['nota_minima'] !== null
                ? (float) $curso['nota_minima'] : $criterios['nota_minima_avaliacao'];
            $criterios['origem'] = 'curso_turma';
        }

        if ($turma) {
            $criterios['progresso_minimo'] = isset($turma['percentual_minimo_conclusao']) && $turma['percentual_minimo_conclusao'] !== null
                ? (float) $turma['percentual_minimo_conclusao'] : $criterios['progresso_minimo'];
            $criterios['exigir_presenca'] = !empty($turma['exige_presenca']) ? 1 : $criterios['exigir_presenca'];
            $criterios['presenca_minima'] = isset($turma['percentual_minimo_presenca']) && $turma['percentual_minimo_presenca'] !== null
                ? (float) $turma['percentual_minimo_presenca'] : $criterios['presenca_minima'];
            $criterios['exigir_avaliacao'] = !empty($turma['exige_avaliacao']) ? 1 : $criterios['exigir_avaliacao'];
            $criterios['nota_minima_avaliacao'] = isset($turma['nota_minima']) && $turma['nota_minima'] !== null
                ? (float) $turma['nota_minima'] : $criterios['nota_minima_avaliacao'];
            $criterios['origem'] = 'curso_turma';
        }

        if ($persistido) {
            $criterios['exigir_progresso'] = !empty($persistido['exigir_progresso']) ? 1 : 0;
            $criterios['progresso_minimo'] = (float) $persistido['progresso_minimo'];
            $criterios['exigir_atividades'] = !empty($persistido['exigir_atividades']) ? 1 : 0;
            $criterios['criterio_atividades'] = (string) $persistido['criterio_atividades'];
            $criterios['nota_minima_atividades'] = $persistido['nota_minima_atividades'] !== null ? (float) $persistido['nota_minima_atividades'] : null;
            $criterios['exigir_presenca'] = !empty($persistido['exigir_presenca']) ? 1 : 0;
            $criterios['presenca_minima'] = (float) $persistido['presenca_minima'];
            $criterios['exigir_avaliacao'] = !empty($persistido['exigir_avaliacao']) ? 1 : 0;
            $criterios['nota_minima_avaliacao'] = $persistido['nota_minima_avaliacao'] !== null ? (float) $persistido['nota_minima_avaliacao'] : null;
            $criterios['origem'] = 'lms_criterios_conclusao';
        }

        return $criterios;
    }

    public function salvar(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $cursoId = isset($data['curso_id']) ? (int) $data['curso_id'] : 0;
        $turmaId = !empty($data['turma_id']) ? (int) $data['turma_id'] : null;

        if ($cursoId <= 0) {
            return array('ok' => false, 'message' => 'Curso inválido para configuração dos critérios.');
        }

        if (!$this->cursoModel->findById($cursoId)) {
            return array('ok' => false, 'message' => 'Curso não encontrado.');
        }

        if ($turmaId !== null) {
            $turma = $this->turmaModel->findById($turmaId);
            if (!$turma || (int) $turma['curso_evento_id'] !== $cursoId) {
                return array('ok' => false, 'message' => 'Turma inválida para este curso.');
            }
        }

        $payload = array(
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
            'exigir_progresso' => !empty($data['exigir_progresso']) ? 1 : 0,
            'progresso_minimo' => $this->normalizarPercentual(isset($data['progresso_minimo']) ? $data['progresso_minimo'] : 75),
            'exigir_atividades' => !empty($data['exigir_atividades']) ? 1 : 0,
            'criterio_atividades' => isset($data['criterio_atividades']) ? (string) $data['criterio_atividades'] : 'nenhuma',
            'nota_minima_atividades' => $this->normalizarNotaOpcional(isset($data['nota_minima_atividades']) ? $data['nota_minima_atividades'] : null),
            'exigir_presenca' => !empty($data['exigir_presenca']) ? 1 : 0,
            'presenca_minima' => $this->normalizarPercentual(isset($data['presenca_minima']) ? $data['presenca_minima'] : 75),
            'exigir_avaliacao' => !empty($data['exigir_avaliacao']) ? 1 : 0,
            'nota_minima_avaliacao' => $this->normalizarNotaOpcional(isset($data['nota_minima_avaliacao']) ? $data['nota_minima_avaliacao'] : null),
            'criado_por' => $actorUserId !== null ? (int) $actorUserId : null,
            'atualizado_por' => $actorUserId !== null ? (int) $actorUserId : null,
        );

        if (!in_array($payload['criterio_atividades'], self::CRITERIOS_ATIVIDADES_VALIDOS, true)) {
            return array('ok' => false, 'message' => 'Critério de atividades inválido.');
        }

        if (empty($payload['exigir_atividades'])) {
            $payload['criterio_atividades'] = 'nenhuma';
            $payload['nota_minima_atividades'] = null;
        }

        if ($payload['criterio_atividades'] !== 'media_minima') {
            $payload['nota_minima_atividades'] = null;
        }

        if (empty($payload['exigir_avaliacao'])) {
            $payload['nota_minima_avaliacao'] = null;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $ativoAtual = $this->criterioModel->findAtivo($cursoId, $turmaId);
            if ($ativoAtual) {
                $this->criterioModel->updateAtivo((int) $ativoAtual['id'], $payload);
                $id = (int) $ativoAtual['id'];
            } else {
                $id = $this->criterioModel->create($payload);
            }

            $this->auditService->record(
                'lms.criterios_conclusao.salvos',
                'lms_criterios_conclusao',
                $id,
                $payload,
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('lms.criterios_conclusao.salvos', array(
                'criterio_id' => $id,
                'curso_id' => $cursoId,
                'turma_id' => $turmaId,
            ));

            $pdo->commit();
            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('lms.criterios_conclusao.falhou', array('message' => $exception->getMessage()));
            return array('ok' => false, 'message' => 'Não foi possível salvar os critérios de conclusão. Verifique se a migration foi aplicada.');
        }
    }

    private function normalizarPercentual($valor)
    {
        $numero = (float) $valor;
        if ($numero < 0) {
            $numero = 0;
        }
        if ($numero > 100) {
            $numero = 100;
        }
        return round($numero, 2);
    }

    private function normalizarNotaOpcional($valor)
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $numero = (float) $valor;
        if ($numero < 0) {
            $numero = 0;
        }
        return round($numero, 2);
    }
}

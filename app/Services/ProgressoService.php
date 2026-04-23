<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Aula;
use App\Models\CursoEvento;
use App\Models\Inscricao;
use App\Models\Modulo;
use App\Models\Turma;
use App\Models\ProgressoUsuarioAula;
use App\Models\ProgressoUsuarioModulo;
use Exception;

class ProgressoService
{
    private $inscricaoModel;
    private $cursoModel;
    private $turmaModel;
    private $moduloModel;
    private $aulaModel;
    private $progressoModuloModel;
    private $progressoAulaModel;
    private $aptidaoService;
    private $auditService;

    public function __construct()
    {
        $this->inscricaoModel = new Inscricao();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->moduloModel = new Modulo();
        $this->aulaModel = new Aula();
        $this->progressoModuloModel = new ProgressoUsuarioModulo();
        $this->progressoAulaModel = new ProgressoUsuarioAula();
        $this->aptidaoService = new AptidaoCertificadoService();
        $this->auditService = new AuditService();
    }

    public function concluirAula($inscricaoId, $aulaId, $usuarioId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $inscricao = $this->inscricaoModel->findById($inscricaoId);
        $aula = $this->aulaModel->findById($aulaId);

        if (!$inscricao || !$aula) {
            return array('ok' => false, 'message' => 'Inscricao ou aula nao encontrada.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->progressoAulaModel->upsert(array(
                'inscricao_id' => $inscricaoId,
                'usuario_id' => $usuarioId,
                'curso_evento_id' => $inscricao['curso_evento_id'],
                'turma_id' => isset($inscricao['turma_id']) ? $inscricao['turma_id'] : null,
                'aula_id' => $aulaId,
                'percentual' => 100,
                'concluido' => 1,
                'visualizado_em' => date('Y-m-d H:i:s'),
                'concluido_em' => date('Y-m-d H:i:s'),
            ));

            $this->recalcularInscricaoInterno($inscricaoId, $usuarioId, $actorUserId, $ipAddress, $userAgent);

            $this->auditService->record(
                'area_curso.aula.concluida',
                'inscricao',
                $inscricaoId,
                array('aula_id' => $aulaId),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('area_curso.aula.concluida', array('inscricao_id' => $inscricaoId, 'aula_id' => $aulaId));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.aula.concluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function concluirModulo($inscricaoId, $moduloId, $usuarioId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $inscricao = $this->inscricaoModel->findById($inscricaoId);
        $modulo = $this->moduloModel->findById($moduloId);

        if (!$inscricao || !$modulo) {
            return array('ok' => false, 'message' => 'Inscricao ou modulo nao encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->progressoModuloModel->upsert(array(
                'inscricao_id' => $inscricaoId,
                'usuario_id' => $usuarioId,
                'curso_evento_id' => $inscricao['curso_evento_id'],
                'turma_id' => isset($inscricao['turma_id']) ? $inscricao['turma_id'] : null,
                'modulo_id' => $moduloId,
                'percentual' => 100,
                'concluido' => 1,
                'concluido_em' => date('Y-m-d H:i:s'),
            ));

            $this->recalcularInscricaoInterno($inscricaoId, $usuarioId, $actorUserId, $ipAddress, $userAgent);

            $this->auditService->record(
                'area_curso.modulo.concluido',
                'inscricao',
                $inscricaoId,
                array('modulo_id' => $moduloId),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('area_curso.modulo.concluido', array('inscricao_id' => $inscricaoId, 'modulo_id' => $moduloId));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.modulo.concluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function recalcularInscricao($inscricaoId, $usuarioId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->recalcularInscricaoInterno($inscricaoId, $usuarioId, $actorUserId, $ipAddress, $userAgent);
    }

    private function recalcularInscricaoInterno($inscricaoId, $usuarioId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $inscricao = $this->inscricaoModel->findById($inscricaoId);
        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscricao nao encontrada.');
        }

        $curso = $this->cursoModel->findById($inscricao['curso_evento_id']);
        $turma = !empty($inscricao['turma_id']) ? $this->turmaModel->findById($inscricao['turma_id']) : null;
        $config = $this->resolverConfiguracao($curso, $turma);
        $modulos = $this->moduloModel->listForContext($inscricao['curso_evento_id'], !empty($inscricao['turma_id']) ? $inscricao['turma_id'] : null);
        $totalAulas = 0;
        $aulasConcluidas = 0;
        $totalModulos = 0;
        $modulosConcluidos = 0;

        foreach ($modulos as $modulo) {
            if (empty($modulo['visivel'])) {
                continue;
            }

            $totalModulos++;
            $aulas = $this->aulaModel->listForModulo($modulo['id']);
            $totalAulasModulo = 0;
            $aulasConcluidasModulo = 0;

            foreach ($aulas as $aula) {
                if (empty($aula['visivel'])) {
                    continue;
                }

                $totalAulas++;
                $totalAulasModulo++;
                $progressoAula = $this->progressoAulaModel->findByContext($inscricaoId, $usuarioId, $aula['id']);
                if ($progressoAula && !empty($progressoAula['concluido'])) {
                    $aulasConcluidas++;
                    $aulasConcluidasModulo++;
                }
            }

            $progressoModulo = $this->progressoModuloModel->findByContext($inscricaoId, $usuarioId, $modulo['id']);
            if ($progressoModulo && !empty($progressoModulo['concluido'])) {
                $modulosConcluidos++;
            } elseif ($totalAulasModulo > 0 && $aulasConcluidasModulo >= $totalAulasModulo) {
                $modulosConcluidos++;
            }
        }

        $progressoBase = isset($config['progresso_base']) && $config['progresso_base'] === 'modulos' ? 'modulos' : 'aulas';
        if ($progressoBase === 'modulos') {
            $percentual = $totalModulos > 0 ? round(($modulosConcluidos / $totalModulos) * 100, 2) : 0.00;
        } else {
            $percentual = $totalAulas > 0 ? round(($aulasConcluidas / $totalAulas) * 100, 2) : 0.00;
        }

        $percentualMinimo = isset($config['percentual_minimo_conclusao']) ? (float) $config['percentual_minimo_conclusao'] : 75.00;
        $concluidaEm = $percentual >= $percentualMinimo ? date('Y-m-d H:i:s') : null;

        $this->inscricaoModel->updateAcademico($inscricaoId, array(
            'percentual_progresso' => $percentual,
            'concluida_em' => $concluidaEm,
        ));

        $this->aptidaoService->recalcularInscricao($inscricaoId, $actorUserId, $ipAddress, $userAgent);

        $this->auditService->record(
            'area_curso.progresso.recalculado',
            'inscricao',
            $inscricaoId,
            array(
                'percentual_progresso' => $percentual,
                'progresso_base' => $progressoBase,
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('area_curso.progresso.recalculado', array(
            'inscricao_id' => $inscricaoId,
            'percentual' => $percentual,
        ));

        return array('ok' => true, 'percentual' => $percentual);
    }

    private function resolverConfiguracao(?array $curso = null, ?array $turma = null)
    {
        $config = array(
            'exige_presenca' => 0,
            'percentual_minimo_presenca' => 75.00,
            'percentual_minimo_conclusao' => 75.00,
            'exige_avaliacao' => 0,
            'nota_minima' => 70.00,
            'progresso_base' => 'aulas',
        );

        if ($curso) {
            foreach ($config as $campo => $valor) {
                if (isset($curso[$campo]) && $curso[$campo] !== null) {
                    $config[$campo] = $curso[$campo];
                }
            }
        }

        if ($turma) {
            foreach ($config as $campo => $valor) {
                if (isset($turma[$campo]) && $turma[$campo] !== null) {
                    $config[$campo] = $turma[$campo];
                }
            }
        }

        return $config;
    }
}

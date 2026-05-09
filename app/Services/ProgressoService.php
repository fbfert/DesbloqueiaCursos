<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Aula;
use App\Models\CursoEvento;
use App\Models\Inscricao;
use App\Models\Pedido;
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
    private $pedidoModel;
    private $progressoModuloModel;
    private $progressoAulaModel;
    private $aptidaoService;
    private $auditService;
    private $rbacService;

    public function __construct()
    {
        $this->inscricaoModel = new Inscricao();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->moduloModel = new Modulo();
        $this->aulaModel = new Aula();
        $this->pedidoModel = new Pedido();
        $this->progressoModuloModel = new ProgressoUsuarioModulo();
        $this->progressoAulaModel = new ProgressoUsuarioAula();
        $this->aptidaoService = new AptidaoCertificadoService();
        $this->auditService = new AuditService();
        $this->rbacService = new RbacService();
    }

    public function resumoAluno($inscricaoId, $usuarioId)
    {
        $inscricao = $this->inscricaoModel->findById($inscricaoId);
        if (!$inscricao || !$this->inscricaoPertenceAoUsuario($inscricao, $usuarioId)) {
            return array(
                'total_aulas_publicadas' => 0,
                'aulas_concluidas' => 0,
                'total_modulos_publicados' => 0,
                'modulos_concluidos' => 0,
                'percentual' => 0.00,
                'aulas_concluidas_ids' => array(),
                'modulos_concluidos_ids' => array(),
            );
        }

        $turmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null;
        $modulos = $this->moduloModel->listForContext((int) $inscricao['curso_evento_id'], $turmaId);
        $totalAulas = 0;
        $aulasConcluidas = 0;
        $totalModulos = 0;
        $modulosConcluidos = 0;
        $aulasConcluidasIds = array();
        $modulosConcluidosIds = array();

        foreach ($modulos as $modulo) {
            if (!$this->conteudoPublicado($modulo)) {
                continue;
            }

            $totalModulos++;
            $aulas = $this->aulaModel->listForModulo($modulo['id']);
            $aulasConcluidasModulo = 0;
            $totalAulasModulo = 0;

            foreach ($aulas as $aula) {
                if (!$this->conteudoPublicado($aula)) {
                    continue;
                }

                $totalAulasModulo++;
                $totalAulas++;

                $progressoAula = $this->progressoAulaModel->findByContext($inscricaoId, $usuarioId, $aula['id']);
                if ($progressoAula && !empty($progressoAula['concluido'])) {
                    $aulasConcluidas++;
                    $aulasConcluidasModulo++;
                    $aulasConcluidasIds[] = (int) $aula['id'];
                }
            }

            $progressoModulo = $this->progressoModuloModel->findByContext($inscricaoId, $usuarioId, $modulo['id']);
            if ($progressoModulo && !empty($progressoModulo['concluido'])) {
                $modulosConcluidos++;
                $modulosConcluidosIds[] = (int) $modulo['id'];
            } elseif ($totalAulasModulo > 0 && $aulasConcluidasModulo >= $totalAulasModulo) {
                $modulosConcluidos++;
                $modulosConcluidosIds[] = (int) $modulo['id'];
            }
        }

        $percentual = $totalAulas > 0 ? round(($aulasConcluidas / $totalAulas) * 100, 2) : 0.00;

        return array(
            'total_aulas_publicadas' => $totalAulas,
            'aulas_concluidas' => $aulasConcluidas,
            'total_modulos_publicados' => $totalModulos,
            'modulos_concluidos' => $modulosConcluidos,
            'percentual' => $percentual,
            'aulas_concluidas_ids' => array_values(array_unique($aulasConcluidasIds)),
            'modulos_concluidos_ids' => array_values(array_unique($modulosConcluidosIds)),
        );
    }

    public function concluirAula($inscricaoId, $aulaId, $usuarioId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $inscricao = $this->inscricaoModel->findById($inscricaoId);
        $aula = $this->aulaModel->findById($aulaId);

        if (!$inscricao || !$aula) {
            return array('ok' => false, 'message' => 'Inscricao ou aula nao encontrada.');
        }

        if (!$this->inscricaoPertenceAoUsuario($inscricao, $usuarioId)) {
            $this->registrarAcessoNegado('area_curso.aula.negado', $inscricaoId, $usuarioId, $aulaId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para concluir esta aula.');
        }

        if (!$this->aulaPertenceAoContexto($aula, $inscricao)) {
            $this->registrarAcessoNegado('area_curso.aula.contexto_invalido', $inscricaoId, $usuarioId, $aulaId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Aula nao pertence ao contexto desta inscricao.');
        }

        $modulo = $this->moduloModel->findById((int) $aula['modulo_id']);
        if (!$modulo || !$this->conteudoPublicado($modulo) || !$this->conteudoPublicado($aula)) {
            $this->registrarAcessoNegado('area_curso.aula.nao_publicada', $inscricaoId, $usuarioId, $aulaId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Aula nao disponivel para conclusao.');
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

        if (!$this->inscricaoPertenceAoUsuario($inscricao, $usuarioId)) {
            $this->registrarAcessoNegado('area_curso.modulo.negado', $inscricaoId, $usuarioId, $moduloId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para concluir este modulo.');
        }

        if (!$this->moduloPertenceAoContexto($modulo, $inscricao)) {
            $this->registrarAcessoNegado('area_curso.modulo.contexto_invalido', $inscricaoId, $usuarioId, $moduloId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Modulo nao pertence ao contexto desta inscricao.');
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
            if (!$this->conteudoPublicado($modulo)) {
                continue;
            }

            $totalModulos++;
            $aulas = $this->aulaModel->listForModulo($modulo['id']);
            $totalAulasModulo = 0;
            $aulasConcluidasModulo = 0;

            foreach ($aulas as $aula) {
                if (!$this->conteudoPublicado($aula)) {
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

    private function inscricaoPertenceAoUsuario(array $inscricao, $usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        if ($this->rbacService->userHasPermission($usuarioId, 'pedidos.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'pedidos.gerenciar')
            || $this->rbacService->userHasPermission($usuarioId, 'financeiro.ver')) {
            return true;
        }

        if (!empty($inscricao['usuario_id']) && (int) $inscricao['usuario_id'] === (int) $usuarioId) {
            return $this->inscricaoTemAcessoComercial($inscricao);
        }

        $pedido = $this->pedidoModel->findById((int) $inscricao['pedido_id']);
        if (!$pedido) {
            return false;
        }

        if ((int) $pedido['comprador_usuario_id'] !== (int) $usuarioId && (int) $pedido['pagador_usuario_id'] !== (int) $usuarioId) {
            return false;
        }

        return $this->inscricaoTemAcessoComercial($inscricao);
    }

    private function registrarAcessoNegado($evento, $inscricaoId, $usuarioId, $recursoId = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $payload = array(
            'inscricao_id' => $inscricaoId,
            'usuario_id' => $usuarioId,
            'recurso_id' => $recursoId,
        );

        $this->auditService->record($evento, 'inscricao', $inscricaoId, $payload, $actorUserId, $ipAddress, $userAgent);
        Logger::error($evento, $payload);
    }

    private function conteudoPublicado(array $registro)
    {
        if (isset($registro['status']) && $registro['status'] !== '') {
            return (string) $registro['status'] === 'publicado';
        }

        if (array_key_exists('visivel', $registro)) {
            return !empty($registro['visivel']);
        }

        return true;
    }

    private function inscricaoTemAcessoComercial(array $inscricao)
    {
        $statusInscricao = isset($inscricao['status']) ? (string) $inscricao['status'] : '';
        if (!in_array($statusInscricao, array('ativa', 'em_andamento', 'concluida', 'concluida_sem_certificado', 'certificado_emitido'), true)) {
            return false;
        }

        $pedidoStatus = isset($inscricao['pedido_status']) ? (string) $inscricao['pedido_status'] : '';
        $comprovanteStatus = isset($inscricao['comprovante_status']) ? (string) $inscricao['comprovante_status'] : '';

        if (in_array($pedidoStatus, array('aprovado', 'pago'), true)) {
            return true;
        }

        return $comprovanteStatus === 'aprovado';
    }

    private function aulaPertenceAoContexto(array $aula, array $inscricao)
    {
        if ((int) $aula['curso_evento_id'] !== (int) $inscricao['curso_evento_id']) {
            return false;
        }

        if (!empty($aula['turma_id'])) {
            if (empty($inscricao['turma_id']) || (int) $aula['turma_id'] !== (int) $inscricao['turma_id']) {
                return false;
            }
        }

        if (!empty($aula['modulo_id'])) {
            $modulo = $this->moduloModel->findById((int) $aula['modulo_id']);
            if (!$modulo) {
                return false;
            }

            return $this->moduloPertenceAoContexto($modulo, $inscricao);
        }

        return true;
    }

    private function moduloPertenceAoContexto(array $modulo, array $inscricao)
    {
        if ((int) $modulo['curso_evento_id'] !== (int) $inscricao['curso_evento_id']) {
            return false;
        }

        if (!empty($modulo['turma_id'])) {
            if (empty($inscricao['turma_id']) || (int) $modulo['turma_id'] !== (int) $inscricao['turma_id']) {
                return false;
            }
        }

        return true;
    }
}





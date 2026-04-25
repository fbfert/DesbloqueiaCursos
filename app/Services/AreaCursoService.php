<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Helpers;
use App\Core\Logger;
use App\Models\Aula;
use App\Models\CursoEvento;
use App\Models\InstrucoesCurso;
use App\Models\Inscricao;
use App\Models\LinkExterno;
use App\Models\Material;
use App\Models\Modulo;
use App\Models\ParticipantePedido;
use App\Models\Turma;
use Exception;

class AreaCursoService
{
    private $inscricaoModel;
    private $cursoModel;
    private $turmaModel;
    private $instrucoesModel;
    private $moduloModel;
    private $aulaModel;
    private $materialModel;
    private $linkModel;
    private $participanteModel;
    private $moduloService;
    private $aulaService;
    private $materialService;
    private $progressoService;
    private $rbacService;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->inscricaoModel = new Inscricao();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->instrucoesModel = new InstrucoesCurso();
        $this->moduloModel = new Modulo();
        $this->aulaModel = new Aula();
        $this->materialModel = new Material();
        $this->linkModel = new LinkExterno();
        $this->participanteModel = new ParticipantePedido();
        $this->moduloService = new ModuloService();
        $this->aulaService = new AulaService();
        $this->materialService = new MaterialService();
        $this->progressoService = new ProgressoService();
        $this->rbacService = new RbacService();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function carregarAluno($usuarioId, $inscricaoId = null, $moduloId = null, $aulaId = null)
    {
        $inscricoes = $this->inscricaoModel->forUsuarioAprovadas($usuarioId);
        $inscricao = $this->selecionarInscricao($inscricoes, $inscricaoId);

        if (!$inscricao) {
            return array(
                'inscricoes' => $inscricoes,
                'inscricao' => null,
                'curso' => null,
                'turma' => null,
                'instrucoes' => null,
                'modulos' => array(),
                'materiais' => array(),
                'links' => array(),
                'selected_modulo' => null,
                'selected_aula' => null,
            );
        }

        $curso = $this->cursoModel->findById((int) $inscricao['curso_evento_id']);
        $turma = !empty($inscricao['turma_id']) ? $this->turmaModel->findById((int) $inscricao['turma_id']) : null;
        $contexto = $this->carregarContexto((int) $inscricao['curso_evento_id'], $turma ? (int) $turma['id'] : null);
        $contexto = $this->filtrarConteudoVisivel($contexto);
        $selectedModulo = $this->selecionarModulo($contexto['modulos'], $moduloId);
        $selectedAula = $this->selecionarAula($selectedModulo ? $selectedModulo['aulas'] : array(), $aulaId);

        return array_merge($contexto, array(
            'inscricoes' => $inscricoes,
            'inscricao' => $inscricao,
            'curso' => $curso,
            'turma' => $turma,
            'selected_modulo' => $selectedModulo,
            'selected_aula' => $selectedAula,
            'percentual_progresso' => isset($inscricao['percentual_progresso']) ? $inscricao['percentual_progresso'] : 0,
            'apto_certificado' => isset($inscricao['apto_certificado']) ? $inscricao['apto_certificado'] : 0,
        ));
    }

    public function carregarModuloAluno($usuarioId, $inscricaoId, $moduloId, $aulaId = null)
    {
        return $this->carregarAluno($usuarioId, $inscricaoId, $moduloId, $aulaId);
    }

    public function carregarProfessor($usuarioId, $cursoId = null, $turmaId = null)
    {
        $cursos = $this->cursoModel->findAccessibleByUser($usuarioId);
        $turmas = $this->turmaModel->findAccessibleByUser($usuarioId);
        $curso = $cursoId ? $this->cursoModel->findById($cursoId) : null;
        $turma = $turmaId ? $this->turmaModel->findById($turmaId) : null;

        if ($cursoId && !$this->contextoProfessorAutorizado($usuarioId, $cursoId, $turmaId)) {
            return array('curso' => null, 'turma' => null, 'cursos' => $cursos, 'turmas' => $turmas);
        }

        $contexto = $cursoId ? $this->carregarContexto($cursoId, $turmaId) : array(
            'instrucoes' => array(),
            'modulos' => array(),
            'materiais' => array(),
            'links' => array(),
        );

        return array_merge($contexto, array(
            'cursos' => $cursos,
            'turmas' => $turmas,
            'curso' => $curso,
            'turma' => $turma,
            'participantes' => $cursoId ? $this->listarParticipantes($cursoId, $turmaId) : array(),
        ));
    }

    public function carregarAdmin($cursoId = null, $turmaId = null, array $selecionados = array())
    {
        $curso = $cursoId ? $this->cursoModel->findById($cursoId) : null;
        $turma = $turmaId ? $this->turmaModel->findById($turmaId) : null;
        $contexto = $cursoId ? $this->carregarContexto($cursoId, $turmaId) : array(
            'instrucoes' => array(),
            'modulos' => array(),
            'materiais' => array(),
            'links' => array(),
        );

        return array_merge($contexto, array(
            'cursos' => $this->cursoModel->allWithCategoryAndCounts(),
            'turmas' => $this->turmaModel->allWithCourse(),
            'curso' => $curso,
            'turma' => $turma,
            'participantes' => $cursoId ? $this->listarParticipantes($cursoId, $turmaId) : array(),
            'instrucao_selecionada' => !empty($selecionados['instrucao_id']) ? $this->instrucoesModel->findById((int) $selecionados['instrucao_id']) : null,
            'modulo_selecionado' => !empty($selecionados['modulo_id']) ? $this->moduloModel->findById((int) $selecionados['modulo_id']) : null,
            'aula_selecionada' => !empty($selecionados['aula_id']) ? $this->aulaModel->findById((int) $selecionados['aula_id']) : null,
            'material_selecionado' => !empty($selecionados['material_id']) ? $this->materialModel->findById((int) $selecionados['material_id']) : null,
            'link_selecionado' => !empty($selecionados['link_id']) ? $this->linkModel->findById((int) $selecionados['link_id']) : null,
        ));
    }

    public function salvarInstrucao(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $payload = array(
            'curso_evento_id' => (int) $data['curso_evento_id'],
            'turma_id' => !empty($data['turma_id']) ? (int) $data['turma_id'] : null,
            'titulo' => trim((string) $data['titulo']),
            'conteudo' => isset($data['conteudo']) ? trim((string) $data['conteudo']) : null,
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        );

        if ($payload['curso_evento_id'] <= 0) {
            return array('ok' => false, 'message' => 'Curso invalido para a instrucao.');
        }

        if ($id > 0 && !$this->instrucoesModel->findById($id)) {
            return array('ok' => false, 'message' => 'Instrucao nao encontrada.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $antiga = $this->instrucoesModel->findById($id);
                $this->instrucoesModel->update($payload, $id);
                $acao = 'area_curso.instrucao.atualizada';
            } else {
                $antiga = null;
                $id = $this->instrucoesModel->create($payload);
                $acao = 'area_curso.instrucao.criada';
            }

            $this->auditService->record($acao, 'instrucoes_curso', $id, array('anterior' => $antiga, 'novo' => $payload), $actorUserId, $ipAddress, $userAgent);
            Logger::info($acao, array('instrucao_id' => $id));
            $pdo->commit();
            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.instrucao.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function salvarLink(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $payload = array(
            'curso_evento_id' => (int) $data['curso_evento_id'],
            'turma_id' => !empty($data['turma_id']) ? (int) $data['turma_id'] : null,
            'modulo_id' => !empty($data['modulo_id']) ? (int) $data['modulo_id'] : null,
            'aula_id' => !empty($data['aula_id']) ? (int) $data['aula_id'] : null,
            'titulo' => trim((string) $data['titulo']),
            'url' => trim((string) $data['url']),
            'tipo_link' => isset($data['tipo_link']) ? trim((string) $data['tipo_link']) : 'generico',
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        );

        if ($payload['curso_evento_id'] <= 0) {
            return array('ok' => false, 'message' => 'Curso invalido para o link.');
        }

        if ($id > 0 && !$this->linkModel->findById($id)) {
            return array('ok' => false, 'message' => 'Link nao encontrado.');
        }

        $validacaoContexto = $this->validarRelacionamentosConteudo($payload);
        if (empty($validacaoContexto['ok'])) {
            return $validacaoContexto;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $antigo = $this->linkModel->findById($id);
                $this->linkModel->update($payload, $id);
                $acao = 'area_curso.link.atualizado';
            } else {
                $antigo = null;
                $acao = 'area_curso.link.criado';
                $id = $this->linkModel->create($payload);
            }

            $this->auditService->record($acao, 'links_externos', $id, array('novo' => $payload), $actorUserId, $ipAddress, $userAgent);
            Logger::info($acao, array('link_id' => $id));
            $pdo->commit();
            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.link.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function excluir($tipo, $id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        switch ($tipo) {
            case 'instrucao':
                $registro = $this->instrucoesModel->findById($id);
                break;
            case 'modulo':
                $registro = $this->moduloModel->findById($id);
                break;
            case 'aula':
                $registro = $this->aulaModel->findById($id);
                break;
            case 'material':
                $registro = $this->materialModel->findById($id);
                break;
            case 'link':
                $registro = $this->linkModel->findById($id);
                break;
            default:
                return array('ok' => false, 'message' => 'Tipo invalido.');
        }

        if (!$registro) {
            return array('ok' => false, 'message' => 'Registro nao encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record($tipo, $id, $justificativa, $registro, $actorUserId, $ipAddress, $userAgent);

            if ($tipo === 'instrucao') {
                $this->instrucoesModel->softDelete($id);
            } elseif ($tipo === 'modulo') {
                $this->moduloModel->softDelete($id);
            } elseif ($tipo === 'aula') {
                $this->aulaModel->softDelete($id);
            } elseif ($tipo === 'material') {
                $this->materialModel->softDelete($id);
            } elseif ($tipo === 'link') {
                $this->linkModel->softDelete($id);
            }

            $this->auditService->record(
                'area_curso.excluido',
                $tipo,
                $id,
                array('justificativa' => $justificativa),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('area_curso.excluido', array('tipo' => $tipo, 'id' => $id));
            $pdo->commit();
            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function listarParticipantes($cursoId, $turmaId = null)
    {
        return $this->participanteModel->forCursoTurma($cursoId, $turmaId);
    }

    public function materialAutorizado($usuarioId, $materialId, $contexto = 'aluno')
    {
        $material = $this->materialService->findById($materialId);
        if (!$material) {
            return null;
        }

        if ($contexto === 'admin') {
            return $material;
        }

        if ($contexto === 'professor' && !$this->professorPodeAcessarContexto($usuarioId, (int) $material['curso_evento_id'], !empty($material['turma_id']) ? (int) $material['turma_id'] : null)) {
            return null;
        }

        if ($contexto === 'aluno') {
            $inscricoes = $this->inscricaoModel->forUsuarioAprovadas($usuarioId);
            $autorizado = false;

            foreach ($inscricoes as $inscricao) {
                if ((int) $inscricao['curso_evento_id'] !== (int) $material['curso_evento_id']) {
                    continue;
                }

                if (!empty($material['turma_id']) && !empty($inscricao['turma_id']) && (int) $material['turma_id'] !== (int) $inscricao['turma_id']) {
                    continue;
                }

                $autorizado = true;
                break;
            }

            if (!$autorizado) {
                return null;
            }
        }

        return $material;
    }

    public function contextoProfessorAutorizado($usuarioId, $cursoId, $turmaId = null)
    {
        return $this->professorPodeAcessarContexto($usuarioId, $cursoId, $turmaId);
    }

    public function registroPertenceAoContexto($tipo, $id, $cursoId, $turmaId = null)
    {
        $registro = $this->buscarRegistroPorTipo($tipo, $id);
        if (!$registro) {
            return false;
        }

        if ((int) $registro['curso_evento_id'] !== (int) $cursoId) {
            return false;
        }

        $turmaRegistro = !empty($registro['turma_id']) ? (int) $registro['turma_id'] : null;
        $turmaContexto = !empty($turmaId) ? (int) $turmaId : null;

        return $turmaRegistro === $turmaContexto;
    }

    private function carregarContexto($cursoId, $turmaId = null)
    {
        $modulos = $this->moduloService->listarPorContexto($cursoId, $turmaId);
        foreach ($modulos as &$modulo) {
            $modulo['aulas'] = $this->aulaService->listarPorModulo($modulo['id']);
            $modulo['materiais'] = $this->materialService->listarPorContexto($cursoId, $turmaId, $modulo['id'], null);
            $modulo['links'] = $this->linkModel->listForContext($cursoId, $turmaId, $modulo['id'], null);
        }
        unset($modulo);

        return array(
            'instrucoes' => $this->instrucoesModel->findByContext($cursoId, $turmaId),
            'modulos' => $modulos,
            'materiais' => $this->materialService->listarPorContexto($cursoId, $turmaId, null, null),
            'links' => $this->linkModel->listForContext($cursoId, $turmaId, null, null),
        );
    }

    private function filtrarConteudoVisivel(array $contexto)
    {
        if (!empty($contexto['instrucoes']) && empty($contexto['instrucoes']['visivel'])) {
            $contexto['instrucoes'] = null;
        }

        $modulosFiltrados = array();
        foreach ($contexto['modulos'] as $modulo) {
            if (empty($modulo['visivel'])) {
                continue;
            }

            $aulas = array();
            foreach ($modulo['aulas'] as $aula) {
                if (empty($aula['visivel'])) {
                    continue;
                }
                $aulas[] = $aula;
            }

            $materiais = array();
            foreach ($modulo['materiais'] as $material) {
                if (empty($material['visivel'])) {
                    continue;
                }
                $materiais[] = $material;
            }

            $links = array();
            foreach ($modulo['links'] as $link) {
                if (empty($link['visivel'])) {
                    continue;
                }
                $links[] = $link;
            }

            $modulo['aulas'] = $aulas;
            $modulo['materiais'] = $materiais;
            $modulo['links'] = $links;
            $modulosFiltrados[] = $modulo;
        }

        $contexto['modulos'] = $modulosFiltrados;
        $contexto['materiais'] = array_values(array_filter($contexto['materiais'], function ($material) {
            return !empty($material['visivel']);
        }));
        $contexto['links'] = array_values(array_filter($contexto['links'], function ($link) {
            return !empty($link['visivel']);
        }));

        return $contexto;
    }

    private function selecionarInscricao(array $inscricoes, $inscricaoId = null)
    {
        if (empty($inscricoes)) {
            return null;
        }

        if ($inscricaoId) {
            foreach ($inscricoes as $inscricao) {
                if ((int) $inscricao['id'] === (int) $inscricaoId) {
                    return $inscricao;
                }
            }
        }

        return $inscricoes[0];
    }

    private function selecionarModulo(array $modulos, $moduloId = null)
    {
        if (empty($modulos)) {
            return null;
        }

        if ($moduloId) {
            foreach ($modulos as $modulo) {
                if ((int) $modulo['id'] === (int) $moduloId) {
                    return $modulo;
                }
            }
        }

        return $modulos[0];
    }

    private function selecionarAula(array $aulas, $aulaId = null)
    {
        if (empty($aulas)) {
            return null;
        }

        if ($aulaId) {
            foreach ($aulas as $aula) {
                if ((int) $aula['id'] === (int) $aulaId) {
                    return $aula;
                }
            }
        }

        return $aulas[0];
    }

    private function professorPodeAcessarContexto($usuarioId, $cursoId, $turmaId = null)
    {
        $cursos = $this->cursoModel->findAccessibleByUser($usuarioId);
        foreach ($cursos as $curso) {
            if ((int) $curso['id'] === (int) $cursoId) {
                return true;
            }
        }

        if ($turmaId !== null) {
            $turmas = $this->turmaModel->findAccessibleByUser($usuarioId);
            foreach ($turmas as $turma) {
                if ((int) $turma['id'] === (int) $turmaId) {
                    return true;
                }
            }
        }

        return false;
    }

    private function validarRelacionamentosConteudo(array $payload)
    {
        $turmaId = !empty($payload['turma_id']) ? (int) $payload['turma_id'] : null;

        if (!empty($payload['modulo_id'])) {
            $modulo = $this->moduloModel->findById((int) $payload['modulo_id']);
            if (!$modulo) {
                return array('ok' => false, 'message' => 'Modulo nao encontrado para o link.');
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
                return array('ok' => false, 'message' => 'Aula nao encontrada para o link.');
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

    private function buscarRegistroPorTipo($tipo, $id)
    {
        switch ($tipo) {
            case 'instrucao':
                return $this->instrucoesModel->findById($id);
            case 'modulo':
                return $this->moduloModel->findById($id);
            case 'aula':
                return $this->aulaModel->findById($id);
            case 'material':
                return $this->materialModel->findById($id);
            case 'link':
                return $this->linkModel->findById($id);
            default:
                return null;
        }
    }
}

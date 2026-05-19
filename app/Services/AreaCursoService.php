<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Helpers;
use App\Core\Logger;
use App\Models\Aula;
use App\Models\Atividade;
use App\Models\Certificado;
use App\Models\CursoEvento;
use App\Models\InstrucoesCurso;
use App\Models\Inscricao;
use App\Models\LinkExterno;
use App\Models\Material;
use App\Models\Modulo;
use App\Models\ParticipantePedido;
use App\Models\Turma;
use App\Support\HtmlSanitizer;
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
    private $atividadeModel;
    private $certificadoModel;
    private $linkModel;
    private $participanteModel;
    private $moduloService;
    private $aulaService;
    private $materialService;
    private $atividadeService;
    private $aptidaoService;
    private $avaliacaoService;
    private $progressoService;
    private $elegibilidadeService;
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
        $this->atividadeModel = new Atividade();
        $this->certificadoModel = new Certificado();
        $this->linkModel = new LinkExterno();
        $this->participanteModel = new ParticipantePedido();
        $this->moduloService = new ModuloService();
        $this->aulaService = new AulaService();
        $this->materialService = new MaterialService();
        $this->atividadeService = new AtividadeService();
        $this->aptidaoService = new AptidaoCertificadoService();
        $this->avaliacaoService = new AvaliacaoService();
        $this->progressoService = new ProgressoService();
        $this->elegibilidadeService = new LmsElegibilidadeService();
        $this->rbacService = new RbacService();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function carregarAluno($usuarioId, $inscricaoId = null, $moduloId = null, $aulaId = null, $cursoId = null, $turmaId = null, $atividadeId = null)
    {
        $inscricoes = $this->inscricaoModel->forUsuarioAprovadas($usuarioId);
        $inscricao = $this->selecionarInscricao($inscricoes, $inscricaoId, $cursoId, $turmaId);

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
                'progresso' => $this->resumoProgressoVazio(),
            );
        }

        $curso = $this->cursoModel->findById((int) $inscricao['curso_evento_id']);
        $turma = !empty($inscricao['turma_id']) ? $this->turmaModel->findById((int) $inscricao['turma_id']) : null;
        $contexto = $this->carregarContexto((int) $inscricao['curso_evento_id'], $turma ? (int) $turma['id'] : null, (int) $usuarioId);
        $contexto = $this->filtrarConteudoVisivel($contexto);
        $selectedModulo = $this->selecionarModulo($contexto['modulos'], $moduloId);
        $selectedAula = $this->selecionarAula($selectedModulo ? $selectedModulo['aulas'] : array(), $aulaId);
        $selectedAtividade = $this->selecionarAtividade($contexto['atividades'], $atividadeId);
        if ($selectedAtividade && (empty($selectedModulo) || (int) $selectedModulo['id'] !== (int) $selectedAtividade['modulo_id'])) {
            $selectedModulo = $this->selecionarModulo($contexto['modulos'], (int) $selectedAtividade['modulo_id']);
        }
        if ($selectedAtividade && (empty($selectedAula) || (int) $selectedAula['id'] !== (int) $selectedAtividade['aula_id'])) {
            $selectedAula = $this->selecionarAula($selectedModulo ? $selectedModulo['aulas'] : array(), (int) $selectedAtividade['aula_id']);
        }
        $selectedAtividadeEntrega = !empty($selectedAtividade['entrega_usuario_id'])
            ? $this->atividadeService->detalharEntrega((int) $usuarioId, (int) $selectedAtividade['entrega_usuario_id'], 'aluno')
            : null;
        $progresso = $this->progressoService->resumoAluno((int) $inscricao['id'], (int) $usuarioId);
        $elegibilidade = $this->elegibilidadeService->calcularParaInscricao($inscricao);

        return array_merge($contexto, array(
            'inscricoes' => $inscricoes,
            'inscricao' => $inscricao,
            'curso' => $curso,
            'turma' => $turma,
            'selected_modulo' => $selectedModulo,
            'selected_aula' => $selectedAula,
            'selected_atividade' => $selectedAtividade,
            'selected_atividade_entrega' => $selectedAtividadeEntrega,
            'percentual_progresso' => isset($inscricao['percentual_progresso']) ? $inscricao['percentual_progresso'] : 0,
            'apto_certificado' => isset($inscricao['apto_certificado']) ? $inscricao['apto_certificado'] : 0,
            'progresso' => $progresso,
            'elegibilidade' => $elegibilidade,
        ));
    }

    public function carregarModuloAluno($usuarioId, $inscricaoId, $moduloId, $aulaId = null, $cursoId = null, $turmaId = null, $atividadeId = null)
    {
        return $this->carregarAluno($usuarioId, $inscricaoId, $moduloId, $aulaId, $cursoId, $turmaId, $atividadeId);
    }

    public function carregarProfessor($usuarioId, $cursoId = null, $turmaId = null, array $selecionados = array())
    {
        $cursos = $this->cursoModel->findAccessibleByUser($usuarioId);
        $turmas = $this->turmaModel->findAccessibleByUser($usuarioId);
        $curso = $cursoId ? $this->cursoModel->findAdminById($cursoId) : null;
        if ($curso) {
            $curso = $this->anexarProfessoresResponsaveisAoCurso($curso);
        }
        $turma = $turmaId ? $this->turmaModel->findById($turmaId) : null;

        if ($cursoId && !$this->contextoProfessorAutorizado($usuarioId, $cursoId, $turmaId)) {
            return array('curso' => null, 'turma' => null, 'cursos' => $cursos, 'turmas' => $turmas);
        }

        $contexto = $cursoId ? $this->carregarContexto($cursoId, $turmaId) : array(
            'instrucoes' => array(),
            'modulos' => array(),
            'materiais' => array(),
            'links' => array(),
            'atividades' => array(),
        );
        $participantesFiltros = !empty($selecionados['participantes_filtros']) && is_array($selecionados['participantes_filtros'])
            ? $selecionados['participantes_filtros']
            : array();
        $atividadeId = !empty($selecionados['atividade_id']) ? (int) $selecionados['atividade_id'] : 0;
        $atividadeSelecionada = $atividadeId > 0 ? $this->selecionarAtividade($contexto['atividades'], $atividadeId) : null;
        $entregaId = !empty($selecionados['entrega_id']) ? (int) $selecionados['entrega_id'] : 0;
        $entregaSelecionada = $entregaId > 0 ? $this->atividadeService->detalharEntrega(1, $entregaId, 'admin') : null;
        $entregaStatusFiltro = !empty($selecionados['entrega_status']) ? (string) $selecionados['entrega_status'] : '';

        return array_merge($contexto, array(
            'cursos' => $cursos,
            'turmas' => $turmas,
            'curso' => $curso,
            'turma' => $turma,
            'participantes' => $cursoId ? $this->listarParticipantes($cursoId, $turmaId, $participantesFiltros) : array(),
            'participantes_filtros' => $participantesFiltros,
            'resumo' => $cursoId ? $this->resumoContexto($cursoId, $turmaId) : $this->resumoVazio(),
            'selected_tab' => !empty($selecionados['aba']) ? (string) $selecionados['aba'] : 'visao-geral',
            'atividade_modulo_id' => !empty($selecionados['atividade_modulo_id']) ? (int) $selecionados['atividade_modulo_id'] : 0,
            'atividade_aula_id' => !empty($selecionados['atividade_aula_id']) ? (int) $selecionados['atividade_aula_id'] : 0,
            'atividade_status' => !empty($selecionados['atividade_status']) ? (string) $selecionados['atividade_status'] : '',
            'entrega_status' => $entregaStatusFiltro,
            'entrega_id' => $entregaId,
            'atividade_selecionada' => $atividadeSelecionada,
            'entrega_selecionada' => $entregaSelecionada,
            'entregas_atividade' => $atividadeSelecionada ? $this->atividadeService->listarEntregasPorAtividade((int) $atividadeSelecionada['id'], $entregaStatusFiltro !== '' ? $entregaStatusFiltro : null) : array(),
        ));
    }

    public function carregarAdmin($cursoId = null, $turmaId = null, array $selecionados = array())
    {
        $curso = $cursoId ? $this->cursoModel->findById($cursoId) : null;
        $turma = $turmaId ? $this->turmaModel->findById($turmaId) : null;
        $turmas = $cursoId ? $this->turmaModel->forCourse($cursoId) : array();
        $contexto = $cursoId ? $this->carregarContexto($cursoId, $turmaId) : array(
            'instrucoes' => array(),
            'modulos' => array(),
            'materiais' => array(),
            'links' => array(),
            'atividades' => array(),
        );
        $aba = !empty($selecionados['aba']) ? (string) $selecionados['aba'] : 'visao-geral';
        $participantesFiltros = !empty($selecionados['participantes_filtros']) && is_array($selecionados['participantes_filtros'])
            ? $selecionados['participantes_filtros']
            : array();
        $presencasFiltros = !empty($selecionados['presencas_filtros']) && is_array($selecionados['presencas_filtros'])
            ? $selecionados['presencas_filtros']
            : array();
        $atividadeId = !empty($selecionados['atividade_id']) ? (int) $selecionados['atividade_id'] : 0;
        $atividadeSelecionada = $atividadeId > 0 ? $this->selecionarAtividade($contexto['atividades'], $atividadeId) : null;
        $entregaId = !empty($selecionados['entrega_id']) ? (int) $selecionados['entrega_id'] : 0;
        $entregaSelecionada = $entregaId > 0 ? $this->atividadeService->detalharEntrega(1, $entregaId, 'admin') : null;
        $entregaStatusFiltro = !empty($selecionados['entrega_status']) ? (string) $selecionados['entrega_status'] : '';
        $entregasAtividadeTodas = $atividadeSelecionada ? $this->atividadeService->listarEntregasPorAtividade((int) $atividadeSelecionada['id']) : array();

        return array_merge($contexto, array(
            'cursos' => $this->cursoModel->allWithCategoryAndCounts(),
            'curso' => $curso,
            'turma' => $turma,
            'turmas' => $turmas,
            'turmas_inscritos' => $cursoId ? $this->inscricaoModel->countAtivasPorCurso($cursoId) : array(),
            'selected_tab' => $aba,
            'tabs' => $this->abasLms(),
            'resumo' => $cursoId ? $this->resumoContexto($cursoId, $turmaId) : $this->resumoVazio(),
            'participantes' => $cursoId ? $this->listarParticipantes($cursoId, $turmaId, $participantesFiltros) : array(),
            'participantes_filtros' => $participantesFiltros,
            'presencas_filtros' => $presencasFiltros,
            'atividade_modulo_id' => !empty($selecionados['atividade_modulo_id']) ? (int) $selecionados['atividade_modulo_id'] : 0,
            'atividade_aula_id' => !empty($selecionados['atividade_aula_id']) ? (int) $selecionados['atividade_aula_id'] : 0,
            'atividade_status' => !empty($selecionados['atividade_status']) ? (string) $selecionados['atividade_status'] : '',
            'entrega_status' => $entregaStatusFiltro,
            'entrega_id' => $entregaId,
            'instrucao_selecionada' => !empty($selecionados['instrucao_id']) ? $this->instrucoesModel->findById((int) $selecionados['instrucao_id']) : null,
            'modulo_selecionado' => !empty($selecionados['modulo_id']) ? $this->moduloModel->findById((int) $selecionados['modulo_id']) : null,
            'aula_selecionada' => !empty($selecionados['aula_id']) ? $this->aulaModel->findById((int) $selecionados['aula_id']) : null,
            'material_selecionado' => !empty($selecionados['material_id']) ? $this->materialModel->findById((int) $selecionados['material_id']) : null,
            'link_selecionado' => !empty($selecionados['link_id']) ? $this->linkModel->findById((int) $selecionados['link_id']) : null,
            'atividade_selecionada' => $atividadeSelecionada,
            'entrega_selecionada' => $entregaSelecionada,
            'entregas_atividade_todas' => $entregasAtividadeTodas,
            'entregas_atividade' => $atividadeSelecionada ? $this->atividadeService->listarEntregasPorAtividade((int) $atividadeSelecionada['id'], $entregaStatusFiltro !== '' ? $entregaStatusFiltro : null) : array(),
        ));
    }

    public function abasLms()
    {
        return array(
            array('slug' => 'visao-geral', 'label' => 'Visão geral'),
            array('slug' => 'turmas', 'label' => 'Turmas'),
            array('slug' => 'modulos-aulas', 'label' => 'Módulos e aulas'),
            array('slug' => 'materiais', 'label' => 'Materiais'),
            array('slug' => 'atividades', 'label' => 'Atividades'),
            array('slug' => 'conteudo', 'label' => 'Conteúdo'),
            array('slug' => 'participantes', 'label' => 'Participantes'),
            array('slug' => 'presenca', 'label' => 'Presença'),
            array('slug' => 'avaliacoes-notas', 'label' => 'Avaliações / Notas'),
            array('slug' => 'certificados', 'label' => 'Certificados'),
            array('slug' => 'relatorios', 'label' => 'Relatórios'),
            array('slug' => 'configuracoes', 'label' => 'Configurações'),
            array('slug' => 'aptos-certificado', 'label' => 'Aptos para certificado'),
        );
    }

    public function salvarInstrução(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $payload = array(
            'curso_evento_id' => (int) $data['curso_evento_id'],
            'turma_id' => !empty($data['turma_id']) ? (int) $data['turma_id'] : null,
            'titulo' => trim((string) $data['titulo']),
            'conteudo' => isset($data['conteudo']) ? HtmlSanitizer::clean((string) $data['conteudo'], 'full') : null,
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        );

        if ($payload['curso_evento_id'] <= 0) {
            return array('ok' => false, 'message' => 'Curso invalido para a instrucao.');
        }

        if ($id > 0 && !$this->instrucoesModel->findById($id)) {
            return array('ok' => false, 'message' => 'Instrução nao encontrada.');
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
            case 'atividade':
                $registro = $this->atividadeModel->findById($id);
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

    public function listarParticipantes($cursoId, $turmaId = null, array $filtros = array())
    {
        return $this->participanteModel->forCursoTurma($cursoId, $turmaId, $filtros);
    }

    public function carregarAvaliacoesNotas($cursoId, $turmaId = null, array $filtros = array())
    {
        $cursoId = (int) $cursoId;
        $turmaId = $turmaId !== null && $turmaId !== '' ? (int) $turmaId : null;

        if ($cursoId <= 0) {
            return array('ok' => false, 'message' => 'Curso invalido para a area de avaliacoes.');
        }

        $contexto = $this->aptidaoService->contexto($cursoId, $turmaId);
        $avaliacoesContexto = $this->avaliacaoService->listarContexto($cursoId, $turmaId);
        if (!empty($avaliacoesContexto['avaliacoes'])) {
            $contexto['avaliacoes'] = $avaliacoesContexto['avaliacoes'];
        }

        $filtros = $this->normalizarFiltrosAvaliacoesNotas($filtros);
        $inscricoes = !empty($contexto['inscricoes']) && is_array($contexto['inscricoes']) ? $contexto['inscricoes'] : array();
        $atividades = $this->atividadeService->listarPorContexto($cursoId, $turmaId);
        $inscricoesFiltradas = $this->aplicarFiltrosAvaliacoesNotas($inscricoes, $filtros);
        $resumo = $this->resumoAvaliacoesNotas($inscricoesFiltradas);

        return array(
            'ok' => true,
            'curso' => !empty($contexto['curso']) ? $contexto['curso'] : $this->cursoModel->findById($cursoId),
            'turma' => !empty($contexto['turma']) ? $contexto['turma'] : null,
            'inscricoes' => $inscricoesFiltradas,
            'inscricoes_base' => $inscricoes,
            'avaliacoes' => !empty($contexto['avaliacoes']) && is_array($contexto['avaliacoes']) ? $contexto['avaliacoes'] : array(),
            'presencas' => !empty($contexto['presencas']) && is_array($contexto['presencas']) ? $contexto['presencas'] : array(),
            'atividades' => $atividades,
            'filtros' => $filtros,
            'resumo' => $resumo,
        );
    }

    public function carregarCertificados($cursoId, $turmaId = null, array $filtros = array())
    {
        $cursoId = (int) $cursoId;
        $turmaId = $turmaId !== null && $turmaId !== '' ? (int) $turmaId : null;

        if ($cursoId <= 0) {
            return array('ok' => false, 'message' => 'Curso invalido para a area de certificados.');
        }

        $contexto = $this->aptidaoService->contexto($cursoId, $turmaId);
        $inscricoes = !empty($contexto['inscricoes']) && is_array($contexto['inscricoes']) ? $contexto['inscricoes'] : array();
        $certificados = $this->certificadoModel->listForContext($cursoId, $turmaId, $this->normalizarFiltrosCertificados($filtros));
        $filtros = $this->normalizarFiltrosCertificados($filtros);

        $certificados = $this->aplicarFiltrosCertificados($certificados, $filtros);
        $aptosParaEmissao = array();
        $pendentes = array();

        foreach ($inscricoes as $inscricao) {
            $temCertificado = !empty($inscricao['certificado_id']) || (isset($inscricao['certificado_status']) && (string) $inscricao['certificado_status'] === 'emitido');
            if ($temCertificado) {
                continue;
            }

            if (!empty($inscricao['apto_certificado'])) {
                if ($this->inscricaoCombinaBuscaCertificado($inscricao, $filtros['busca'])) {
                    $aptosParaEmissao[] = $inscricao;
                }
                continue;
            }

            if ($this->inscricaoCombinaBuscaCertificado($inscricao, $filtros['busca'])) {
                $pendentes[] = $inscricao;
            }
        }

        return array(
            'ok' => true,
            'curso' => $contexto['curso'],
            'turma' => $contexto['turma'],
            'inscricoes' => $inscricoes,
            'certificados' => $certificados,
            'aptos_para_emissao' => $aptosParaEmissao,
            'pendentes' => $pendentes,
            'resumo' => $this->resumoCertificados($certificados, $aptosParaEmissao, $pendentes),
            'filtros' => $filtros,
        );
    }

    public function exportarCsvCertificados(array $certificados, $arquivoNome = 'certificados.csv')
    {
        $linhas = array(
            array('Código', 'Participante', 'CPF', 'Turma', 'Status', 'Emitido em', 'Pedido'),
        );

        foreach ($certificados as $certificado) {
            $linhas[] = array(
                (string) ($certificado['codigo'] ?? ''),
                (string) ($certificado['participante_nome'] ?? ''),
                (string) ($certificado['cpf_participante'] ?? ''),
                (string) ($certificado['turma_nome'] ?? ''),
                (string) ($certificado['status'] ?? ''),
                (string) ($certificado['emitido_em'] ?? ''),
                (string) ($certificado['pedido_codigo'] ?? ''),
            );
        }

        $arquivo = fopen('php://temp', 'r+');
        fwrite($arquivo, "\xEF\xBB\xBF");
        foreach ($linhas as $linha) {
            fputcsv($arquivo, $linha, ';');
        }
        rewind($arquivo);
        $content = stream_get_contents($arquivo);
        fclose($arquivo);

        return array(
            'content' => $content,
            'content_type' => 'text/csv; charset=UTF-8',
            'filename' => $arquivoNome,
        );
    }

    public function exportarCsvAvaliacoesNotas(array $inscricoes, $arquivoNome = 'avaliacoes-notas.csv')
    {
        $linhas = array(
            array('Aluno', 'E-mail', 'CPF', 'Turma', 'Status da inscrição', 'Progresso', 'Presença', 'Nota final', 'Apto para certificado', 'Certificado'),
        );

        foreach ($inscricoes as $inscricao) {
            $linhas[] = array(
                (string) ($inscricao['aluno_nome'] ?? $inscricao['participante_nome'] ?? ''),
                (string) ($inscricao['aluno_email'] ?? $inscricao['participante_email'] ?? ''),
                (string) ($inscricao['aluno_cpf'] ?? $inscricao['participante_cpf'] ?? ''),
                (string) ($inscricao['turma_nome'] ?? ''),
                (string) ($inscricao['status'] ?? $inscricao['inscricao_status'] ?? ''),
                isset($inscricao['percentual_progresso']) && $inscricao['percentual_progresso'] !== null ? number_format((float) $inscricao['percentual_progresso'], 2, ',', '.') . '%' : '',
                isset($inscricao['presenca_percentual']) && $inscricao['presenca_percentual'] !== null ? number_format((float) $inscricao['presenca_percentual'], 2, ',', '.') . '%' : '',
                isset($inscricao['nota_final']) && $inscricao['nota_final'] !== null ? number_format((float) $inscricao['nota_final'], 2, ',', '.') : '',
                !empty($inscricao['apto_certificado']) ? 'Sim' : 'Não',
                !empty($inscricao['certificado_id']) || (isset($inscricao['certificado_status']) && (string) $inscricao['certificado_status'] === 'emitido') ? 'Emitido' : 'Não emitido',
            );
        }

        $arquivo = fopen('php://temp', 'r+');
        fwrite($arquivo, "\xEF\xBB\xBF");
        foreach ($linhas as $linha) {
            fputcsv($arquivo, $linha, ';');
        }
        rewind($arquivo);
        $content = stream_get_contents($arquivo);
        fclose($arquivo);

        return array(
            'filename' => $arquivoNome,
            'content' => $content,
            'content_type' => 'text/csv; charset=UTF-8',
        );
    }

    private function normalizarFiltrosAvaliacoesNotas(array $filtros)
    {
        return array(
            'busca' => isset($filtros['busca']) ? trim((string) $filtros['busca']) : '',
            'status_inscricao' => isset($filtros['status_inscricao']) ? trim((string) $filtros['status_inscricao']) : '',
            'nota_status' => isset($filtros['nota_status']) ? trim((string) $filtros['nota_status']) : '',
            'certificado' => isset($filtros['certificado']) ? trim((string) $filtros['certificado']) : '',
            'avaliacao_id' => isset($filtros['avaliacao_id']) ? (int) $filtros['avaliacao_id'] : 0,
        );
    }

    private function aplicarFiltrosAvaliacoesNotas(array $inscricoes, array $filtros)
    {
        $resultado = array();

        foreach ($inscricoes as $inscricao) {
            if (!empty($filtros['busca'])) {
                $busca = mb_strtolower((string) $filtros['busca']);
                $camposBusca = array(
                    mb_strtolower((string) ($inscricao['aluno_nome'] ?? $inscricao['participante_nome'] ?? '')),
                    mb_strtolower((string) ($inscricao['aluno_email'] ?? $inscricao['participante_email'] ?? '')),
                    mb_strtolower((string) ($inscricao['aluno_cpf'] ?? $inscricao['participante_cpf'] ?? '')),
                    mb_strtolower((string) ($inscricao['turma_nome'] ?? '')),
                );
                $encontrado = false;
                foreach ($camposBusca as $campo) {
                    if ($campo !== '' && mb_strpos($campo, $busca) !== false) {
                        $encontrado = true;
                        break;
                    }
                }
                if (!$encontrado) {
                    continue;
                }
            }

            if (!empty($filtros['status_inscricao']) && (string) ($inscricao['status'] ?? $inscricao['inscricao_status'] ?? '') !== (string) $filtros['status_inscricao']) {
                continue;
            }

            if (!empty($filtros['nota_status'])) {
                $notaFinal = isset($inscricao['nota_final']) && $inscricao['nota_final'] !== null ? (float) $inscricao['nota_final'] : null;
                $aptoCertificado = !empty($inscricao['apto_certificado']);
                $statusNota = 'pendente';

                if ($notaFinal !== null) {
                    $statusNota = $aptoCertificado ? 'aprovada' : 'reprovada';
                } elseif (!empty($inscricao['status']) && in_array((string) $inscricao['status'], array('ativa', 'em_andamento'), true)) {
                    $statusNota = 'sem_nota';
                }

                if ($statusNota !== (string) $filtros['nota_status']) {
                    continue;
                }
            }

            if (!empty($filtros['certificado'])) {
                $temCertificado = !empty($inscricao['certificado_id']) || (isset($inscricao['certificado_status']) && (string) $inscricao['certificado_status'] === 'emitido');
                if ($filtros['certificado'] === 'com_certificado' && !$temCertificado) {
                    continue;
                }
                if ($filtros['certificado'] === 'sem_certificado' && $temCertificado) {
                    continue;
                }
            }

            $resultado[] = $inscricao;
        }

        return $resultado;
    }

    private function resumoAvaliacoesNotas(array $inscricoes)
    {
        $total = count($inscricoes);
        $comNota = 0;
        $semNota = 0;
        $aptos = 0;
        $naoAptos = 0;
        $certificadosEmitidos = 0;
        $somaProgresso = 0;
        $somaPresenca = 0;
        $comProgresso = 0;
        $comPresenca = 0;

        foreach ($inscricoes as $inscricao) {
            $notaFinal = isset($inscricao['nota_final']) && $inscricao['nota_final'] !== null ? (float) $inscricao['nota_final'] : null;
            $progresso = isset($inscricao['percentual_progresso']) && $inscricao['percentual_progresso'] !== null ? (float) $inscricao['percentual_progresso'] : null;
            $presenca = isset($inscricao['presenca_percentual']) && $inscricao['presenca_percentual'] !== null ? (float) $inscricao['presenca_percentual'] : null;

            if ($notaFinal !== null) {
                $comNota++;
            } else {
                $semNota++;
            }

            if ($progresso !== null) {
                $somaProgresso += $progresso;
                $comProgresso++;
            }

            if ($presenca !== null) {
                $somaPresenca += $presenca;
                $comPresenca++;
            }

            if (!empty($inscricao['apto_certificado'])) {
                $aptos++;
            } else {
                $naoAptos++;
            }

            if (!empty($inscricao['certificado_id']) || (isset($inscricao['certificado_status']) && (string) $inscricao['certificado_status'] === 'emitido')) {
                $certificadosEmitidos++;
            }
        }

        return array(
            'total_inscricoes' => $total,
            'com_nota' => $comNota,
            'sem_nota' => $semNota,
            'aptos' => $aptos,
            'nao_aptos' => $naoAptos,
            'certificados_emitidos' => $certificadosEmitidos,
            'progresso_medio' => $comProgresso > 0 ? round($somaProgresso / $comProgresso, 2) : 0,
            'presenca_media' => $comPresenca > 0 ? round($somaPresenca / $comPresenca, 2) : 0,
        );
    }

    private function normalizarFiltrosCertificados(array $filtros)
    {
        return array(
            'busca' => isset($filtros['busca']) ? trim((string) $filtros['busca']) : '',
            'status' => isset($filtros['status']) ? trim((string) $filtros['status']) : '',
        );
    }

    private function aplicarFiltrosCertificados(array $certificados, array $filtros)
    {
        $resultado = array();

        foreach ($certificados as $certificado) {
            if (!empty($filtros['busca']) && !$this->registroCombinaBuscaCertificado($certificado, $filtros['busca'])) {
                continue;
            }

            if (!empty($filtros['status']) && (string) $certificado['status'] !== (string) $filtros['status']) {
                continue;
            }

            $resultado[] = $certificado;
        }

        return $resultado;
    }

    private function inscricaoCombinaBuscaCertificado(array $inscricao, $busca)
    {
        $busca = trim((string) $busca);
        if ($busca === '') {
            return true;
        }

        $termo = mb_strtolower($busca);
        $campos = array(
            isset($inscricao['participante_nome']) ? $inscricao['participante_nome'] : '',
            isset($inscricao['participante_cpf']) ? $inscricao['participante_cpf'] : '',
            isset($inscricao['curso_nome']) ? $inscricao['curso_nome'] : '',
            isset($inscricao['turma_nome']) ? $inscricao['turma_nome'] : '',
            isset($inscricao['pedido_codigo']) ? $inscricao['pedido_codigo'] : '',
            isset($inscricao['status']) ? $inscricao['status'] : '',
        );

        foreach ($campos as $campo) {
            if ($campo !== '' && mb_strpos(mb_strtolower((string) $campo), $termo) !== false) {
                return true;
            }
        }

        return false;
    }

    private function registroCombinaBuscaCertificado(array $certificado, $busca)
    {
        $busca = trim((string) $busca);
        if ($busca === '') {
            return true;
        }

        $termo = mb_strtolower($busca);
        $campos = array(
            isset($certificado['codigo']) ? $certificado['codigo'] : '',
            isset($certificado['participante_nome']) ? $certificado['participante_nome'] : '',
            isset($certificado['cpf_participante']) ? $certificado['cpf_participante'] : '',
            isset($certificado['turma_nome']) ? $certificado['turma_nome'] : '',
            isset($certificado['pedido_codigo']) ? $certificado['pedido_codigo'] : '',
            isset($certificado['status']) ? $certificado['status'] : '',
        );

        foreach ($campos as $campo) {
            if ($campo !== '' && mb_strpos(mb_strtolower((string) $campo), $termo) !== false) {
                return true;
            }
        }

        return false;
    }

    private function resumoCertificados(array $certificados, array $aptosParaEmissao, array $pendentes)
    {
        $emitidos = 0;
        $cancelados = 0;
        $revogados = 0;
        $substituidos = 0;

        foreach ($certificados as $certificado) {
            $status = isset($certificado['status']) ? (string) $certificado['status'] : '';
            if ($status === 'emitido') {
                $emitidos++;
            } elseif ($status === 'cancelado') {
                $cancelados++;
            } elseif ($status === 'revogado') {
                $revogados++;
            } elseif ($status === 'substituido') {
                $substituidos++;
            }
        }

        return array(
            'emitidos' => $emitidos,
            'aptos' => count($aptosParaEmissao),
            'pendentes' => count($pendentes),
            'cancelados' => $cancelados,
            'revogados' => $revogados,
            'substituidos' => $substituidos,
        );
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
            if (!$this->conteudoPublicado($material)) {
                return null;
            }

            $inscricoes = $this->inscricaoModel->forUsuarioAprovadas($usuarioId);
            $autorizado = false;

            foreach ($inscricoes as $inscricao) {
                if (!$this->inscricaoAlunoPodeAcessar($inscricao)) {
                    continue;
                }

                if ((int) $inscricao['curso_evento_id'] !== (int) $material['curso_evento_id']) {
                    continue;
                }

                $materialTurmaId = !empty($material['turma_id']) ? (int) $material['turma_id'] : null;
                $inscricaoTurmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null;
                if ($materialTurmaId !== null && $materialTurmaId !== $inscricaoTurmaId) {
                    continue;
                }

                if (!empty($material['modulo_id'])) {
                    $modulo = $this->moduloModel->findById((int) $material['modulo_id']);
                    if (!$modulo || !$this->conteudoPublicado($modulo) || (int) $modulo['curso_evento_id'] !== (int) $material['curso_evento_id']) {
                        continue;
                    }

                    $moduloTurmaId = !empty($modulo['turma_id']) ? (int) $modulo['turma_id'] : null;
                    if ($moduloTurmaId !== null && $moduloTurmaId !== $inscricaoTurmaId) {
                        continue;
                    }

                    if ($materialTurmaId !== null && $moduloTurmaId !== null && $moduloTurmaId !== $materialTurmaId) {
                        continue;
                    }
                }

                if (!empty($material['aula_id'])) {
                    $aula = $this->aulaModel->findById((int) $material['aula_id']);
                    if (!$aula || !$this->conteudoPublicado($aula) || (int) $aula['curso_evento_id'] !== (int) $material['curso_evento_id']) {
                        continue;
                    }

                    $aulaTurmaId = !empty($aula['turma_id']) ? (int) $aula['turma_id'] : null;
                    if ($aulaTurmaId !== null && $aulaTurmaId !== $inscricaoTurmaId) {
                        continue;
                    }

                    if ($materialTurmaId !== null && $aulaTurmaId !== null && $aulaTurmaId !== $materialTurmaId) {
                        continue;
                    }

                    if (!empty($material['modulo_id']) && (int) $aula['modulo_id'] !== (int) $material['modulo_id']) {
                        continue;
                    }
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

    public function prepararAcessoMaterial(array $material)
    {
        return $this->materialService->prepararAcesso($material);
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

    private function carregarContexto($cursoId, $turmaId = null, $usuarioId = null)
    {
        $modulos = $this->moduloService->listarPorContexto($cursoId, $turmaId);
        $atividades = $this->atividadeService->listarPorContexto($cursoId, $turmaId, null, null, null, $usuarioId);
        $atividadesPorModulo = array();
        $atividadesPorAula = array();
        foreach ($atividades as $atividade) {
            $atividadesPorModulo[(int) $atividade['modulo_id']][] = $atividade;
            $atividadesPorAula[(int) $atividade['aula_id']][] = $atividade;
        }

        foreach ($modulos as &$modulo) {
            $modulo['aulas'] = $this->aulaService->listarPorModulo($modulo['id']);
            $modulo['materiais'] = $this->materialService->listarPorContexto($cursoId, $turmaId, $modulo['id'], null);
            $modulo['links'] = $this->linkModel->listForContext($cursoId, $turmaId, $modulo['id'], null);
            $modulo['atividades'] = !empty($atividadesPorModulo[(int) $modulo['id']]) ? $atividadesPorModulo[(int) $modulo['id']] : array();
            $modulo['total_aulas'] = !empty($modulo['aulas']) ? count($modulo['aulas']) : 0;
            $modulo['total_materiais'] = !empty($modulo['materiais']) ? count($modulo['materiais']) : 0;
            $modulo['total_atividades'] = !empty($modulo['atividades']) ? count($modulo['atividades']) : 0;

            foreach ($modulo['aulas'] as &$aula) {
                $aula['materiais'] = $this->materialService->listarPorContexto($cursoId, $turmaId, $modulo['id'], $aula['id']);
                $aula['total_materiais'] = !empty($aula['materiais']) ? count($aula['materiais']) : 0;
                $aula['atividades'] = !empty($atividadesPorAula[(int) $aula['id']]) ? $atividadesPorAula[(int) $aula['id']] : array();
                $aula['total_atividades'] = !empty($aula['atividades']) ? count($aula['atividades']) : 0;
            }
            unset($aula);
        }
        unset($modulo);

        return array(
            'instrucoes' => $this->instrucoesModel->findByContext($cursoId, $turmaId),
            'modulos' => $modulos,
            'materiais' => $this->materialService->listarPorContexto($cursoId, $turmaId, null, null),
            'links' => $this->linkModel->listForContext($cursoId, $turmaId, null, null),
            'atividades' => $atividades,
        );
    }

    private function filtrarConteudoVisivel(array $contexto)
    {
        if (!empty($contexto['instrucoes']) && !$this->conteudoPublicado($contexto['instrucoes'])) {
            $contexto['instrucoes'] = null;
        }

        $modulosFiltrados = array();
        foreach ($contexto['modulos'] as $modulo) {
            if (!$this->conteudoPublicado($modulo)) {
                continue;
            }

            $aulas = array();
            foreach ($modulo['aulas'] as $aula) {
                if (!$this->conteudoPublicado($aula)) {
                    continue;
                }
                $aulas[] = $aula;
            }

            $materiais = array();
            foreach ($modulo['materiais'] as $material) {
                if (!$this->conteudoPublicado($material)) {
                    continue;
                }
                $materiais[] = $material;
            }

            $links = array();
            foreach ($modulo['links'] as $link) {
                if (!$this->conteudoPublicado($link)) {
                    continue;
                }
                $links[] = $link;
            }

            $atividadesModulo = array();
            if (!empty($modulo['atividades'])) {
                foreach ($modulo['atividades'] as $atividade) {
                    if (!$this->conteudoPublicado($atividade)) {
                        continue;
                    }
                    $atividadesModulo[] = $atividade;
                }
            }

            foreach ($aulas as &$aula) {
                $aulaMateriais = array();
                if (!empty($aula['materiais'])) {
                    foreach ($aula['materiais'] as $material) {
                        if (!$this->conteudoPublicado($material)) {
                            continue;
                        }
                        $aulaMateriais[] = $material;
                    }
                }
                $aula['materiais'] = $aulaMateriais;
                $aula['total_materiais'] = count($aulaMateriais);

                $aulaAtividades = array();
                if (!empty($aula['atividades'])) {
                    foreach ($aula['atividades'] as $atividade) {
                        if (!$this->conteudoPublicado($atividade)) {
                            continue;
                        }
                        $aulaAtividades[] = $atividade;
                    }
                }
                $aula['atividades'] = $aulaAtividades;
                $aula['total_atividades'] = count($aulaAtividades);
            }
            unset($aula);

            $modulo['aulas'] = $aulas;
            $modulo['materiais'] = $materiais;
            $modulo['links'] = $links;
            $modulo['atividades'] = $atividadesModulo;
            $modulo['total_materiais'] = count($materiais);
            $modulo['total_atividades'] = count($atividadesModulo);
            $modulosFiltrados[] = $modulo;
        }

        $contexto['modulos'] = $modulosFiltrados;
        $contexto['materiais'] = array_values(array_filter($contexto['materiais'], function ($material) {
            return $this->conteudoPublicado($material);
        }));
        $contexto['links'] = array_values(array_filter($contexto['links'], function ($link) {
            return $this->conteudoPublicado($link);
        }));
        $contexto['atividades'] = array_values(array_filter($contexto['atividades'], function ($atividade) {
            return $this->conteudoPublicado($atividade);
        }));

        return $contexto;
    }

    private function resumoContexto($cursoId, $turmaId = null)
    {
        $modulos = $this->moduloService->listarPorContexto($cursoId, $turmaId);
        $atividades = $this->atividadeService->listarPorContexto($cursoId, $turmaId);
        $materiais = $this->materialService->listarPorContexto($cursoId, $turmaId, null, null);
        $aulas = 0;
        foreach ($modulos as $modulo) {
            $aulas += !empty($modulo['aulas']) ? count($modulo['aulas']) : 0;
        }

        return array(
            'modulos' => count($modulos),
            'aulas' => $aulas,
            'materiais' => count($materiais),
            'atividades' => count($atividades),
            'participantes' => $cursoId ? count($this->listarParticipantes($cursoId, $turmaId)) : 0,
            'certificados' => $this->contarCertificadosEmitidos($cursoId, $turmaId),
            'turmas' => count($this->turmaModel->forCourse($cursoId)),
        );
    }

    private function resumoVazio()
    {
        return array(
            'modulos' => 0,
            'aulas' => 0,
            'materiais' => 0,
            'atividades' => 0,
            'participantes' => 0,
            'certificados' => 0,
            'turmas' => 0,
        );
    }

    private function contarCertificadosEmitidos($cursoId, $turmaId = null)
    {
        $sql = 'SELECT COUNT(*) AS total
                FROM certificados c
                INNER JOIN inscricoes i ON i.id = c.inscricao_id
                WHERE c.deleted_at IS NULL
                  AND c.status = "emitido"
                  AND c.curso_evento_id = :curso_evento_id';
        $params = array('curso_evento_id' => $cursoId);

        if ($turmaId !== null) {
            $sql .= ' AND i.turma_id = :turma_id';
            $params['turma_id'] = $turmaId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return !empty($row) ? (int) $row['total'] : 0;
    }

    private function selecionarInscricaoPorContexto(array $inscricoes, $cursoId = null, $turmaId = null)
    {
        if (empty($inscricoes)) {
            return null;
        }

        $cursoId = $cursoId !== null ? (int) $cursoId : null;
        $turmaId = $turmaId !== null ? (int) $turmaId : null;

        foreach ($inscricoes as $inscricao) {
            if (!$this->inscricaoAlunoPodeAcessar($inscricao)) {
                continue;
            }

            if ($cursoId !== null && (int) $inscricao['curso_evento_id'] !== $cursoId) {
                continue;
            }

            $turmaInscricao = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null;
            if ($turmaId !== null && $turmaInscricao !== $turmaId) {
                continue;
            }

            return $inscricao;
        }

        return null;
    }

    private function inscricaoAlunoPodeAcessar(array $inscricao)
    {
        $status = isset($inscricao['status']) ? (string) $inscricao['status'] : '';
        if (!in_array($status, array('ativa', 'em_andamento', 'concluida', 'concluida_sem_certificado', 'certificado_emitido'), true)) {
            return false;
        }

        return $this->inscricaoTemAcessoComercial($inscricao);
    }

    private function conteudoPublicado(array $conteudo)
    {
        if (isset($conteudo['status']) && $conteudo['status'] !== '') {
            return (string) $conteudo['status'] === 'publicado';
        }

        if (array_key_exists('visivel', $conteudo)) {
            return !empty($conteudo['visivel']);
        }

        return true;
    }

    private function resumoProgressoVazio()
    {
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

    private function inscricaoTemAcessoComercial(array $inscricao)
    {
        $pedidoStatus = isset($inscricao['pedido_status']) ? (string) $inscricao['pedido_status'] : '';
        $comprovanteStatus = isset($inscricao['comprovante_status']) ? (string) $inscricao['comprovante_status'] : '';
        $acessoExpiraEm = isset($inscricao['acesso_expira_em']) ? (string) $inscricao['acesso_expira_em'] : '';

        if ($acessoExpiraEm !== '' && strtotime($acessoExpiraEm) !== false && strtotime($acessoExpiraEm) < time()) {
            return false;
        }

        if (in_array($pedidoStatus, array('aprovado', 'pago'), true)) {
            return true;
        }

        if ($comprovanteStatus === 'aprovado') {
            return true;
        }

        return false;
    }

    private function selecionarInscricao(array $inscricoes, $inscricaoId = null, $cursoId = null, $turmaId = null)
    {
        if (empty($inscricoes)) {
            return null;
        }

        if ($cursoId !== null || $turmaId !== null) {
            return $this->selecionarInscricaoPorContexto($inscricoes, $cursoId, $turmaId);
        }

        if ($inscricaoId) {
            foreach ($inscricoes as $inscricao) {
                if ((int) $inscricao['id'] === (int) $inscricaoId && $this->inscricaoAlunoPodeAcessar($inscricao)) {
                    return $inscricao;
                }
            }
        }

        foreach ($inscricoes as $inscricao) {
            if ($this->inscricaoAlunoPodeAcessar($inscricao)) {
                return $inscricao;
            }
        }

        return null;
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

            return null;
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

            return null;
        }

        return $aulas[0];
    }

    private function selecionarAtividade(array $atividades, $atividadeId = null)
    {
        if (empty($atividades)) {
            return null;
        }

        if ($atividadeId) {
            foreach ($atividades as $atividade) {
                if ((int) $atividade['id'] === (int) $atividadeId) {
                    return $atividade;
                }
            }

            return null;
        }

        return $atividades[0];
    }

    private function professorPodeAcessarContexto($usuarioId, $cursoId, $turmaId = null)
    {
        if ($usuarioId <= 0 || $cursoId <= 0) {
            return false;
        }

        $curso = $this->cursoModel->findById($cursoId);
        if (!$curso) {
            return false;
        }

        $cursos = $this->cursoModel->findAccessibleByUser($usuarioId);
        $cursoAutorizado = false;
        foreach ($cursos as $cursoItem) {
            if ((int) $cursoItem['id'] === (int) $cursoId) {
                $cursoAutorizado = true;
                break;
            }
        }

        if (!$cursoAutorizado) {
            return false;
        }

        if ($turmaId === null) {
            return true;
        }

        $turma = $this->turmaModel->findById($turmaId);
        if (!$turma || (int) $turma['curso_evento_id'] !== (int) $cursoId) {
            return false;
        }

        $turmas = $this->turmaModel->findAccessibleByUser($usuarioId);
        foreach ($turmas as $turmaItem) {
            if ((int) $turmaItem['id'] === (int) $turmaId) {
                return true;
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
            case 'atividade':
                return $this->atividadeModel->findById($id);
            case 'atividade_entrega':
                return $this->atividadeService->findEntregaById($id);
            case 'link':
                return $this->linkModel->findById($id);
            default:
                return null;
        }
    }
}




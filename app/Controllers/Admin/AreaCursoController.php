<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AreaCursoService;
use App\Services\AtividadeService;
use App\Services\AulaService;
use App\Services\ConteudoCursoService;
use App\Services\ConteudoAvaliacaoTextualService;
use App\Services\MaterialService;
use App\Services\ModuloService;
use App\Services\LmsCriterioConclusaoService;
use App\Services\RelatorioLmsService;
use App\Services\ProgressoService;
use App\Services\RbacService;
use App\Support\HtmlSanitizer;
use App\Models\CursoEvento;
use App\Models\Turma;

class AreaCursoController extends Controller
{
    private $areaCursoService;
    private $atividadeService;
    private $moduloService;
    private $aulaService;
    private $materialService;
    private $progressoService;
    private $relatorioService;
    private $criterioConclusaoService;
    private $conteudoService;
    private $conteudoAvaliacaoTextualService;
    private $rbacService;

    public function __construct()
    {
        $this->areaCursoService = new AreaCursoService();
        $this->atividadeService = new AtividadeService();
        $this->moduloService = new ModuloService();
        $this->aulaService = new AulaService();
        $this->materialService = new MaterialService();
        $this->progressoService = new ProgressoService();
        $this->relatorioService = new RelatorioLmsService();
        $this->criterioConclusaoService = new LmsCriterioConclusaoService();
        $this->conteudoService = new ConteudoCursoService();
        $this->conteudoAvaliacaoTextualService = new ConteudoAvaliacaoTextualService();
        $this->rbacService = new RbacService();
    }

    public function index(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $aba = (string) $request->query('aba', 'visao-geral');
        $abasLegadasOcultadas = array('modulos-aulas', 'materiais', 'atividades');
        if (in_array($aba, $abasLegadasOcultadas, true)) {
            Session::flash('success', 'As abas antigas foram consolidadas na aba Conteúdo.');
            $params = array('curso_id' => $cursoId, 'aba' => 'conteudo');
            if ($turmaId > 0) {
                $params['turma_id'] = $turmaId;
            }
            return $this->redirect('/admin/area-curso?' . http_build_query($params));
        }

        $abasPermitidas = array('visao-geral', 'turmas', 'conteudo', 'participantes', 'presenca', 'avaliacoes-notas', 'certificados', 'relatorios', 'configuracoes', 'aptos-certificado');
        if (!in_array($aba, $abasPermitidas, true)) {
            $aba = 'visao-geral';
        }
        $relatoriosFiltros = array(
            'busca' => trim((string) $request->query('busca', '')),
            'status_inscricao' => (string) $request->query('status_inscricao', ''),
            'faixa_progresso' => (string) $request->query('faixa_progresso', ''),
            'atividade_id' => (int) $request->query('atividade_id', 0),
            'status_correcao' => (string) $request->query('status_correcao', ''),
            'situacao_elegibilidade' => (string) $request->query('situacao_elegibilidade', ''),
            'filtro_certificado' => (string) $request->query('filtro_certificado', ''),
            'tipo_pendencia' => (string) $request->query('tipo_pendencia', ''),
        );

        $dados = array_merge(
            array(
                'title' => 'Área interna do curso',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'curso_solicitado' => $cursoId > 0,
            ),
            $this->areaCursoService->carregarAdmin(
                $cursoId,
                $turmaId,
                array(
                    'cursos_filtros' => array(
                        'busca' => trim((string) $request->query('cursos_busca', '')),
                        'categoria_id' => (int) $request->query('cursos_categoria_id', 0),
                        'status' => trim((string) $request->query('cursos_status', '')),
                        'tipo' => trim((string) $request->query('cursos_tipo', '')),
                        'modalidade' => trim((string) $request->query('cursos_modalidade', '')),
                    ),
                    'cursos_page' => (int) $request->query('cursos_page', 1),
                    'turmas_filtros' => array(
                        'curso_id' => $cursoId,
                        'search' => trim((string) $request->query('turmas_busca', '')),
                        'status' => trim((string) $request->query('turmas_status', '')),
                    ),
                    'turmas_page' => (int) $request->query('turmas_page', 1),
                    'instrucao_id' => (int) $request->query('instrucao_id', 0),
                    'modulo_id' => (int) $request->query('modulo_id', 0),
                    'aula_id' => (int) $request->query('aula_id', 0),
                    'material_id' => (int) $request->query('material_id', 0),
                    'link_id' => (int) $request->query('link_id', 0),
                    'atividade_id' => (int) $request->query('atividade_id', 0),
                    'atividade_modulo_id' => (int) $request->query('atividade_modulo_id', 0),
                    'atividade_aula_id' => (int) $request->query('atividade_aula_id', 0),
                    'atividade_status' => (string) $request->query('atividade_status', ''),
                    'entrega_id' => (int) $request->query('entrega_id', 0),
                    'entrega_status' => (string) $request->query('entrega_status', ''),
                    'aba' => $aba,
                )
            )
        );

        $dados['conteudo_modulos'] = isset($dados['conteudo_modulos']) && is_array($dados['conteudo_modulos']) ? $dados['conteudo_modulos'] : array();
        $dados['conteudo_modulos_arquivados'] = isset($dados['conteudo_modulos_arquivados']) && is_array($dados['conteudo_modulos_arquivados']) ? $dados['conteudo_modulos_arquivados'] : array();
        $dados['conteudo_modulo_selecionado_id'] = isset($dados['conteudo_modulo_selecionado_id']) ? (int) $dados['conteudo_modulo_selecionado_id'] : 0;
        $dados['conteudo_modulo_selecionado'] = isset($dados['conteudo_modulo_selecionado']) && is_array($dados['conteudo_modulo_selecionado']) ? $dados['conteudo_modulo_selecionado'] : null;
        $dados['conteudo_modulo_itens'] = isset($dados['conteudo_modulo_itens']) && is_array($dados['conteudo_modulo_itens']) ? $dados['conteudo_modulo_itens'] : array();
        $dados['conteudo_modulo_itens_arquivados'] = isset($dados['conteudo_modulo_itens_arquivados']) && is_array($dados['conteudo_modulo_itens_arquivados']) ? $dados['conteudo_modulo_itens_arquivados'] : array();
        $dados['conteudo_modo_modulo'] = !empty($dados['conteudo_modo_modulo']);
        $dados['conteudo_modulo_erro'] = isset($dados['conteudo_modulo_erro']) ? (string) $dados['conteudo_modulo_erro'] : '';
        $dados['conteudo_modulo_arquivado_selecionado'] = !empty($dados['conteudo_modulo_arquivado_selecionado']);
        $dados['can_manage_turmas'] = !empty($dados['can_manage_turmas']);
        $dados['can_delete_conteudo_definitivo'] = !empty($dados['can_delete_conteudo_definitivo']);

        if (!empty($dados['curso'])) {
            $dados['can_manage_turmas'] = $aba === 'turmas'
                ? $this->rbacService->userHasPermission(Session::get('usuario_id'), 'conteudo.gerenciar')
                : false;
            $dados['can_delete_conteudo_definitivo'] = $this->rbacService->isSuperAdmin(Session::get('usuario_id'));

            if ($aba === 'conteudo') {
                $listar = $this->conteudoService->listarModulosComItens($cursoId);
                if (!empty($listar['ok'])) {
                    $dados['conteudo_modulos'] = isset($listar['modulos']) && is_array($listar['modulos']) ? $listar['modulos'] : array();
                    $dados['conteudo_modulos_arquivados'] = isset($listar['modulos_arquivados']) && is_array($listar['modulos_arquivados']) ? $listar['modulos_arquivados'] : array();
                    $moduloSelecionadoId = (int) $request->query('modulo_id', $request->query('conteudo_modulo_id', 0));
                    $dados['conteudo_modulo_selecionado_id'] = $moduloSelecionadoId;
                    $dados['conteudo_modo_modulo'] = $moduloSelecionadoId > 0;
                    $dados['conteudo_modulo_selecionado'] = null;
                    $dados['conteudo_modulo_itens'] = array();
                    $dados['conteudo_modulo_itens_arquivados'] = array();
                    $dados['conteudo_modulo_erro'] = '';
                    $dados['conteudo_modulo_arquivado_selecionado'] = false;

                    if ($moduloSelecionadoId > 0) {
                        foreach ($dados['conteudo_modulos'] as $modulo) {
                            if ((int) ($modulo['id'] ?? 0) === $moduloSelecionadoId) {
                                $dados['conteudo_modulo_selecionado'] = $modulo;
                                $dados['conteudo_modulo_itens'] = isset($modulo['itens_ativos']) && is_array($modulo['itens_ativos']) ? $modulo['itens_ativos'] : (isset($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array());
                                $dados['conteudo_modulo_itens_arquivados'] = isset($modulo['itens_arquivados']) && is_array($modulo['itens_arquivados']) ? $modulo['itens_arquivados'] : array();
                                break;
                            }
                        }

                        if (empty($dados['conteudo_modulo_selecionado'])) {
                            foreach ($dados['conteudo_modulos_arquivados'] as $modulo) {
                                if ((int) ($modulo['id'] ?? 0) === $moduloSelecionadoId) {
                                    $dados['conteudo_modulo_arquivado_selecionado'] = true;
                                    break;
                                }
                            }
                        }

                        if (empty($dados['conteudo_modulo_selecionado']) && $moduloSelecionadoId > 0) {
                            if (!empty($dados['conteudo_modulo_arquivado_selecionado'])) {
                                $dados['conteudo_modulo_erro'] = '';
                            } else {
                                $detalheModulo = $this->conteudoService->detalharModulo($moduloSelecionadoId, $cursoId);
                                if (!empty($detalheModulo['ok']) && !empty($detalheModulo['modulo']) && is_array($detalheModulo['modulo'])) {
                                    $dados['conteudo_modulo_selecionado'] = $detalheModulo['modulo'];
                                    $dados['conteudo_modulo_itens'] = isset($detalheModulo['modulo']['itens']) && is_array($detalheModulo['modulo']['itens'])
                                        ? $detalheModulo['modulo']['itens']
                                        : array();
                                }
                            }
                        }

                        if (empty($dados['conteudo_modulo_selecionado'])) {
                            if (empty($dados['conteudo_modulo_erro'])) {
                                $dados['conteudo_modulo_erro'] = 'O módulo selecionado não pertence a este curso.';
                            }
                        }
                    }
                } else {
                    Session::flash('errors', array($listar['message'] ?? 'Não foi possível carregar o conteúdo do curso.'));
                    $dados['conteudo_modulos'] = array();
                    $dados['conteudo_modulos_arquivados'] = array();
                    $dados['conteudo_modulo_selecionado_id'] = 0;
                    $dados['conteudo_modulo_selecionado'] = null;
                    $dados['conteudo_modulo_itens'] = array();
                    $dados['conteudo_modulo_itens_arquivados'] = array();
                    $dados['conteudo_modulo_erro'] = '';
                    $dados['conteudo_modulo_arquivado_selecionado'] = false;
                }
            }

            $moduloSelecionadoQueryId = (int) $request->query('modulo_id', $request->query('conteudo_modulo_id', 0));
            if ($aba === 'conteudo' && $moduloSelecionadoQueryId > 0 && empty($dados['conteudo_modulo_selecionado'])) {
                $detalheModulo = $this->conteudoService->detalharModuloComItens($cursoId, $moduloSelecionadoQueryId);
                if (!empty($detalheModulo['ok']) && !empty($detalheModulo['modulo']) && is_array($detalheModulo['modulo'])) {
                    $dados['conteudo_modulo_selecionado'] = $detalheModulo['modulo'];
                    $dados['conteudo_modulo_itens'] = isset($detalheModulo['modulo']['itens_ativos']) && is_array($detalheModulo['modulo']['itens_ativos'])
                        ? $detalheModulo['modulo']['itens_ativos']
                        : (isset($detalheModulo['modulo']['itens']) && is_array($detalheModulo['modulo']['itens']) ? $detalheModulo['modulo']['itens'] : array());
                    $dados['conteudo_modulo_itens_arquivados'] = isset($detalheModulo['modulo']['itens_arquivados']) && is_array($detalheModulo['modulo']['itens_arquivados'])
                        ? $detalheModulo['modulo']['itens_arquivados']
                        : array();
                    $dados['conteudo_modulo_arquivado_selecionado'] = (string) ($detalheModulo['modulo']['status'] ?? '') === 'arquivado';
                    $dados['conteudo_modo_modulo'] = true;
                } else {
                    Session::flash('errors', array($detalheModulo['message'] ?? 'O módulo selecionado não pertence a este curso.'));
                    return $this->redirect($this->redirectContexto($request, 'conteudo'));
                }
            }

            if ($aba === 'configuracoes') {
                $dados['criterios_conclusao'] = $this->criterioConclusaoService->resolver($cursoId, $turmaId > 0 ? $turmaId : null);
            }

            if (in_array($aba, array('avaliacoes-notas', 'relatorios'), true)) {
                $filtrosConteudo = array(
                    'aluno_id' => (int) $request->query('conteudo_aluno_id', 0),
                    'status' => trim((string) $request->query('conteudo_status', '')),
                    'modulo_id' => (int) $request->query('conteudo_modulo_id_filtro', 0),
                    'avaliacao_id' => (int) $request->query('conteudo_avaliacao_id', 0),
                );
                $notasConteudo = $this->conteudoAvaliacaoTextualService->listarNotasAvaliacoesTextuais($cursoId, $turmaId > 0 ? $turmaId : null, $filtrosConteudo['aluno_id'] > 0 ? $filtrosConteudo['aluno_id'] : null);
                $dados['conteudo_avaliacoes_notas'] = $this->filtrarNotasConteudo($notasConteudo, $filtrosConteudo);
                $dados['conteudo_avaliacoes_filtros'] = $filtrosConteudo;
                $dados['conteudo_avaliacoes_resumo_alunos'] = $this->conteudoAvaliacaoTextualService->resumoNotasAvaliacoesTextuaisPorAluno($cursoId, $turmaId > 0 ? $turmaId : null);
            }

            if (in_array($aba, array('relatorios', 'aptos-certificado'), true)) {
                $relatorios = $this->relatorioService->carregarAdmin($cursoId, $turmaId > 0 ? $turmaId : null, $relatoriosFiltros);

                if (!empty($relatorios['ok'])) {
                    $dados['relatorios'] = $relatorios;

                    if ((string) $request->query('export', '') === 'csv') {
                        $tipoExportacao = (string) $request->query('relatorio', 'progresso');
                        $arquivoCsv = $this->relatorioService->exportarCsv($tipoExportacao, $relatorios);

                        return new Response($arquivoCsv['content'], 200, array(
                            'Content-Type' => $arquivoCsv['content_type'],
                            'Content-Disposition' => 'attachment; filename="' . $arquivoCsv['filename'] . '"',
                        ));
                    }
                } else {
                    $dados['relatorios'] = array('ok' => false, 'message' => $relatorios['message'] ?? 'Relatório indisponível.');
                }
            }
        }

        if ($aba === 'conteudo') {
            $dados['conteudo_voltar_modulos_url'] = $this->conteudoIndexContextoUrl($request);
        }

        $dados['relatorios_filtros'] = $relatoriosFiltros;

        return $this->view('admin/area-curso/index', $dados);
    }

    public function criarConteudoModulo(Request $request)
    {
        return $this->renderConteudoModuloForm($request, null);
    }

    public function editarConteudoModulo(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $moduloId = (int) $request->query('modulo_id', 0);
        if ($cursoId <= 0 || $moduloId <= 0) {
            Session::flash('errors', array('Informe um curso e um módulo válidos.'));
            return $this->redirect($this->redirectContexto($request, 'conteudo'));
        }

        $detalhe = $this->conteudoService->detalharModulo($moduloId, $cursoId);
        if (empty($detalhe['ok'])) {
            Session::flash('errors', array($detalhe['message'] ?? 'Módulo não encontrado.'));
            return $this->redirect($this->redirectContexto($request, 'conteudo'));
        }

        return $this->renderConteudoModuloForm($request, $detalhe['modulo']);
    }

    public function criarConteudoItem(Request $request)
    {
        return $this->renderConteudoItemForm($request, null, null);
    }

    public function editarConteudoItem(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $itemId = (int) $request->query('item_id', 0);
        if ($cursoId <= 0 || $itemId <= 0) {
            Session::flash('errors', array('Informe um curso e um conteúdo válidos.'));
            return $this->redirect($this->redirectContexto($request, 'conteudo'));
        }

        $detalhe = $this->conteudoService->detalharItem($itemId, $cursoId);
        if (empty($detalhe['ok'])) {
            Session::flash('errors', array($detalhe['message'] ?? 'Conteúdo não encontrado.'));
            return $this->redirect($this->redirectContexto($request, 'conteudo'));
        }

        return $this->renderConteudoItemForm($request, $detalhe['item'], $detalhe['detalhe']);
    }

    public function salvarCriteriosConclusao(Request $request)
    {
        $resultado = $this->criterioConclusaoService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'configuracoes'),
            $this->redirectContexto($request, 'configuracoes')
        );
    }

    public function salvarInstrucao(Request $request)
    {
        $resultado = $this->areaCursoService->salvarInstrução($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'visao-geral'),
            $this->redirectContexto($request, 'visao-geral')
        );
    }

    public function salvarInstrução(Request $request)
    {
        return $this->salvarInstrucao($request);
    }

    public function salvarModulo(Request $request)
    {
        $resultado = $this->moduloService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'modulos-aulas'),
            $this->redirectContexto($request, 'modulos-aulas')
        );
    }

    public function salvarAula(Request $request)
    {
        $resultado = $this->aulaService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'modulos-aulas'),
            $this->redirectContexto($request, 'modulos-aulas')
        );
    }

    public function salvarMaterial(Request $request)
    {
        $resultado = $this->materialService->salvar($request->all(), isset($_FILES['arquivo']) ? $_FILES['arquivo'] : null, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'materiais'),
            $this->redirectContexto($request, 'materiais')
        );
    }

    public function salvarAtividade(Request $request)
    {
        $resultado = $this->atividadeService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'atividades'),
            $this->redirectContexto($request, 'atividades')
        );
    }

    public function alterarStatusAtividade(Request $request)
    {
        $resultado = $this->atividadeService->alterarStatus((int) $request->input('id', 0), (string) $request->input('status', 'publicado'), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'atividades'),
            $this->redirectContexto($request, 'atividades')
        );
    }

    public function corrigirEntrega(Request $request)
    {
        $resultado = $this->atividadeService->corrigirEntrega($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, $this->redirectContexto($request, 'atividades'));
    }

    public function devolverEntrega(Request $request)
    {
        $resultado = $this->atividadeService->devolverEntrega($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'atividades'),
            $this->redirectContexto($request, 'atividades')
        );
    }

    public function salvarLink(Request $request)
    {
        $resultado = $this->areaCursoService->salvarLink($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'materiais'),
            $this->redirectContexto($request, 'materiais')
        );
    }

    public function excluir(Request $request)
    {
        $resultado = $this->areaCursoService->excluir(
            (string) $request->input('tipo', ''),
            (int) $request->input('id', 0),
            trim((string) $request->input('justificativa', '')),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, (string) $request->input('aba', 'modulos-aulas')),
            $this->redirectContexto($request, (string) $request->input('aba', 'modulos-aulas'))
        );
    }

    public function salvarConteudoModulo(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');
        $id = (int) $request->input('id', 0);
        $formUrl = $this->conteudoModuloFormUrl($request, $id > 0 ? 'editar' : 'criar', $id);

        $payload = $request->all();
        $payload['descricao'] = isset($payload['descricao']) ? HtmlSanitizer::clean((string) $payload['descricao'], 'basic') : null;
        $payload['atualizado_por'] = $usuarioId;
        if ($id <= 0) {
            $payload['criado_por'] = $usuarioId;
        }

        $resultado = $id > 0
            ? $this->conteudoService->atualizarModulo($id, $payload)
            : $this->conteudoService->criarModulo($payload);

        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível salvar o módulo.'));
            Session::flash('old_input', $payload);
            return $this->redirect($formUrl);
        }

        Session::flash('success', $id > 0 ? 'Módulo atualizado com sucesso.' : 'Módulo criado com sucesso.');
        if ($id > 0) {
            return $this->redirect($this->conteudoModuloContextoUrl($request, $id));
        }

        return $this->redirect($this->redirectContexto($request, 'conteudo'));
    }

    public function arquivarConteudoModulo(Request $request)
    {
        $resultado = $this->conteudoService->arquivarModulo((int) $request->input('id', 0), (int) Session::get('usuario_id'));
        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'conteudo'),
            $this->redirectContexto($request, 'conteudo')
        );
    }

    public function duplicarConteudoModulo(Request $request)
    {
        $resultado = $this->conteudoService->duplicarModulo((int) $request->input('id', 0), (int) Session::get('usuario_id'));
        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'conteudo'),
            $this->redirectContexto($request, 'conteudo')
        );
    }

    public function ordenarConteudoModulos(Request $request)
    {
        $cursoEventoId = (int) $request->input('curso_evento_id', 0);
        $moduloId = (int) $request->input('modulo_id', 0);
        $direcao = (string) $request->input('direcao', '');

        $listar = $this->conteudoService->listarModulosComItens($cursoEventoId);
        if (empty($listar['ok'])) {
            return $this->respondForm($listar, $request, $this->redirectContexto($request, 'conteudo'));
        }

        $modulos = $listar['modulos'];
        $idx = -1;
        foreach ($modulos as $i => $m) {
            if ((int) $m['id'] === $moduloId) {
                $idx = (int) $i;
                break;
            }
        }

        if ($idx >= 0) {
            if ($direcao === 'subir' && $idx > 0) {
                $tmp = $modulos[$idx - 1];
                $modulos[$idx - 1] = $modulos[$idx];
                $modulos[$idx] = $tmp;
            }
            if ($direcao === 'descer' && $idx < (count($modulos) - 1)) {
                $tmp = $modulos[$idx + 1];
                $modulos[$idx + 1] = $modulos[$idx];
                $modulos[$idx] = $tmp;
            }
        }

        $ordens = array();
        $ordem = 1;
        foreach ($modulos as $m) {
            $ordens[(int) $m['id']] = $ordem++;
        }

        $resultado = $this->conteudoService->reordenarModulos($cursoEventoId, $ordens);
        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'conteudo'),
            $this->redirectContexto($request, 'conteudo')
        );
    }

    public function salvarConteudoItem(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');
        $arquivo = isset($_FILES['arquivo']) ? $_FILES['arquivo'] : null;
        $id = (int) $request->input('id', 0);
        $formUrl = $this->conteudoItemFormUrl($request, $id > 0 ? 'editar' : 'criar', $id);

        $payload = $request->all();
        $payload['atualizado_por'] = $usuarioId;
        if (empty($payload['id'])) {
            $payload['criado_por'] = $usuarioId;
        }

        $resultado = $this->conteudoService->salvarItemComDetalhes($payload, $arquivo, $usuarioId);

        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível salvar o conteúdo.'));
            Session::flash('old_input', $payload);
            return $this->redirect($formUrl);
        }

        $tipo = (string) ($payload['tipo'] ?? '');
        Session::flash('success', $id > 0 ? 'Conteúdo atualizado com sucesso.' : 'Conteúdo criado com sucesso.');
        $moduloId = (int) ($payload['modulo_id'] ?? 0);
        if ($tipo === 'quiz' && $id <= 0 && !empty($resultado['id'])) {
            $quizParams = array(
                'item_id' => (int) $resultado['id'],
                'curso_id' => (int) ($payload['curso_evento_id'] ?? $payload['curso_id'] ?? 0),
            );
            if (!empty($payload['turma_id'])) {
                $quizParams['turma_id'] = (int) $payload['turma_id'];
            }
            if ($moduloId > 0) {
                $quizParams['modulo_id'] = $moduloId;
            }

            Session::flash('success', 'Quiz criado com sucesso. Adicione as perguntas no editor.');
            return $this->redirect('/admin/area-curso/conteudo/quiz/perguntas?' . http_build_query($quizParams));
        }

        if ($moduloId > 0) {
            return $this->redirect($this->conteudoModuloContextoUrl($request, $moduloId));
        }

        return $this->redirect($this->redirectContexto($request, 'conteudo'));
    }

    public function arquivarConteudoItem(Request $request)
    {
        $resultado = $this->conteudoService->arquivarItem((int) $request->input('id', 0), (int) Session::get('usuario_id'));
        $moduloId = (int) $request->input('modulo_id', 0);
        return $this->respondForm(
            $resultado,
            $request,
            $moduloId > 0 ? $this->conteudoModuloContextoUrl($request, $moduloId) : $this->redirectContexto($request, 'conteudo'),
            $moduloId > 0 ? $this->conteudoModuloContextoUrl($request, $moduloId) : $this->redirectContexto($request, 'conteudo')
        );
    }

    public function duplicarConteudoItem(Request $request)
    {
        $resultado = $this->conteudoService->duplicarItem((int) $request->input('id', 0), (int) Session::get('usuario_id'));
        $moduloId = (int) $request->input('modulo_id', 0);
        return $this->respondForm(
            $resultado,
            $request,
            $moduloId > 0 ? $this->conteudoModuloContextoUrl($request, $moduloId) : $this->redirectContexto($request, 'conteudo'),
            $moduloId > 0 ? $this->conteudoModuloContextoUrl($request, $moduloId) : $this->redirectContexto($request, 'conteudo')
        );
    }

    public function moverConteudoItem(Request $request)
    {
        $novoModuloId = (int) $request->input('novo_modulo_id', 0);
        $resultado = $this->conteudoService->moverItemParaModulo((int) $request->input('item_id', 0), $novoModuloId, (int) Session::get('usuario_id'));
        return $this->respondForm(
            $resultado,
            $request,
            $novoModuloId > 0 ? $this->conteudoModuloContextoUrl($request, $novoModuloId) : $this->redirectContexto($request, 'conteudo'),
            $novoModuloId > 0 ? $this->conteudoModuloContextoUrl($request, $novoModuloId) : $this->redirectContexto($request, 'conteudo')
        );
    }

    public function excluirDefinitivamenteConteudoModulo(Request $request)
    {
        $resultado = $this->conteudoService->excluirDefinitivamenteModuloArquivado(
            (int) $request->input('id', 0),
            (int) $request->input('curso_evento_id', 0),
            trim((string) $request->input('justificativa', '')),
            (int) Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'conteudo'),
            $this->redirectContexto($request, 'conteudo')
        );
    }

    public function excluirDefinitivamenteConteudoItem(Request $request)
    {
        $moduloId = (int) $request->input('modulo_id', 0);
        $resultado = $this->conteudoService->excluirDefinitivamenteItemArquivado(
            (int) $request->input('id', 0),
            (int) $request->input('curso_evento_id', 0),
            $moduloId,
            trim((string) $request->input('justificativa', '')),
            (int) Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            return $this->respondForm(
                $resultado,
                $request,
                $this->redirectContexto($request, 'conteudo'),
                $this->redirectContexto($request, 'conteudo')
            );
        }

        if ($moduloId > 0) {
            return $this->redirect($this->conteudoModuloContextoUrl($request, $moduloId));
        }

        return $this->redirect($this->redirectContexto($request, 'conteudo'));
    }

    public function ordenarConteudoItens(Request $request)
    {
        $moduloId = (int) $request->input('modulo_id', 0);
        $itemId = (int) $request->input('item_id', 0);
        $direcao = (string) $request->input('direcao', '');

        $itens = $this->conteudoService->listarModulosComItens((int) $request->input('curso_evento_id', 0));
        if (empty($itens['ok'])) {
            return $this->respondForm($itens, $request, $this->redirectContexto($request, 'conteudo'));
        }

        $listaItens = array();
        foreach ($itens['modulos'] as $m) {
            if ((int) $m['id'] === $moduloId) {
                $listaItens = isset($m['itens']) && is_array($m['itens']) ? $m['itens'] : array();
                break;
            }
        }

        $idx = -1;
        foreach ($listaItens as $i => $it) {
            if ((int) $it['id'] === $itemId) {
                $idx = (int) $i;
                break;
            }
        }

        if ($idx >= 0) {
            if ($direcao === 'subir' && $idx > 0) {
                $tmp = $listaItens[$idx - 1];
                $listaItens[$idx - 1] = $listaItens[$idx];
                $listaItens[$idx] = $tmp;
            }
            if ($direcao === 'descer' && $idx < (count($listaItens) - 1)) {
                $tmp = $listaItens[$idx + 1];
                $listaItens[$idx + 1] = $listaItens[$idx];
                $listaItens[$idx] = $tmp;
            }
        }

        $ordens = array();
        $ordem = 1;
        foreach ($listaItens as $it) {
            $ordens[(int) $it['id']] = $ordem++;
        }

        $resultado = $this->conteudoService->reordenarItens($moduloId, $ordens);
        return $this->respondForm(
            $resultado,
            $request,
            $moduloId > 0 ? $this->conteudoModuloContextoUrl($request, $moduloId) : $this->redirectContexto($request, 'conteudo'),
            $moduloId > 0 ? $this->conteudoModuloContextoUrl($request, $moduloId) : $this->redirectContexto($request, 'conteudo')
        );
    }

    public function participantes(Request $request)
    {
        return $this->json(array(
            'ok' => true,
            'participantes' => $this->areaCursoService->listarParticipantes((int) $request->query('curso_id', 0), null),
        ));
    }

    public function material(Request $request)
    {
        $materialId = (int) $request->query('material_id', 0);
        $material = $this->areaCursoService->materialAutorizado(Session::get('usuario_id'), $materialId, 'admin');

        if (!$material) {
            return new Response(View::render('errors/404', array('title' => 'Material não encontrado')), 404);
        }

        $acesso = $this->areaCursoService->prepararAcessoMaterial($material);
        if (!$acesso) {
            return new Response(View::render('errors/404', array('title' => 'Material indisponível')), 404);
        }

        if ($acesso['tipo'] === 'url') {
            return $this->redirect($acesso['url']);
        }

        if (!is_file($acesso['absolute_path'])) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo não encontrado')), 404);
        }

        $content = file_get_contents($acesso['absolute_path']);
        return new Response($content, 200, array(
            'Content-Type' => $acesso['content_type'],
            'Content-Disposition' => 'inline; filename="' . $acesso['filename'] . '"',
        ));
    }

    public function entregaArquivo(Request $request)
    {
        $entregaId = (int) $request->query('entrega_id', 0);
        $entrega = $this->atividadeService->entregaAutorizada(Session::get('usuario_id'), $entregaId, 'admin');

        if (!$entrega) {
            Logger::info('atividade.entrega.bloqueio_acesso', array(
                'contexto' => 'admin',
                'entrega_id' => $entregaId,
                'usuario_id' => Session::get('usuario_id'),
            ));
            return new Response(View::render('errors/404', array('title' => 'Entrega não encontrada')), 404);
        }

        $acesso = $this->atividadeService->prepararAcessoEntrega($entrega);
        if (!$acesso) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo indisponível')), 404);
        }

        if (!is_file($acesso['absolute_path'])) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo não encontrado')), 404);
        }

        Logger::info('atividade.entrega.download', array(
            'contexto' => 'admin',
            'entrega_id' => $entregaId,
            'usuario_id' => Session::get('usuario_id'),
        ));

        $content = file_get_contents($acesso['absolute_path']);
        return new Response($content, 200, array(
            'Content-Type' => $acesso['content_type'],
            'Content-Disposition' => 'inline; filename="' . $acesso['filename'] . '"',
        ));
    }

    public function downloadConteudoArquivo(Request $request)
    {
        $itemId = (int) $request->query('id', 0);
        $cursoId = (int) $request->query('curso_id', 0);
        $arquivo = $this->conteudoService->obterArquivoDoItem($itemId, $cursoId);

        if (empty($arquivo['ok'])) {
            Logger::info('conteudo.arquivo.download_bloqueado', array('contexto' => 'admin', 'item_id' => $itemId, 'usuario_id' => Session::get('usuario_id')));
            return new Response(View::render('errors/404', array('title' => 'Arquivo não encontrado')), 404);
        }

        $storage = new \App\Services\FileStorageService();
        $relativePath = (string) $arquivo['arquivo']['caminho'];
        $absolutePath = $storage->privatePath($relativePath);
        if (!is_file($absolutePath)) {
            $alternativos = array(
                BASE_PATH . '/storage/private_uploads/' . ltrim($relativePath, '/\\'),
                dirname(BASE_PATH) . '/storage/private_uploads/' . ltrim($relativePath, '/\\'),
                BASE_PATH . '/public_html/storage/private_uploads/' . ltrim($relativePath, '/\\'),
            );
            foreach ($alternativos as $caminhoAlternativo) {
                if (is_file($caminhoAlternativo)) {
                    $absolutePath = $caminhoAlternativo;
                    break;
                }
            }
        }
        if (!is_file($absolutePath)) {
            Logger::error('conteudo.arquivo.download_arquivo_ausente', array('contexto' => 'admin', 'item_id' => $itemId, 'caminho' => $relativePath));
            return new Response(View::render('errors/404', array('title' => 'Arquivo não encontrado')), 404);
        }

        $content = file_get_contents($absolutePath);
        $fileName = !empty($arquivo['arquivo']['nome_original']) ? (string) $arquivo['arquivo']['nome_original'] : basename($absolutePath);

        return new Response($content, 200, array(
            'Content-Type' => !empty($arquivo['arquivo']['mime_type']) ? (string) $arquivo['arquivo']['mime_type'] : 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ));
    }

    public function previewConteudoHtml(Request $request)
    {
        $html = (string) $request->input('html_conteudo', '');
        if (trim($html) === '') {
            $html = '<!doctype html><html><body style="font-family:sans-serif;padding:24px;color:#666;">Cole o HTML no formulário e clique em Pré-visualizar novamente.</body></html>';
        }

        return new Response($html, 200, array(
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Frame-Options' => 'SAMEORIGIN',
        ));
    }

    public function avaliacoesTextuaisPendentes(Request $request)
    {
        $lista = $this->conteudoAvaliacaoTextualService->listarPendentesProfessor(0);
        return $this->view('admin/area-curso/avaliacoes_textuais_pendentes', array(
            'title' => 'Avaliações textuais pendentes',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'entregas' => !empty($lista['items']) ? $lista['items'] : array(),
        ));
    }

    public function avaliacaoTextualCorrigir(Request $request)
    {
        $entregaId = (int) $request->query('id', 0);
        $entrega = $this->conteudoAvaliacaoTextualService->buscarEntregaParaCorrecao($entregaId);
        if (!$entrega) {
            return new Response(View::render('errors/404', array('title' => 'Entrega não encontrada')), 404);
        }

        $lista = $this->conteudoAvaliacaoTextualService->listarEntregasAluno((int) $entrega['avaliacao_id'], (int) $entrega['aluno_id'], (int) $entrega['inscricao_id']);
        return $this->view('admin/area-curso/avaliacao_textual_corrigir', array(
            'title' => 'Corrigir avaliação textual',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'entrega' => $entrega,
            'entregas_aluno' => $lista,
        ));
    }

    public function corrigirAvaliacaoTextual(Request $request)
    {
        $entregaId = (int) $request->input('entrega_id', 0);
        $resultado = $this->conteudoAvaliacaoTextualService->corrigirEntrega($entregaId, array(
            'nota' => $request->input('nota', null),
            'feedback' => (string) $request->input('feedback', ''),
            'status' => (string) $request->input('status', ''),
            'corrigido_por' => (int) Session::get('usuario_id'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ));
        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível salvar a correção.'));
        } else {
            Session::flash('success', 'Correção registrada com sucesso.');
        }
        return $this->redirect('/admin/area-curso/conteudo/avaliacao/corrigir?id=' . $entregaId);
    }

    public function liberarReenvioAvaliacaoTextual(Request $request)
    {
        $entregaId = (int) $request->input('entrega_id', 0);
        $resultado = $this->conteudoAvaliacaoTextualService->liberarNovoPrazo(
            $entregaId,
            (string) $request->input('novo_prazo', ''),
            (int) Session::get('usuario_id')
        );
        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível liberar novo prazo.'));
        } else {
            Session::flash('success', 'Novo prazo de reenvio liberado com sucesso.');
        }
        return $this->redirect('/admin/area-curso/conteudo/avaliacao/corrigir?id=' . $entregaId);
    }

    /**
     * GET /admin/area-curso/conteudo/avaliacao/imagem — serve uma imagem
     * anexada pelo aluno em uma entrega de avaliação textual, para o admin
     * conferir durante a correção.
     */
    public function entregaAvaliacaoImagem(Request $request)
    {
        $imagemId = (int) $request->query('id', 0);
        $imagem = (new \App\Models\ConteudoAvaliacaoEntregaImagem())->findById($imagemId);
        if (!$imagem) {
            return new Response(View::render('errors/404', array('title' => 'Imagem não encontrada')), 404);
        }

        $storage = new \App\Services\FileStorageService();
        $absolutePath = $storage->privatePath((string) $imagem['caminho']);
        if (!is_file($absolutePath)) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo não encontrado')), 404);
        }

        $content = file_get_contents($absolutePath);
        return new Response($content, 200, array(
            'Content-Type' => (string) ($imagem['mime_type'] ?: 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="' . basename((string) $imagem['nome_original']) . '"',
        ));
    }

    public function exportarAvaliacoesConteudoCsv(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        if ($cursoId <= 0) {
            return new Response('Curso inválido.', 400);
        }

        $filtros = array(
            'aluno_id' => (int) $request->query('conteudo_aluno_id', 0),
            'status' => trim((string) $request->query('conteudo_status', '')),
            'modulo_id' => (int) $request->query('conteudo_modulo_id_filtro', 0),
            'avaliacao_id' => (int) $request->query('conteudo_avaliacao_id', 0),
        );
        $notas = $this->conteudoAvaliacaoTextualService->listarNotasAvaliacoesTextuais($cursoId, $turmaId > 0 ? $turmaId : null, $filtros['aluno_id'] > 0 ? $filtros['aluno_id'] : null);
        $notas = $this->filtrarNotasConteudo($notas, $filtros);
        $cursoNome = (string) ($request->query('curso_nome', ''));
        $turmaNome = (string) ($request->query('turma_nome', ''));
        if ($cursoNome === '') {
            $curso = (new CursoEvento())->findById($cursoId);
            $cursoNome = (string) ($curso['nome'] ?? '');
        }
        if ($turmaNome === '') {
            if ($turmaId > 0) {
                $turma = (new Turma())->findById($turmaId);
                $turmaNome = (string) ($turma['nome'] ?? '');
            } else {
                $turmaNome = 'Curso inteiro';
            }
        }
        $csv = $this->gerarCsvAvaliacoesConteudo($notas, $cursoNome, $turmaNome);

        return new Response($csv, 200, array(
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="conteudo-avaliacoes-' . date('Ymd-His') . '.csv"',
        ));
    }

    private function respondForm(array $resultado, Request $request, $redirectTo, $exitUrl = null)
    {
        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível salvar o registro.'));
        } else {
            Session::flash('success', 'Registro salvo com sucesso.');
        }

        return $this->redirectAfterFormAction($request, $redirectTo, $exitUrl);
    }

    private function conteudoModuloFormUrl(Request $request, $modo, $moduloId = 0)
    {
        $cursoId = (int) $request->input('curso_evento_id', $request->input('curso_id', $request->query('curso_id', 0)));
        $turmaId = (int) $request->input('turma_id', $request->query('turma_id', 0));
        $params = array('curso_id' => $cursoId);
        if ($turmaId > 0) {
            $params['turma_id'] = $turmaId;
        }
        if ((int) $moduloId > 0) {
            $params['modulo_id'] = (int) $moduloId;
        }

        $route = $modo === 'editar'
            ? '/admin/area-curso/conteudo/modulos/editar'
            : '/admin/area-curso/conteudo/modulos/criar';

        return $route . '?' . http_build_query($params);
    }

    private function conteudoModuloContextoUrl(Request $request, $moduloId = 0)
    {
        $cursoId = (int) $request->input('curso_evento_id', $request->input('curso_id', $request->query('curso_id', 0)));
        $turmaId = (int) $request->input('turma_id', $request->query('turma_id', 0));
        $params = array('curso_id' => $cursoId, 'aba' => 'conteudo');
        if ($turmaId > 0) {
            $params['turma_id'] = $turmaId;
        }
        if ((int) $moduloId > 0) {
            $params['modulo_id'] = (int) $moduloId;
        }

        return '/admin/area-curso?' . http_build_query($params);
    }

    private function conteudoItemFormUrl(Request $request, $modo, $itemId = 0)
    {
        $cursoId = (int) $request->input('curso_evento_id', $request->input('curso_id', $request->query('curso_id', 0)));
        $turmaId = (int) $request->input('turma_id', $request->query('turma_id', 0));
        $moduloId = (int) $request->input('modulo_id', $request->query('modulo_id', 0));
        $params = array('curso_id' => $cursoId);
        if ($turmaId > 0) {
            $params['turma_id'] = $turmaId;
        }
        if ($moduloId > 0) {
            $params['modulo_id'] = $moduloId;
        }
        if ((int) $itemId > 0) {
            $params['item_id'] = (int) $itemId;
        }

        $route = $modo === 'editar'
            ? '/admin/area-curso/conteudo/itens/editar'
            : '/admin/area-curso/conteudo/itens/criar';

        return $route . '?' . http_build_query($params);
    }

    private function conteudoIndexContextoUrl(Request $request)
    {
        $cursoId = (int) $request->input('curso_evento_id', $request->input('curso_id', $request->query('curso_id', 0)));
        $turmaId = (int) $request->input('turma_id', $request->query('turma_id', 0));
        $params = array('curso_id' => $cursoId, 'aba' => 'conteudo');
        if ($turmaId > 0) {
            $params['turma_id'] = $turmaId;
        }

        return '/admin/area-curso?' . http_build_query($params);
    }

    private function renderConteudoModuloForm(Request $request, ?array $modulo)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        if ($cursoId <= 0) {
            Session::flash('errors', array('Selecione um curso para continuar.'));
            return $this->redirect('/admin/area-curso');
        }

        return $this->view('admin/area-curso/conteudo_modulo_form', array(
            'title' => $modulo ? 'Editar módulo' : 'Novo módulo',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
            'modulo' => $modulo,
            'action_url' => '/admin/area-curso/conteudo/modulo/salvar',
            'cancel_url' => $modulo && !empty($modulo['id']) ? $this->conteudoModuloContextoUrl($request, (int) $modulo['id']) : $this->redirectContexto($request, 'conteudo'),
        ));
    }

    private function renderConteudoItemForm(Request $request, ?array $item, ?array $detalhe)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        if ($cursoId <= 0) {
            Session::flash('errors', array('Selecione um curso para continuar.'));
            return $this->redirect('/admin/area-curso');
        }

        $listar = $this->conteudoService->listarModulosComItens($cursoId);
        $modulos = !empty($listar['ok']) ? $listar['modulos'] : array();
        if (empty($listar['ok'])) {
            Session::flash('errors', array($listar['message'] ?? 'Não foi possível carregar o conteúdo do curso.'));
        }

        $moduloSelecionado = (int) $request->query('modulo_id', 0);
        if ($item && !empty($item['modulo_id'])) {
            $moduloSelecionado = (int) $item['modulo_id'];
        }

        return $this->view('admin/area-curso/conteudo_item_form', array(
            'title' => $item ? 'Editar conteúdo' : 'Novo conteúdo',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
            'modulos' => $modulos,
            'item' => $item,
            'detalhe' => $detalhe,
            'modulo_selecionado' => $moduloSelecionado,
            'action_url' => '/admin/area-curso/conteudo/item/salvar',
            'cancel_url' => $moduloSelecionado > 0 ? $this->conteudoModuloContextoUrl($request, $moduloSelecionado) : $this->redirectContexto($request, 'conteudo'),
        ));
    }

    private function redirectContexto(Request $request, $aba = 'visao-geral')
    {
        $cursoId = (int) $request->input('curso_evento_id', $request->input('curso_id', 0));
        $turmaId = (int) $request->input('turma_id', $request->query('turma_id', 0));
        $params = array('curso_id' => $cursoId, 'aba' => $aba);

        if ($turmaId > 0) {
            $params['turma_id'] = $turmaId;
        }

        foreach (array('atividade_id', 'atividade_modulo_id', 'atividade_aula_id', 'atividade_status', 'entrega_id', 'entrega_status') as $chave) {
            $valor = $request->input($chave, $request->query($chave, null));
            if ($valor !== null && $valor !== '' && $valor !== 0) {
                $params[$chave] = $valor;
            }
        }

        return '/admin/area-curso?' . http_build_query($params);
    }

    private function filtrarNotasConteudo(array $notas, array $filtros)
    {
        $status = isset($filtros['status']) ? (string) $filtros['status'] : '';
        $moduloId = isset($filtros['modulo_id']) ? (int) $filtros['modulo_id'] : 0;
        $avaliacaoId = isset($filtros['avaliacao_id']) ? (int) $filtros['avaliacao_id'] : 0;
        if ($status === '' && $moduloId <= 0 && $avaliacaoId <= 0) {
            return $notas;
        }
        return array_values(array_filter($notas, function ($item) use ($status, $moduloId, $avaliacaoId) {
            if ($status !== '' && (string) ($item['status'] ?? '') !== $status) {
                return false;
            }
            if ($moduloId > 0 && (int) ($item['modulo_id'] ?? 0) !== $moduloId) {
                return false;
            }
            if ($avaliacaoId > 0 && (int) ($item['avaliacao_id'] ?? 0) !== $avaliacaoId) {
                return false;
            }
            return true;
        }));
    }

    private function gerarCsvAvaliacoesConteudo(array $notas, $cursoNome = '', $turmaNome = '')
    {
        $arquivo = fopen('php://temp', 'r+');
        fwrite($arquivo, "\xEF\xBB\xBF");
        fputcsv($arquivo, array('Curso', 'Turma', 'Aluno', 'Módulo', 'Avaliação', 'Obrigatória', 'Status', 'Tentativa', 'Nota', 'Nota máxima', 'Nota mínima', 'Peso', 'Enviado em', 'Corrigido em', 'Feedback resumido'), ';', '"', '\\');
        foreach ($notas as $item) {
            fputcsv($arquivo, array(
                $this->csvSafe((string) $cursoNome),
                $this->csvSafe((string) $turmaNome),
                $this->csvSafe((string) ($item['aluno_nome'] ?? '')),
                $this->csvSafe((string) ($item['modulo_titulo'] ?? '')),
                $this->csvSafe((string) ($item['avaliacao_titulo'] ?? '')),
                !empty($item['obrigatorio']) ? 'Sim' : 'Não',
                $this->csvSafe((string) ($item['status'] ?? '')),
                (int) ($item['tentativa'] ?? 0),
                $item['nota'] !== null ? number_format((float) $item['nota'], 2, ',', '.') : '',
                $item['nota_maxima'] !== null ? number_format((float) $item['nota_maxima'], 2, ',', '.') : '',
                $item['nota_minima'] !== null ? number_format((float) $item['nota_minima'], 2, ',', '.') : '',
                number_format((float) ($item['peso'] ?? 1), 2, ',', '.'),
                !empty($item['enviado_em']) ? date('d/m/Y H:i', strtotime((string) $item['enviado_em'])) : '',
                !empty($item['corrigido_em']) ? date('d/m/Y H:i', strtotime((string) $item['corrigido_em'])) : '',
                $this->csvSafe(substr(trim((string) ($item['feedback'] ?? '')), 0, 180)),
            ), ';', '"', '\\');
        }
        rewind($arquivo);
        $content = stream_get_contents($arquivo);
        fclose($arquivo);
        return $content;
    }

    private function csvSafe($valor)
    {
        if ($valor !== '' && preg_match('/^[\s]*[=+\-@]/u', $valor) === 1) {
            return "'" . $valor;
        }
        return $valor;
    }
}


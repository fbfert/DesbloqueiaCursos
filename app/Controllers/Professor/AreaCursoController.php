<?php

namespace App\Controllers\Professor;

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
    }

    public function index(Request $request)
    {
        $usuarioId = Session::get('usuario_id');
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $aba = (string) $request->query('aba', 'conteudo');
        $abasLegadasOcultadas = array('modulos-aulas', 'materiais', 'atividades');
        if (in_array($aba, $abasLegadasOcultadas, true)) {
            Session::flash('success', 'As abas antigas foram consolidadas na aba Conteúdo.');
            $params = array('curso_id' => $cursoId, 'aba' => 'conteudo');
            if ($turmaId > 0) {
                $params['turma_id'] = $turmaId;
            }
            return $this->redirect('/professor/area-curso?' . http_build_query($params));
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
                'title' => 'Área do professor',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->areaCursoService->carregarProfessor(
                $usuarioId,
                $cursoId,
                $turmaId,
                array(
                    'aba' => $aba,
                    'atividade_id' => (int) $request->query('atividade_id', 0),
                    'atividade_modulo_id' => (int) $request->query('atividade_modulo_id', 0),
                    'atividade_aula_id' => (int) $request->query('atividade_aula_id', 0),
                    'atividade_status' => (string) $request->query('atividade_status', ''),
                    'entrega_id' => (int) $request->query('entrega_id', 0),
                    'entrega_status' => (string) $request->query('entrega_status', ''),
                )
            )
        );

        if ($cursoId > 0 && empty($dados['curso'])) {
            return new Response(View::render('errors/403', array('title' => 'Acesso negado')), 403);
        }

        if (!empty($dados['curso'])) {
            if (in_array($aba, array('', 'avaliacoes-notas', 'relatorios'), true)) {
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

            if ($aba === 'conteudo') {
                $listar = $this->conteudoService->listarModulosComItens($cursoId);
                if (!empty($listar['ok'])) {
                    $dados['conteudo_modulos'] = $listar['modulos'];
                } else {
                    Session::flash('errors', array($listar['message'] ?? 'NÃ£o foi possÃ­vel carregar o conteÃºdo do curso.'));
                    $dados['conteudo_modulos'] = array();
                }

                $conteudoModuloId = (int) $request->query('conteudo_modulo_id', 0);
                if ($conteudoModuloId > 0) {
                    $detalheModulo = $this->conteudoService->detalharModulo($conteudoModuloId, $cursoId);
                    if (!empty($detalheModulo['ok'])) {
                        $dados['conteudo_modulo_editar'] = $detalheModulo['modulo'];
                    }
                }

                $conteudoItemId = (int) $request->query('conteudo_item_id', 0);
                if ($conteudoItemId > 0) {
                    $detalheItem = $this->conteudoService->detalharItem($conteudoItemId, $cursoId);
                    if (!empty($detalheItem['ok'])) {
                        $dados['conteudo_item_editar'] = $detalheItem['item'];
                        $dados['conteudo_item_detalhe'] = $detalheItem['detalhe'];
                    }
                }
            }

            $dados['criterios_conclusao'] = $this->criterioConclusaoService->resolver($cursoId, $turmaId > 0 ? $turmaId : null);
            $relatorios = $this->relatorioService->carregarProfessor($usuarioId, $cursoId, $turmaId > 0 ? $turmaId : null, $relatoriosFiltros);

            if (empty($relatorios['ok'])) {
                Session::flash('errors', array($relatorios['message'] ?? 'Relatório indisponível.'));
                return $this->redirect('/professor/area-curso?curso_id=' . $cursoId . ($turmaId > 0 ? '&turma_id=' . $turmaId : ''));
            }

            $dados['relatorios'] = $relatorios;

            if ((string) $request->query('export', '') === 'csv') {
                $tipoExportacao = (string) $request->query('relatorio', 'progresso');
                $arquivoCsv = $this->relatorioService->exportarCsv($tipoExportacao, $relatorios);

                return new Response($arquivoCsv['content'], 200, array(
                    'Content-Type' => $arquivoCsv['content_type'],
                    'Content-Disposition' => 'attachment; filename="' . $arquivoCsv['filename'] . '"',
                ));
            }
        }

        $dados['relatorios_filtros'] = $relatoriosFiltros;

        return $this->view('professor/area-curso/index', $dados);
    }

    public function salvarInstrução(Request $request)
    {
        if (!$this->registroAutorizado('instrucao', (int) $request->input('id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->areaCursoService->salvarInstrução($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0),
            '/professor/area-curso'
        );
    }

    public function salvarModulo(Request $request)
    {
        if (!$this->registroAutorizado('modulo', (int) $request->input('id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->moduloService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0),
            '/professor/area-curso'
        );
    }

    public function salvarAula(Request $request)
    {
        if (!$this->registroAutorizado('aula', (int) $request->input('id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->aulaService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0),
            '/professor/area-curso'
        );
    }

    public function salvarMaterial(Request $request)
    {
        if (!$this->registroAutorizado('material', (int) $request->input('id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->materialService->salvar($request->all(), isset($_FILES['arquivo']) ? $_FILES['arquivo'] : null, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0),
            '/professor/area-curso'
        );
    }

    public function salvarAtividade(Request $request)
    {
        if (!$this->registroAutorizado('atividade', (int) $request->input('id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->atividadeService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0) . '&aba=conteudo',
            '/professor/area-curso'
        );
    }

    public function alterarStatusAtividade(Request $request)
    {
        if (!$this->registroAutorizado('atividade', (int) $request->input('id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->atividadeService->alterarStatus((int) $request->input('id', 0), (string) $request->input('status', 'publicado'), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm($resultado, '/professor/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0) . '&aba=conteudo');
    }

    public function salvarLink(Request $request)
    {
        if (!$this->registroAutorizado('link', (int) $request->input('id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->areaCursoService->salvarLink($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0),
            '/professor/area-curso'
        );
    }

    public function excluir(Request $request)
    {
        if (!$this->registroAutorizado((string) $request->input('tipo', ''), (int) $request->input('id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }

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
            '/professor/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0),
            '/professor/area-curso'
        );
    }

    public function salvarConteudoModulo(Request $request)
    {
        $cursoId = (int) $request->input('curso_evento_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }

        $usuarioId = (int) Session::get('usuario_id');
        $id = (int) $request->input('id', 0);
        if ($id > 0 && !$this->conteudoModuloPertenceAoCurso($id, $cursoId)) {
            Session::flash('errors', array('Modulo fora do contexto autorizado.'));
            return $this->redirect('/professor/area-curso');
        }

        $payload = $request->all();
        $payload['descricao'] = isset($payload['descricao']) ? HtmlSanitizer::clean((string) $payload['descricao'], 'basic') : null;
        $payload['atualizado_por'] = $usuarioId;
        if ($id <= 0) {
            $payload['criado_por'] = $usuarioId;
        }

        $resultado = $id > 0
            ? $this->conteudoService->atualizarModulo($id, $payload)
            : $this->conteudoService->criarModulo($payload);

        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . $cursoId . '&turma_id=' . $turmaId . '&aba=conteudo',
            '/professor/area-curso'
        );
    }

    public function arquivarConteudoModulo(Request $request)
    {
        $cursoId = (int) $request->input('curso_evento_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }
        if (!$this->conteudoModuloPertenceAoCurso((int) $request->input('id', 0), $cursoId)) {
            Session::flash('errors', array('Modulo fora do contexto autorizado.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->conteudoService->arquivarModulo((int) $request->input('id', 0), (int) Session::get('usuario_id'));
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . $cursoId . '&turma_id=' . $turmaId . '&aba=conteudo',
            '/professor/area-curso'
        );
    }

    public function duplicarConteudoModulo(Request $request)
    {
        $cursoId = (int) $request->input('curso_evento_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }
        if (!$this->conteudoModuloPertenceAoCurso((int) $request->input('id', 0), $cursoId)) {
            Session::flash('errors', array('Modulo fora do contexto autorizado.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->conteudoService->duplicarModulo((int) $request->input('id', 0), (int) Session::get('usuario_id'));
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . $cursoId . '&turma_id=' . $turmaId . '&aba=conteudo',
            '/professor/area-curso'
        );
    }

    public function ordenarConteudoModulos(Request $request)
    {
        $cursoId = (int) $request->input('curso_evento_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }

        $moduloId = (int) $request->input('modulo_id', 0);
        $direcao = (string) $request->input('direcao', '');

        $listar = $this->conteudoService->listarModulosComItens($cursoId);
        if (empty($listar['ok'])) {
            return $this->respondForm($listar, $request, '/professor/area-curso?curso_id=' . $cursoId . '&turma_id=' . $turmaId . '&aba=conteudo', '/professor/area-curso');
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

        $resultado = $this->conteudoService->reordenarModulos($cursoId, $ordens);
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . $cursoId . '&turma_id=' . $turmaId . '&aba=conteudo',
            '/professor/area-curso'
        );
    }

    public function salvarConteudoItem(Request $request)
    {
        $cursoId = (int) $request->input('curso_evento_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }

        $usuarioId = (int) Session::get('usuario_id');
        $arquivo = isset($_FILES['arquivo']) ? $_FILES['arquivo'] : null;

        $payload = $request->all();
        $itemId = (int) ($payload['id'] ?? 0);
        if ($itemId > 0 && !$this->conteudoItemPertenceAoCurso($itemId, $cursoId)) {
            Session::flash('errors', array('Item fora do contexto autorizado.'));
            return $this->redirect('/professor/area-curso');
        }
        if ((int) $request->input('modulo_id', 0) > 0 && !$this->conteudoModuloPertenceAoCurso((int) $request->input('modulo_id', 0), $cursoId)) {
            Session::flash('errors', array('Modulo fora do contexto autorizado.'));
            return $this->redirect('/professor/area-curso');
        }
        $payload['atualizado_por'] = $usuarioId;
        if (empty($payload['id'])) {
            $payload['criado_por'] = $usuarioId;
        }

        $resultado = $this->conteudoService->salvarItemComDetalhes($payload, $arquivo, $usuarioId);

        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . $cursoId . '&turma_id=' . $turmaId . '&aba=conteudo',
            '/professor/area-curso'
        );
    }

    public function arquivarConteudoItem(Request $request)
    {
        $cursoId = (int) $request->input('curso_evento_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }
        if (!$this->conteudoItemPertenceAoCurso((int) $request->input('id', 0), $cursoId)) {
            Session::flash('errors', array('Item fora do contexto autorizado.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->conteudoService->arquivarItem((int) $request->input('id', 0), (int) Session::get('usuario_id'));
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . $cursoId . '&turma_id=' . $turmaId . '&aba=conteudo',
            '/professor/area-curso'
        );
    }

    public function duplicarConteudoItem(Request $request)
    {
        $cursoId = (int) $request->input('curso_evento_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }
        if (!$this->conteudoItemPertenceAoCurso((int) $request->input('id', 0), $cursoId)) {
            Session::flash('errors', array('Item fora do contexto autorizado.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->conteudoService->duplicarItem((int) $request->input('id', 0), (int) Session::get('usuario_id'));
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . $cursoId . '&turma_id=' . $turmaId . '&aba=conteudo',
            '/professor/area-curso'
        );
    }

    public function moverConteudoItem(Request $request)
    {
        $cursoId = (int) $request->input('curso_evento_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }
        if (
            !$this->conteudoItemPertenceAoCurso((int) $request->input('item_id', 0), $cursoId)
            || !$this->conteudoModuloPertenceAoCurso((int) $request->input('novo_modulo_id', 0), $cursoId)
        ) {
            Session::flash('errors', array('Contexto do item/modulo nao autorizado.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->conteudoService->moverItemParaModulo((int) $request->input('item_id', 0), (int) $request->input('novo_modulo_id', 0), (int) Session::get('usuario_id'));
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . $cursoId . '&turma_id=' . $turmaId . '&aba=conteudo',
            '/professor/area-curso'
        );
    }

    public function ordenarConteudoItens(Request $request)
    {
        $cursoId = (int) $request->input('curso_evento_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }
        if (!$this->conteudoModuloPertenceAoCurso((int) $request->input('modulo_id', 0), $cursoId)) {
            Session::flash('errors', array('Modulo fora do contexto autorizado.'));
            return $this->redirect('/professor/area-curso');
        }

        $moduloId = (int) $request->input('modulo_id', 0);
        $itemId = (int) $request->input('item_id', 0);
        $direcao = (string) $request->input('direcao', '');

        $listar = $this->conteudoService->listarModulosComItens($cursoId);
        if (empty($listar['ok'])) {
            return $this->respondForm($listar, $request, '/professor/area-curso?curso_id=' . $cursoId . '&turma_id=' . $turmaId . '&aba=conteudo', '/professor/area-curso');
        }

        $listaItens = array();
        foreach ($listar['modulos'] as $m) {
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
            '/professor/area-curso?curso_id=' . $cursoId . '&turma_id=' . $turmaId . '&aba=conteudo',
            '/professor/area-curso'
        );
    }

    public function corrigirEntrega(Request $request)
    {
        if (!$this->registroAutorizado('atividade_entrega', (int) $request->input('entrega_id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->atividadeService->corrigirEntrega($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0) . '&aba=conteudo&atividade_id=' . (int) $request->input('atividade_id', 0) . '&entrega_id=' . (int) $request->input('entrega_id', 0) . '&entrega_status=' . urlencode((string) $request->input('entrega_status', '')),
            '/professor/area-curso'
        );
    }

    public function devolverEntrega(Request $request)
    {
        if (!$this->registroAutorizado('atividade_entrega', (int) $request->input('entrega_id', 0), (int) $request->input('curso_evento_id', 0), (int) $request->input('turma_id', 0))) {
            Session::flash('errors', array('Contexto nao autorizado para este professor.'));
            return $this->redirect('/professor/area-curso');
        }

        $resultado = $this->atividadeService->devolverEntrega($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            '/professor/area-curso?curso_id=' . (int) $request->input('curso_evento_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0) . '&aba=conteudo&atividade_id=' . (int) $request->input('atividade_id', 0) . '&entrega_id=' . (int) $request->input('entrega_id', 0) . '&entrega_status=' . urlencode((string) $request->input('entrega_status', '')),
            '/professor/area-curso'
        );
    }

    public function participantes(Request $request)
    {
        if (!$this->contextoAutorizado((int) $request->query('curso_id', 0), (int) $request->query('turma_id', 0))) {
            return $this->json(array('ok' => false, 'message' => 'Contexto nao autorizado para este professor.'), 403);
        }

        return $this->json(array(
            'ok' => true,
            'participantes' => $this->areaCursoService->listarParticipantes((int) $request->query('curso_id', 0), (int) $request->query('turma_id', 0)),
        ));
    }

    public function material(Request $request)
    {
        $materialId = (int) $request->query('material_id', 0);
        $material = $this->areaCursoService->materialAutorizado(Session::get('usuario_id'), $materialId, 'professor');

        if (!$material) {
            return new Response(View::render('errors/404', array('title' => 'Material nao encontrado')), 404);
        }

        $acesso = $this->areaCursoService->prepararAcessoMaterial($material);
        if (!$acesso) {
            return new Response(View::render('errors/404', array('title' => 'Material indisponivel')), 404);
        }

        if ($acesso['tipo'] === 'url') {
            return $this->redirect($acesso['url']);
        }

        if (!is_file($acesso['absolute_path'])) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo nao encontrado')), 404);
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
        $entrega = $this->atividadeService->entregaAutorizada(Session::get('usuario_id'), $entregaId, 'professor');

        if (!$entrega) {
            Logger::info('atividade.entrega.bloqueio_acesso', array(
                'contexto' => 'professor',
                'entrega_id' => $entregaId,
                'usuario_id' => Session::get('usuario_id'),
            ));
            return new Response(View::render('errors/404', array('title' => 'Entrega nao encontrada')), 404);
        }

        $acesso = $this->atividadeService->prepararAcessoEntrega($entrega);
        if (!$acesso) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo indisponivel')), 404);
        }

        if (!is_file($acesso['absolute_path'])) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo nao encontrado')), 404);
        }

        Logger::info('atividade.entrega.download', array(
            'contexto' => 'professor',
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
        $turmaId = (int) $request->query('turma_id', 0);

        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            return new Response(View::render('errors/403', array('title' => 'Acesso negado')), 403);
        }

        $arquivo = $this->conteudoService->obterArquivoDoItem($itemId, $cursoId);
        if (empty($arquivo['ok'])) {
            Logger::info('conteudo.arquivo.download_bloqueado', array('contexto' => 'professor', 'item_id' => $itemId, 'usuario_id' => Session::get('usuario_id')));
            return new Response(View::render('errors/404', array('title' => 'Arquivo nao encontrado')), 404);
        }

        $storage = new \App\Services\FileStorageService();
        $relativePath = (string) $arquivo['arquivo']['caminho'];
        $absolutePath = $storage->privatePath($relativePath);
        if (!is_file($absolutePath)) {
            $alternativos = array(
                BASE_PATH . '/storage/private_uploads/' . ltrim($relativePath, '/\\'),
                dirname(BASE_PATH) . '/storage/private_uploads/' . ltrim($relativePath, '/\\'),
                BASE_PATH . '/storage/private_uploads/' . ltrim($relativePath, '/\\'),
            );
            foreach ($alternativos as $caminhoAlternativo) {
                if (is_file($caminhoAlternativo)) {
                    $absolutePath = $caminhoAlternativo;
                    break;
                }
            }
        }
        if (!is_file($absolutePath)) {
            Logger::error('conteudo.arquivo.download_arquivo_ausente', array('contexto' => 'professor', 'item_id' => $itemId, 'caminho' => $relativePath));
            return new Response(View::render('errors/404', array('title' => 'Arquivo nao encontrado')), 404);
        }

        $content = file_get_contents($absolutePath);
        $fileName = !empty($arquivo['arquivo']['nome_original']) ? (string) $arquivo['arquivo']['nome_original'] : basename($absolutePath);

        return new Response($content, 200, array(
            'Content-Type' => !empty($arquivo['arquivo']['mime_type']) ? (string) $arquivo['arquivo']['mime_type'] : 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ));
    }

    public function avaliacoesTextuaisPendentes(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');
        $resultado = $this->conteudoAvaliacaoTextualService->listarPendentesProfessor($usuarioId);

        return $this->view('professor/area-curso/avaliacoes_textuais_pendentes', array(
            'title' => 'Avaliações textuais pendentes',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'entregas' => !empty($resultado['items']) ? $resultado['items'] : array(),
        ));
    }

    public function avaliacaoTextualCorrigir(Request $request)
    {
        $entregaId = (int) $request->query('id', 0);
        $usuarioId = (int) Session::get('usuario_id');
        $entrega = $this->conteudoAvaliacaoTextualService->buscarEntregaParaCorrecao($entregaId);
        if (!$entrega) {
            return new Response(View::render('errors/404', array('title' => 'Entrega não encontrada')), 404);
        }

        if (!$this->contextoAutorizado((int) $entrega['curso_evento_id'], (int) $entrega['turma_id'])) {
            return new Response(View::render('errors/403', array('title' => 'Acesso negado')), 403);
        }

        $lista = $this->conteudoAvaliacaoTextualService->listarEntregasAluno((int) $entrega['avaliacao_id'], (int) $entrega['aluno_id'], (int) $entrega['inscricao_id']);
        $pendentes = $this->conteudoAvaliacaoTextualService->contarPendentesProfessor($usuarioId);

        return $this->view('professor/area-curso/avaliacao_textual_corrigir', array(
            'title' => 'Corrigir avaliação textual',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'entrega' => $entrega,
            'entregas_aluno' => $lista,
            'total_pendentes' => !empty($pendentes['total']) ? (int) $pendentes['total'] : 0,
        ));
    }

    public function corrigirAvaliacaoTextual(Request $request)
    {
        $entregaId = (int) $request->input('entrega_id', 0);
        $entrega = $this->conteudoAvaliacaoTextualService->buscarEntregaParaCorrecao($entregaId);
        if (!$entrega || !$this->contextoAutorizado((int) $entrega['curso_evento_id'], (int) $entrega['turma_id'])) {
            Session::flash('errors', array('Entrega inválida ou fora do seu escopo.'));
            return $this->redirect('/professor/area-curso/conteudo/avaliacoes/pendentes');
        }

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

        return $this->redirect('/professor/area-curso/conteudo/avaliacao/corrigir?id=' . $entregaId);
    }

    public function liberarReenvioAvaliacaoTextual(Request $request)
    {
        $entregaId = (int) $request->input('entrega_id', 0);
        $entrega = $this->conteudoAvaliacaoTextualService->buscarEntregaParaCorrecao($entregaId);
        if (!$entrega || !$this->contextoAutorizado((int) $entrega['curso_evento_id'], (int) $entrega['turma_id'])) {
            Session::flash('errors', array('Entrega inválida ou fora do seu escopo.'));
            return $this->redirect('/professor/area-curso/conteudo/avaliacoes/pendentes');
        }

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

        return $this->redirect('/professor/area-curso/conteudo/avaliacao/corrigir?id=' . $entregaId);
    }

    public function exportarAvaliacoesConteudoCsv(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        if ($cursoId <= 0 || !$this->areaCursoService->contextoProfessorAutorizado($usuarioId, $cursoId, $turmaId > 0 ? $turmaId : null)) {
            return new Response(View::render('errors/403', array('title' => 'Acesso negado')), 403);
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
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possivel salvar o registro.'));
        } else {
            Session::flash('success', 'Registro salvo com sucesso.');
        }

        return $this->redirectAfterFormAction($request, $redirectTo, $exitUrl);
    }

    private function contextoAutorizado($cursoId, $turmaId)
    {
        if ($cursoId <= 0) {
            return false;
        }

        return $this->areaCursoService->contextoProfessorAutorizado(Session::get('usuario_id'), $cursoId, $turmaId > 0 ? $turmaId : null);
    }

    private function registroAutorizado($tipo, $id, $cursoId, $turmaId)
    {
        if (!$this->contextoAutorizado($cursoId, $turmaId)) {
            return false;
        }

        if ($id <= 0) {
            return true;
        }

        return $this->areaCursoService->registroPertenceAoContexto($tipo, $id, $cursoId, $turmaId > 0 ? $turmaId : null);
    }

    private function conteudoModuloPertenceAoCurso($moduloId, $cursoId)
    {
        if ((int) $moduloId <= 0 || (int) $cursoId <= 0) {
            return false;
        }
        $detalhe = $this->conteudoService->detalharModulo((int) $moduloId, (int) $cursoId);
        return !empty($detalhe['ok']);
    }

    private function conteudoItemPertenceAoCurso($itemId, $cursoId)
    {
        if ((int) $itemId <= 0 || (int) $cursoId <= 0) {
            return false;
        }
        $detalhe = $this->conteudoService->detalharItem((int) $itemId, (int) $cursoId);
        return !empty($detalhe['ok']);
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


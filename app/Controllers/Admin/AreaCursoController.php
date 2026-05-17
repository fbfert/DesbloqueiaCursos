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
use App\Services\MaterialService;
use App\Services\ModuloService;
use App\Services\LmsCriterioConclusaoService;
use App\Services\RelatorioLmsService;
use App\Services\ProgressoService;

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
    }

    public function index(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $aba = (string) $request->query('aba', 'visao-geral');
        $abasPermitidas = array('visao-geral', 'turmas', 'modulos-aulas', 'materiais', 'atividades', 'participantes', 'presenca', 'avaliacoes-notas', 'certificados', 'relatorios', 'configuracoes', 'aptos-certificado');
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
            ),
            $this->areaCursoService->carregarAdmin(
                $cursoId,
                $turmaId,
                array(
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

        if (!empty($dados['curso'])) {
            $dados['criterios_conclusao'] = $this->criterioConclusaoService->resolver($cursoId, $turmaId > 0 ? $turmaId : null);
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

        $dados['relatorios_filtros'] = $relatoriosFiltros;

        return $this->view('admin/area-curso/index', $dados);
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

    public function salvarInstrução(Request $request)
    {
        $resultado = $this->areaCursoService->salvarInstrução($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->respondForm(
            $resultado,
            $request,
            $this->redirectContexto($request, 'visao-geral'),
            $this->redirectContexto($request, 'visao-geral')
        );
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
        $entrega = $this->atividadeService->entregaAutorizada(Session::get('usuario_id'), $entregaId, 'admin');

        if (!$entrega) {
            Logger::info('atividade.entrega.bloqueio_acesso', array(
                'contexto' => 'admin',
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

    private function respondForm(array $resultado, Request $request, $redirectTo, $exitUrl = null)
    {
        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possivel salvar o registro.'));
        } else {
            Session::flash('success', 'Registro salvo com sucesso.');
        }

        return $this->redirectAfterFormAction($request, $redirectTo, $exitUrl);
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
}


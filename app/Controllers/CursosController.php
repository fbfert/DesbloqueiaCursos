<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\CategoriaService;
use App\Services\ConfiguracaoGlobalService;
use App\Services\CursoService;
use App\Services\InscricaoService;

class CursosController extends Controller
{
    private $cursoService;
    private $inscricaoService;
    private $configuracaoGlobalService;
    private $categoriaService;

    public function __construct()
    {
        $this->configuracaoGlobalService = new ConfiguracaoGlobalService();
        $this->categoriaService = new CategoriaService();
        $this->cursoService = new CursoService();
        $this->inscricaoService = new InscricaoService();
    }

    public function index(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        $categoriaSlug = trim((string) $request->query('categoria', ''));
        $busca = trim((string) $request->query('busca', ''));
        $busca = trim(strip_tags(mb_substr($busca, 0, 80)));
        $categoriasFiltro = $this->categoriaService->listPublic();
        $categoriasFiltro = isset($categoriasFiltro['categorias']) && is_array($categoriasFiltro['categorias']) ? $categoriasFiltro['categorias'] : array();
        $categoriaSelecionada = null;
        $filters = array();
        $pageTitle = 'Cursos e eventos';
        $pageSubtitle = 'Confira apenas cursos ativos do portal. O frontend não publica itens inativos nem dados operacionais do backoffice.';

        if ($categoriaSlug !== '') {
            $categoria = $this->categoriaService->findPublicBySlug($categoriaSlug);
            if ($categoria) {
                $categoriaSelecionada = $categoria;
                $filters['categoria_id'] = (int) $categoria['id'];
                $pageTitle = 'Cursos de ' . $categoria['nome'];
                $pageSubtitle = !empty($categoria['descricao'])
                    ? (string) $categoria['descricao']
                    : 'Cursos públicos da categoria selecionada.';
            }
        }

        if ($busca !== '') {
            $filters['busca'] = $busca;
        }

        $contexto = $this->cursoService->listPublic($filters);
        if ($usuarioId > 0 && !empty($contexto['cursos']) && is_array($contexto['cursos'])) {
            $contexto['cursos'] = $this->anexarAcessosDoAlunoAoCatalogo($contexto['cursos'], $usuarioId);
        }

        return $this->view('cursos/index', array_merge(
            array(
                'title' => $pageTitle,
                'page_title' => $pageTitle,
                'page_subtitle' => $pageSubtitle,
                'categoriaSelecionada' => $categoriaSelecionada,
                'categoriaSlugAtual' => $categoriaSlug,
                'categoriasFiltro' => $categoriasFiltro,
                'buscaAtual' => $busca,
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'frontend_template' => $this->configuracaoGlobalService->templateVisualPortal(),
                'loggedIn' => $usuarioId > 0,
                'usuarioNome' => Session::get('usuario_nome'),
            ),
            $contexto
        ));
    }

    public function show(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $cupomPromocional = trim((string) $request->query('cupom', ''));
        if ($cupomPromocional !== '') {
            Session::put('cupom_promocional_codigo', $cupomPromocional);
        }
        $contexto = $this->cursoService->showPublic($cursoId, $turmaId ?: null);

        if (empty($contexto['curso'])) {
            return new Response(View::render('errors/404', array(
                'title' => 'Curso nao encontrado',
            )), 404);
        }

        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId > 0) {
            $contexto = $this->anexarSituacoesInscricaoPublica($contexto, $usuarioId, $cursoId);
        }

        return $this->view('cursos/show', array_merge(
            array(
                'title' => $contexto['curso']['nome'],
                'loggedIn' => $usuarioId > 0,
                'usuarioNome' => Session::get('usuario_nome'),
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'frontend_template' => $this->configuracaoGlobalService->templateVisualPortal(),
            ),
            $contexto
        ));
    }

    private function anexarSituacoesInscricaoPublica(array $contexto, $usuarioId, $cursoId)
    {
        if (empty($contexto['curso'])) {
            return $contexto;
        }

        if (!empty($contexto['curso']['turmas_abertas']) && is_array($contexto['curso']['turmas_abertas'])) {
            $turmas = array();
            foreach ($contexto['curso']['turmas_abertas'] as $turma) {
                if (!is_array($turma) || empty($turma['id'])) {
                    $turmas[] = $turma;
                    continue;
                }

                $situacao = $this->inscricaoService->situacaoAlunoNoCurso($usuarioId, $cursoId, (int) $turma['id']);
                $turma['situacao_inscricao'] = $situacao;
                $turma['acao_inscricao'] = $this->montarAcaoInscricaoPublica($cursoId, (int) $turma['id'], $situacao);
                $turmas[] = $turma;
            }

            $contexto['curso']['turmas_abertas'] = $turmas;
        }

        if (!empty($contexto['curso']['turma_selecionada']['id'])) {
            $situacaoSelecionada = $this->inscricaoService->situacaoAlunoNoCurso($usuarioId, $cursoId, (int) $contexto['curso']['turma_selecionada']['id']);
            $contexto['curso']['turma_selecionada']['situacao_inscricao'] = $situacaoSelecionada;
            $contexto['curso']['turma_selecionada']['acao_inscricao'] = $this->montarAcaoInscricaoPublica($cursoId, (int) $contexto['curso']['turma_selecionada']['id'], $situacaoSelecionada);
        }

        return $contexto;
    }

    private function montarAcaoInscricaoPublica($cursoId, $turmaId, array $situacao)
    {
        $statusFluxo = isset($situacao['status_fluxo']) ? (string) $situacao['status_fluxo'] : 'nao_inscrito';
        $baseUrl = '/inscricao?curso_id=' . (int) $cursoId . '&turma_id=' . (int) $turmaId;

        if ($statusFluxo === 'matriculado') {
            return array(
                'label' => 'Acessar curso',
                'href' => '/minha-pagina',
                'classe' => 'button-link',
            );
        }

        if ($statusFluxo === 'pendente_pagamento') {
            return array(
                'label' => 'Continuar pagamento',
                'href' => !empty($situacao['checkout_url']) ? (string) $situacao['checkout_url'] : '/checkout/resumo?pedido_id=' . (int) ($situacao['pedido_id'] ?? 0),
                'classe' => 'button-link',
            );
        }

        if (in_array($statusFluxo, array('cancelado', 'expirado', 'falhou', 'reprovado'), true)) {
            return array(
                'label' => 'Inscrever-se novamente',
                'href' => $baseUrl,
                'classe' => 'button-link',
            );
        }

        return array(
            'label' => 'Inscrever nesta turma',
            'href' => $baseUrl,
            'classe' => 'button-link',
        );
    }

    private function anexarAcessosDoAlunoAoCatalogo(array $cursos, $usuarioId)
    {
        $resultado = $this->inscricaoService->listarAprovadasDoUsuario($usuarioId);
        $inscricoes = !empty($resultado['inscricoes']) && is_array($resultado['inscricoes']) ? $resultado['inscricoes'] : array();
        $mapa = array();

        foreach ($inscricoes as $inscricao) {
            $cursoId = isset($inscricao['curso_evento_id']) ? (int) $inscricao['curso_evento_id'] : 0;
            $inscricaoId = isset($inscricao['id']) ? (int) $inscricao['id'] : 0;
            if ($cursoId <= 0 || $inscricaoId <= 0 || isset($mapa[$cursoId])) {
                continue;
            }

            $turmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;
            $mapa[$cursoId] = array(
                'inscricao_id' => $inscricaoId,
                'curso_id' => $cursoId,
                'turma_id' => $turmaId,
                'status' => isset($inscricao['status']) ? (string) $inscricao['status'] : '',
                'href' => $this->montarUrlAcessoAluno($inscricaoId, $cursoId, $turmaId),
                'label' => 'Você já tem esse curso, acesse aqui!',
                'classe' => 'button-link button-link--primary',
            );
        }

        foreach ($cursos as &$curso) {
            $cursoId = isset($curso['id']) ? (int) $curso['id'] : 0;
            if ($cursoId > 0 && isset($mapa[$cursoId])) {
                $curso['cta_aluno'] = $mapa[$cursoId];
            } else {
                $curso['cta_aluno'] = null;
            }
        }
        unset($curso);

        return $cursos;
    }

    private function montarUrlAcessoAluno($inscricaoId, $cursoId, $turmaId = 0)
    {
        return '/aluno/curso/' . (int) $inscricaoId . '/' . (int) $cursoId . '/' . (int) $turmaId;
    }
}

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

class CategoriasController extends Controller
{
    private $categoriaService;
    private $cursoService;
    private $configuracaoGlobalService;
    private $inscricaoService;

    public function __construct()
    {
        $this->categoriaService = new CategoriaService();
        $this->cursoService = new CursoService();
        $this->configuracaoGlobalService = new ConfiguracaoGlobalService();
        $this->inscricaoService = new InscricaoService();
    }

    public function index(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        $categoriasPublicas = $this->categoriaService->listPublic();

        return $this->view('categorias/index', array(
            'title' => 'Categorias',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'frontend_template' => $this->configuracaoGlobalService->templateVisualPortal(),
            'loggedIn' => $usuarioId > 0,
            'usuarioNome' => Session::get('usuario_nome'),
            'categorias' => isset($categoriasPublicas['categorias']) ? $categoriasPublicas['categorias'] : array(),
        ));
    }

    public function cursos(Request $request)
    {
        $slug = trim((string) $request->route('slug', ''));
        if ($slug === '') {
            return new Response(View::render('errors/404', array('title' => 'Categoria não encontrada')), 404);
        }

        $categoria = $this->categoriaService->findPublicBySlug($slug);
        if (!$categoria) {
            return new Response(View::render('errors/404', array('title' => 'Categoria não encontrada')), 404);
        }

        $contexto = $this->cursoService->listPublicByCategoria((int) $categoria['id']);
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId > 0 && !empty($contexto['cursos']) && is_array($contexto['cursos'])) {
            $contexto['cursos'] = $this->anexarAcessosDoAlunoAoCatalogo($contexto['cursos'], $usuarioId);
        }

        $descricao = !empty($categoria['descricao']) ? (string) $categoria['descricao'] : 'Cursos públicos da categoria selecionada.';
        $titulo = 'Cursos de ' . $categoria['nome'];

        return $this->view('cursos/index', array_merge(
            array(
                'title' => $titulo,
                'page_title' => $titulo,
                'page_subtitle' => $descricao,
                'categoriaSelecionada' => $categoria,
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'frontend_template' => $this->configuracaoGlobalService->templateVisualPortal(),
                'loggedIn' => $usuarioId > 0,
                'usuarioNome' => Session::get('usuario_nome'),
            ),
            $contexto
        ));
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
                'href' => '/aluno/curso/' . (int) $inscricaoId . '/' . (int) $cursoId . '/' . (int) $turmaId,
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
}

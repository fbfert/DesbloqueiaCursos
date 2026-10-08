<?php

namespace App\Controllers\Api\App;

use App\Core\Request;
use App\Models\CursoEvento;
use App\Models\Turma;
use App\Services\CategoriaService;
use App\Services\CursoService;
use App\Support\AppApi\CatalogoPresenter;
use App\Support\AppApi\Formato;

/**
 * Catálogo público, só leitura (mesmos critérios de publicação do site:
 * CursoService::listPublic / showPublic). A compra abre o site no navegador.
 */
class CatalogoController extends AppController
{
    public function index(Request $request)
    {
        list($pagina, $porPagina) = Formato::paginacao($request->query('pagina', 1), $request->query('por_pagina', 20));

        $filtros = array();
        $busca = $request->query('busca', '');
        $busca = is_scalar($busca) ? trim(strip_tags(mb_substr((string) $busca, 0, 80))) : '';
        if ($busca !== '') {
            $filtros['busca'] = $busca;
        }

        $categoria = $request->query('categoria', '');
        $categoria = is_scalar($categoria) ? trim((string) $categoria) : '';
        if ($categoria !== '') {
            $encontrada = ctype_digit($categoria) ? array('id' => (int) $categoria) : (new CategoriaService())->findPublicBySlug(mb_substr($categoria, 0, 120));
            if (!$encontrada) {
                return $this->ok(array(), 200, array('pagina' => $pagina, 'por_pagina' => $porPagina, 'total' => 0));
            }
            $filtros['categoria_id'] = (int) $encontrada['id'];
        }

        $resultado = (new CursoService())->listPublic($filtros, $pagina, $porPagina);
        $saida = array();
        foreach ((array) ($resultado['cursos'] ?? array()) as $curso) {
            $saida[] = CatalogoPresenter::cursoResumo($curso);
        }
        $paginacao = (array) ($resultado['paginacao'] ?? array());

        return $this->ok($saida, 200, array(
            'pagina' => (int) ($paginacao['pagina'] ?? $pagina),
            'por_pagina' => (int) ($paginacao['por_pagina'] ?? $porPagina),
            'total' => (int) ($paginacao['total'] ?? count($saida)),
        ));
    }

    public function show(Request $request)
    {
        $slug = trim((string) $request->route('slug', ''));
        $curso = $slug !== '' ? (new CursoEvento())->findBySlug(mb_substr($slug, 0, 191)) : null;
        if (!$curso) {
            return $this->naoEncontrado('Curso não encontrado.');
        }

        $contexto = (new CursoService())->showPublic((int) $curso['id']);
        if (empty($contexto['curso'])) {
            return $this->naoEncontrado('Curso não encontrado.');
        }

        $turmaModel = new Turma();
        $vagas = array();
        foreach ((array) ($contexto['curso']['turmas_abertas'] ?? array()) as $turma) {
            $vagas[(int) $turma['id']] = $turmaModel->vagasRestantes($turma);
        }

        return $this->ok(CatalogoPresenter::cursoDetalhe($contexto['curso'], $vagas));
    }
}

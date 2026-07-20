<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\CategoriaService;

/**
 * Página de Categorias da V2 (listagem real, com dados do catálogo atual).
 * Cada categoria leva ao catálogo V2 já filtrado (`/v2/catalogo/?categoria=slug`).
 */
class CategoriasController extends Controller
{
    private $categoriaService;

    public function __construct()
    {
        $this->categoriaService = new CategoriaService();
    }

    public function index(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        $resultado = $this->categoriaService->listPublic();
        $categoriasBrutas = isset($resultado['categorias']) && is_array($resultado['categorias'])
            ? $resultado['categorias']
            : array();

        $pageTitle = 'Categorias — Desbloqueia Cursos';

        $data = array(
            'title' => $pageTitle,
            'pageTitle' => $pageTitle,
            'pageDescription' => 'Veja todas as categorias de cursos da Desbloqueia Cursos e encontre o tema ideal para você.',
            'categorias' => $this->normalizarCategorias($categoriasBrutas),
            'loggedIn' => $usuarioId > 0,
            'usuarioNome' => trim((string) Session::get('usuario_nome', '')),
            'coursePalette' => $this->coursePalette(),
        );

        return new Response(View::render('v2/categorias', $data, false));
    }

    private function normalizarCategorias(array $categorias)
    {
        $normalizadas = array();
        foreach ($categorias as $categoria) {
            if (!is_array($categoria)) {
                continue;
            }
            $slug = isset($categoria['slug']) ? (string) $categoria['slug'] : '';
            $normalizadas[] = array(
                'nome' => isset($categoria['nome']) ? (string) $categoria['nome'] : '',
                'thumbnail' => !empty($categoria['thumbnail']) ? (string) $categoria['thumbnail'] : '',
                'total_cursos' => isset($categoria['total_cursos']) ? (int) $categoria['total_cursos'] : 0,
                'url' => $slug !== ''
                    ? '/v2/catalogo/?categoria=' . rawurlencode($slug)
                    : '/v2/catalogo/',
            );
        }

        return $normalizadas;
    }

    private function coursePalette()
    {
        return array(
            array('icon' => 'ti-scale', 'g1' => '#fff4ec', 'g2' => '#ffe4d3', 'cor' => '#cc5500'),
            array('icon' => 'ti-speakerphone', 'g1' => '#f0e8ff', 'g2' => '#e4d6ff', 'cor' => '#4B008E'),
            array('icon' => 'ti-chart-bar', 'g1' => '#d1faf5', 'g2' => '#b8f2ea', 'cor' => '#007a6a'),
            array('icon' => 'ti-device-laptop', 'g1' => '#e3f0ff', 'g2' => '#cfe4ff', 'cor' => '#1d4ed8'),
            array('icon' => 'ti-microphone', 'g1' => '#ffe9f0', 'g2' => '#ffd6e3', 'cor' => '#c00057'),
            array('icon' => 'ti-briefcase', 'g1' => '#eef7df', 'g2' => '#dcefc0', 'cor' => '#3b6d11'),
        );
    }
}

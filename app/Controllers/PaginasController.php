<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\PaginaService;

class PaginasController extends Controller
{
    private $paginaService;

    public function __construct()
    {
        $this->paginaService = new PaginaService();
    }

    public function showByRoute(Request $request)
    {
        $rota = $request->path();
        $pagina = $this->paginaService->buscarPublicaPorRota($rota);

        if (!$pagina) {
            return null;
        }

        return new Response(View::render('paginas/show', array(
            'title' => $pagina['titulo'],
            'pagina' => $pagina,
        )));
    }
}

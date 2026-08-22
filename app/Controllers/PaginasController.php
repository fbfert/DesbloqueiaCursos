<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\PaginaService;
use App\Support\V2InstitucionalContent;

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

        // Mesmo pipeline de sanitização e de título único usado nas páginas
        // institucionais V2 (App\Support\V2InstitucionalContent): o HTML
        // cadastrado é sanitizado e um eventual <h1> próprio do conteúdo é
        // rebaixado para <h2>, já que o título da página é sempre o único
        // <h1> impresso por esta view.
        $conteudo = V2InstitucionalContent::build((string) $pagina['conteudo_html']);
        $pagina['conteudo_html'] = $conteudo['html'];

        return new Response(View::render('paginas/show', array(
            'title' => $pagina['titulo'],
            'pagina' => $pagina,
        )));
    }
}

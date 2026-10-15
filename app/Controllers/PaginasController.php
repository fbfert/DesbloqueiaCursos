<?php

namespace App\Controllers;

use App\Controllers\V2\InstitucionalController;
use App\Core\Controller;
use App\Core\Request;

class PaginasController extends Controller
{
    /**
     * Página publicada no cadastro de páginas, acessada pela própria rota. Renderiza
     * no tema caderno, com o mesmo tratamento das institucionais do tema (HTML
     * sanitizado por V2InstitucionalContent e título único) — tema-caderno-unico.
     * Null quando não há página publicada: o Router segue para o 404 do tema.
     */
    public function showByRoute(Request $request)
    {
        return (new InstitucionalController())->paginaDaRota($request);
    }
}

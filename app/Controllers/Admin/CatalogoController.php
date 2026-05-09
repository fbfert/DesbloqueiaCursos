<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\CatalogoService;

class CatalogoController extends Controller
{
    private $catalogoService;

    public function __construct()
    {
        $this->catalogoService = new CatalogoService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/catalogo/index', array_merge(
            array(
                'title' => 'Catalogo',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->catalogoService->adminOverview()
        ));
    }
}




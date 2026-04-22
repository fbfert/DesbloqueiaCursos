<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\CursoService;

class CursosController extends Controller
{
    private $cursoService;

    public function __construct()
    {
        $this->cursoService = new CursoService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/cursos/index', array_merge(
            array(
                'title' => 'Cursos e eventos',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->cursoService->listAdmin()
        ));
    }
}

<?php

namespace App\Controllers\Professor;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\CursoService;
use App\Services\TurmaService;

class CatalogoController extends Controller
{
    private $cursoService;
    private $turmaService;

    public function __construct()
    {
        $this->cursoService = new CursoService();
        $this->turmaService = new TurmaService();
    }

    public function index(Request $request)
    {
        return $this->view('professor/catalogo/index', array_merge(
            array(
                'title' => 'Area do professor',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            array_merge(
                $this->cursoService->listProfessor(Session::get('usuario_id')),
                $this->turmaService->listProfessor(Session::get('usuario_id'))
            )
        ));
    }
}



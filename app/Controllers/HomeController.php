<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\CursoService;

class HomeController extends Controller
{
    private $cursoService;

    public function __construct()
    {
        $this->cursoService = new CursoService();
    }

    public function index(Request $request)
    {
        return $this->view('home', array(
            'title' => 'Polo Rainbow',
            'success' => Session::pullFlash('success'),
            'usuarioNome' => Session::get('usuario_nome'),
            'cursos' => $this->cursoService->listPublic()['cursos'],
        ));
    }
}

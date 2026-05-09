<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ConfiguracaoGlobalService;
use App\Services\CursoService;

class HomeController extends Controller
{
    private $cursoService;
    private $configuracaoGlobalService;

    public function __construct()
    {
        $this->cursoService = new CursoService();
        $this->configuracaoGlobalService = new ConfiguracaoGlobalService();
    }

    public function index(Request $request)
    {
        $limiteDestaques = $this->configuracaoGlobalService->homeDestaquesLimite();
        $cursosDestaque = $this->cursoService->listPublicHome($limiteDestaques);

        return $this->view('home', array(
            'title' => 'Polo Rainbow',
            'success' => Session::pullFlash('success'),
            'usuarioNome' => Session::get('usuario_nome'),
            'cursos' => $cursosDestaque,
        ));
    }
}

<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\InscriçãoService;

class MeusCursosController extends Controller
{
    private $inscricaoService;

    public function __construct()
    {
        $this->inscricaoService = new InscriçãoService();
    }

    public function index(Request $request)
    {
        $usuarioId = Session::get('usuario_id');

        return $this->view('meus-cursos/index', array_merge(
            array(
                'title' => 'Meus Cursos',
                'usuarioNome' => Session::get('usuario_nome'),
                'success' => Session::pullFlash('success'),
            ),
            $this->inscricaoService->listarAprovadasDoUsuario($usuarioId)
        ));
    }
}


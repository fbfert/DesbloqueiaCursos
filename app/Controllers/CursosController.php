<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
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
        return $this->view('cursos/index', array_merge(
            array(
                'title' => 'Cursos e eventos',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->cursoService->listPublic()
        ));
    }

    public function show(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $cupomPromocional = trim((string) $request->query('cupom', ''));
        if ($cupomPromocional !== '') {
            Session::put('cupom_promocional_codigo', $cupomPromocional);
        }
        $contexto = $this->cursoService->showPublic($cursoId, $turmaId ?: null);

        if (empty($contexto['curso'])) {
            return new Response(View::render('errors/404', array(
                'title' => 'Curso nao encontrado',
            )), 404);
        }

        return $this->view('cursos/show', array_merge(
            array(
                'title' => $contexto['curso']['nome'],
                'loggedIn' => Session::get('usuario_id') !== null,
                'usuarioNome' => Session::get('usuario_nome'),
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $contexto
        ));
    }
}

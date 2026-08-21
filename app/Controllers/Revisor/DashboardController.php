<?php

namespace App\Controllers\Revisor;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\RevisaoComentario;
use App\Services\RevisorAcademicScopeService;

/**
 * Painel do revisor: os cursos atribuidos e o andamento da revisao de cada um.
 */
class DashboardController extends Controller
{
    private $escopo;
    private $comentarioModel;

    public function __construct()
    {
        $this->escopo = new RevisorAcademicScopeService();
        $this->comentarioModel = new RevisaoComentario();
    }

    public function index(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id');
        $cursos = $this->escopo->cursosDoRevisor($usuarioId);

        foreach ($cursos as $indice => $curso) {
            $resumo = array('aberto' => 0, 'aceito' => 0, 'recusado' => 0, 'resolvido' => 0, 'erros_abertos' => 0);
            foreach ($this->comentarioModel->resumoPorCurso($curso['id']) as $linha) {
                if (isset($resumo[$linha['status']])) {
                    $resumo[$linha['status']] += (int) $linha['total'];
                }
            }
            $resumo['erros_abertos'] = $this->comentarioModel->contarErrosAbertos($curso['id']);
            $cursos[$indice]['resumo'] = $resumo;
        }

        return $this->view('revisor/dashboard', array(
            'title' => 'Área de revisão',
            'cursos' => $cursos,
            'cursosDoRevisor' => $cursos,
            'cursoAtual' => null,
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }
}

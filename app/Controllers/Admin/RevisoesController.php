<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\RevisaoComentarioService;

/**
 * Fila de triagem dos apontamentos de revisão
 * (openspec/changes/fila-revisao-admin).
 *
 * A regra mora em RevisaoComentarioService; aqui só entram filtros e
 * resposta HTTP. O gestor é sempre o usuário da sessão.
 */
class RevisoesController extends Controller
{
    private $comentarioService;

    public function __construct()
    {
        $this->comentarioService = new RevisaoComentarioService();
    }

    public function index(Request $request)
    {
        $fila = $this->comentarioService->fila(array(
            'curso_evento_id' => $request->query('curso_id', 0),
            'severidade' => $request->query('severidade', ''),
            'status' => $request->query('status', ''),
        ));

        return $this->view('admin/revisoes/index', array_merge(
            array(
                'title' => 'Revisões',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $fila
        ));
    }

    public function triar(Request $request)
    {
        $voltar = '/admin/revisoes' . RevisaoComentarioService::queryStringFila(array(
            'curso_evento_id' => $request->input('filtro_curso_id', 0),
            'severidade' => $request->input('filtro_severidade', ''),
            'status' => $request->input('filtro_status', ''),
        ));

        $status = (string) $request->input('status', '');
        $result = $this->comentarioService->triar(
            (int) $request->input('comentario_id', 0),
            $status,
            (string) $request->input('resposta', ''),
            Session::get('usuario_id')
        );

        if (!$result['ok']) {
            Session::flash('errors', array_values($result['errors']));
            return $this->redirect($voltar);
        }

        $mensagens = array(
            'aceito' => 'Apontamento aceito.',
            'recusado' => 'Apontamento recusado. A resposta ficou visível para o revisor.',
            'resolvido' => 'Apontamento marcado como resolvido.',
        );
        Session::flash('success', $mensagens[$status]);
        return $this->redirect($voltar);
    }
}

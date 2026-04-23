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

    public function create(Request $request)
    {
        return $this->view('admin/cursos/form', array(
            'title' => 'Novo curso/evento',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/cursos/criar',
            'submit_label' => 'Salvar curso/evento',
            'form_data' => $this->cursoService->formData(),
        ));
    }

    public function store(Request $request)
    {
        $result = $this->cursoService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Nao foi possivel salvar o curso/evento.'));
            return $this->redirect('/admin/cursos/criar');
        }

        Session::flash('success', 'Curso/evento salvo com sucesso.');
        return $this->redirect('/admin/cursos');
    }

    public function edit(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);

        return $this->view('admin/cursos/form', array(
            'title' => 'Editar curso/evento',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/cursos/editar',
            'submit_label' => 'Atualizar curso/evento',
            'form_data' => $this->cursoService->formData($cursoId),
        ));
    }

    public function update(Request $request)
    {
        $result = $this->cursoService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        $cursoId = (int) $request->input('id', 0);

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Nao foi possivel atualizar o curso/evento.'));
            return $this->redirect('/admin/cursos/editar?curso_id=' . $cursoId);
        }

        Session::flash('success', 'Curso/evento atualizado com sucesso.');
        return $this->redirect('/admin/cursos');
    }

    public function show(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $formData = $this->cursoService->formData($cursoId);

        if (empty($formData['curso'])) {
            Session::flash('errors', array('Curso/evento nao encontrado.'));
            return $this->redirect('/admin/cursos');
        }

        return $this->view('admin/cursos/show', array_merge(
            array(
                'title' => 'Curso/evento',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $formData
        ));
    }

    public function destroy(Request $request)
    {
        $cursoId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));

        $result = $this->cursoService->excluir($cursoId, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['message']) ? $result['message'] : 'Nao foi possivel excluir o curso/evento.');
            return $this->redirect('/admin/cursos/editar?curso_id=' . $cursoId);
        }

        Session::flash('success', 'Curso/evento excluido e enviado para a lixeira.');
        return $this->redirect('/admin/cursos');
    }
}

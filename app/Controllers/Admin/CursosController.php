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
        $filters = array(
            'busca' => trim((string) $request->query('busca', '')),
            'categoria_id' => (int) $request->query('categoria_id', 0),
            'status' => trim((string) $request->query('status', '')),
            'tipo' => trim((string) $request->query('tipo', '')),
            'modalidade' => trim((string) $request->query('modalidade', '')),
        );
        $page = (int) $request->query('page', 1);

        return $this->view('admin/cursos/index', array_merge(
            array(
                'title' => 'Cursos e eventos',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'filters' => $filters,
            ),
            $this->cursoService->listAdmin($filters, $page, 20)
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/cursos/form', array(
            'title' => 'Novo curso/evento',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/cursos/criar',
            'form_data' => $this->cursoService->formData(),
        ));
    }

    public function store(Request $request)
    {
        $action = $this->submitAction($request, 'save_exit');
        $input = $request->all();

        if ($action === 'save_copy') {
            $result = $this->cursoService->duplicar($input, isset($_FILES) ? $_FILES : array(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel salvar o curso/evento.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/cursos/criar');
            }

            Session::flash('success', 'Cópia do curso/evento criada com sucesso.');
            return $this->redirect('/admin/cursos/editar?curso_id=' . (int) $result['id']);
        }

        $result = $this->cursoService->salvar($input, isset($_FILES) ? $_FILES : array(), Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel salvar o curso/evento.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/cursos/criar');
        }

        if ($action === 'save_stay') {
            Session::flash('success', 'Curso/evento salvo com sucesso.');
            return $this->redirect('/admin/cursos/editar?curso_id=' . (int) $result['id']);
        }

        if ($action === 'save_new') {
            Session::flash('success', 'Curso/evento salvo com sucesso. Você já pode criar um novo curso/evento.');
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
            'form_data' => $this->cursoService->formData($cursoId),
        ));
    }

    public function update(Request $request)
    {
        $input = $request->all();
        $cursoId = (int) $request->input('id', 0);
        $action = $this->submitAction($request, 'save_exit');

        if ($action === 'save_copy') {
            $result = $this->cursoService->duplicar($input, isset($_FILES) ? $_FILES : array(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel criar a cópia do curso/evento.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/cursos/editar?curso_id=' . $cursoId);
            }

            Session::flash('success', 'Cópia do curso/evento criada com sucesso.');
            return $this->redirect('/admin/cursos/editar?curso_id=' . (int) $result['id']);
        }

        $result = $this->cursoService->salvar($input, isset($_FILES) ? $_FILES : array(), Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel atualizar o curso/evento.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/cursos/editar?curso_id=' . $cursoId);
        }

        if ($action === 'save_stay') {
            Session::flash('success', 'Curso/evento atualizado com sucesso.');
            return $this->redirect('/admin/cursos/editar?curso_id=' . (int) $result['id']);
        }

        if ($action === 'save_new') {
            Session::flash('success', 'Curso/evento atualizado com sucesso. Você já pode criar um novo curso/evento.');
            return $this->redirect('/admin/cursos/criar');
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
            Session::flash('errors', isset($result['message']) ? $result['message'] : 'Não foi possivel excluir o curso/evento.');
            return $this->redirect('/admin/cursos/editar?curso_id=' . $cursoId);
        }

        Session::flash('success', 'Curso/evento excluido e enviado para a lixeira.');
        return $this->redirect('/admin/cursos');
    }

    public function updateStatus(Request $request)
    {
        $cursoId = (int) $request->input('id', 0);
        $status = trim((string) $request->input('status', ''));

        $result = $this->cursoService->atualizarStatus($cursoId, $status, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['message']) ? $result['message'] : 'Não foi possivel atualizar o status do curso/evento.');
            return $this->redirect('/admin/cursos');
        }

        Session::flash('success', 'Status do curso/evento atualizado com sucesso.');
        return $this->redirect('/admin/cursos');
    }
}


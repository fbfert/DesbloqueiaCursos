<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\TurmaService;

class TurmasController extends Controller
{
    private $turmaService;

    public function __construct()
    {
        $this->turmaService = new TurmaService();
    }

    public function index(Request $request)
    {
        $filters = array(
            'q' => trim((string) $request->query('q', '')),
            'curso_id' => (int) $request->query('curso_id', 0),
            'status' => trim((string) $request->query('status', '')),
        );

        return $this->view('admin/turmas/index', array_merge(
            array(
                'title' => 'Turmas',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'filters' => $filters,
            ),
            $this->turmaService->listAdmin($filters)
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/turmas/form', array(
            'title' => 'Nova turma',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/turmas/criar',
            'form_data' => $this->turmaService->formData(),
        ));
    }

    public function store(Request $request)
    {
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');
        if ($action === 'save_copy') {
            $result = $this->turmaService->duplicar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível criar a cópia da turma.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/turmas/criar');
            }

            Session::flash('success', 'Cópia da turma criada com sucesso.');
            return $this->redirect('/admin/turmas/editar?turma_id=' . (int) $result['id']);
        }

        $result = $this->turmaService->salvar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel salvar a turma.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/turmas/criar');
        }

        if ($action === 'save_stay') {
            Session::flash('success', 'Turma salva com sucesso.');
            return $this->redirect('/admin/turmas/editar?turma_id=' . (int) $result['id']);
        }

        if ($action === 'save_new') {
            Session::flash('success', 'Turma salva com sucesso. Você já pode criar uma nova turma.');
            return $this->redirect('/admin/turmas/criar');
        }

        Session::flash('success', 'Turma salva com sucesso.');
        return $this->redirect('/admin/turmas');
    }

    public function edit(Request $request)
    {
        $turmaId = (int) $request->query('turma_id', 0);

        return $this->view('admin/turmas/form', array(
            'title' => 'Editar turma',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/turmas/editar',
            'form_data' => $this->turmaService->formData($turmaId),
        ));
    }

    public function update(Request $request)
    {
        $input = $request->all();
        $turmaId = (int) $request->input('id', 0);
        $action = $this->submitAction($request, 'save_exit');
        if ($action === 'save_copy') {
            $result = $this->turmaService->duplicar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível criar a cópia da turma.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/turmas/editar?turma_id=' . $turmaId);
            }

            Session::flash('success', 'Cópia da turma criada com sucesso.');
            return $this->redirect('/admin/turmas/editar?turma_id=' . (int) $result['id']);
        }

        $result = $this->turmaService->salvar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel atualizar a turma.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/turmas/editar?turma_id=' . $turmaId);
        }

        if ($action === 'save_stay') {
            Session::flash('success', 'Turma atualizada com sucesso.');
            return $this->redirect('/admin/turmas/editar?turma_id=' . (int) $result['id']);
        }

        if ($action === 'save_new') {
            Session::flash('success', 'Turma atualizada com sucesso. Você já pode criar uma nova turma.');
            return $this->redirect('/admin/turmas/criar');
        }

        Session::flash('success', 'Turma atualizada com sucesso.');
        return $this->redirect('/admin/turmas');
    }

    public function show(Request $request)
    {
        $turmaId = (int) $request->query('turma_id', 0);
        $formData = $this->turmaService->formData($turmaId);

        if (empty($formData['turma'])) {
            Session::flash('errors', array('Turma nao encontrada.'));
            return $this->redirect('/admin/turmas');
        }

        return $this->view('admin/turmas/show', array_merge(
            array(
                'title' => 'Turma',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $formData
        ));
    }

    public function destroy(Request $request)
    {
        $turmaId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));

        $result = $this->turmaService->excluir($turmaId, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['message']) ? $result['message'] : 'Não foi possivel excluir a turma.');
            return $this->redirect('/admin/turmas/editar?turma_id=' . $turmaId);
        }

        Session::flash('success', 'Turma excluida e enviada para a lixeira.');
        return $this->redirect('/admin/turmas');
    }

    public function updateStatus(Request $request)
    {
        $turmaId = (int) $request->input('id', 0);
        $status = trim((string) $request->input('status', ''));
        $justificativa = trim((string) $request->input('justificativa', ''));

        $result = $this->turmaService->atualizarStatus($turmaId, $status, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['message']) ? $result['message'] : 'Não foi possivel atualizar o status da turma.');
            return $this->redirect('/admin/turmas/show?turma_id=' . $turmaId);
        }

        Session::flash('success', 'Status da turma atualizado com sucesso.');
        return $this->redirect('/admin/turmas/show?turma_id=' . $turmaId);
    }
}


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
        return $this->view('admin/turmas/index', array_merge(
            array(
                'title' => 'Turmas',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->turmaService->listAdmin()
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/turmas/form', array(
            'title' => 'Nova turma',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/turmas/criar',
            'submit_label' => 'Salvar turma',
            'form_data' => $this->turmaService->formData(),
        ));
    }

    public function store(Request $request)
    {
        $result = $this->turmaService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel salvar a turma.'));
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
            'submit_label' => 'Atualizar turma',
            'form_data' => $this->turmaService->formData($turmaId),
        ));
    }

    public function update(Request $request)
    {
        $result = $this->turmaService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        $turmaId = (int) $request->input('id', 0);

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel atualizar a turma.'));
            return $this->redirect('/admin/turmas/editar?turma_id=' . $turmaId);
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

        $result = $this->turmaService->atualizarStatus($turmaId, $status, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['message']) ? $result['message'] : 'Não foi possivel atualizar o status da turma.');
            return $this->redirect('/admin/turmas/show?turma_id=' . $turmaId);
        }

        Session::flash('success', 'Status da turma atualizado com sucesso.');
        return $this->redirect('/admin/turmas/show?turma_id=' . $turmaId);
    }
}


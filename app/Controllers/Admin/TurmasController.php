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
        $cursoId = (int) $request->query('curso_id', 0);
        $returnTo = $this->sanitizeReturnTo((string) $request->query('return_to', ''));

        return $this->view('admin/turmas/form', array(
            'title' => 'Nova turma',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/turmas/criar',
            'form_data' => $this->turmaService->formData(null, $cursoId > 0 ? $cursoId : null),
            'curso_id' => $cursoId,
            'curso_locked' => $cursoId > 0 && $returnTo !== '',
            'return_to' => $returnTo,
        ));
    }

    public function store(Request $request)
    {
        $input = $request->all();
        $returnTo = $this->sanitizeReturnTo((string) $request->input('return_to', ''));
        $cursoContextoId = (int) $request->input('curso_id', 0);
        if ($returnTo !== '' && $cursoContextoId > 0) {
            $input['curso_evento_id'] = $cursoContextoId;
        }
        $action = $this->submitAction($request, 'save_exit');
        if ($action === 'save_copy') {
            $result = $this->turmaService->duplicar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível criar a cópia da turma.'));
                Session::flash('old', $input);
                return $this->redirect($this->turmaFormErrorUrl('create', $request, $cursoContextoId, $returnTo));
            }

            Session::flash('success', 'Cópia da turma criada com sucesso.');
            return $this->redirect('/admin/turmas/editar?turma_id=' . (int) $result['id']);
        }

        $result = $this->turmaService->salvar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível salvar a turma.'));
            Session::flash('old', $input);
            return $this->redirect($this->turmaFormErrorUrl('create', $request, $cursoContextoId, $returnTo));
        }

        if ($returnTo !== '') {
            Session::flash('success', 'Turma salva com sucesso.');
            return $this->redirect($returnTo);
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
        $cursoId = (int) $request->query('curso_id', 0);
        $returnTo = $this->sanitizeReturnTo((string) $request->query('return_to', ''));

        return $this->view('admin/turmas/form', array(
            'title' => 'Editar turma',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/turmas/editar',
            'form_data' => $this->turmaService->formData($turmaId, $cursoId > 0 ? $cursoId : null),
            'curso_id' => $cursoId,
            'curso_locked' => $cursoId > 0 && $returnTo !== '',
            'return_to' => $returnTo,
        ));
    }

    public function update(Request $request)
    {
        $input = $request->all();
        $turmaId = (int) $request->input('id', 0);
        $cursoContextoId = (int) $request->input('curso_id', 0);
        $returnTo = $this->sanitizeReturnTo((string) $request->input('return_to', ''));
        $turmaAtual = $turmaId > 0 ? $this->turmaService->formData($turmaId) : array();
        $turmaAtual = isset($turmaAtual['turma']) && is_array($turmaAtual['turma']) ? $turmaAtual['turma'] : null;
        if ($returnTo !== '' && $cursoContextoId > 0 && $turmaAtual && (int) $turmaAtual['curso_evento_id'] !== $cursoContextoId) {
            Session::flash('errors', array('A turma selecionada não pertence ao curso vinculado a esta tela.'));
            Session::flash('old', $input);
            return $this->redirect($this->turmaFormErrorUrl('edit', $request, $cursoContextoId, $returnTo, $turmaId));
        }
        if ($returnTo !== '' && $cursoContextoId > 0) {
            $input['curso_evento_id'] = $cursoContextoId;
        }
        $action = $this->submitAction($request, 'save_exit');
        if ($action === 'save_copy') {
            $result = $this->turmaService->duplicar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível criar a cópia da turma.'));
                Session::flash('old', $input);
                return $this->redirect($this->turmaFormErrorUrl('edit', $request, $cursoContextoId, $returnTo, $turmaId));
            }

            Session::flash('success', 'Cópia da turma criada com sucesso.');
            return $this->redirect('/admin/turmas/editar?turma_id=' . (int) $result['id']);
        }

        $result = $this->turmaService->salvar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível atualizar a turma.'));
            Session::flash('old', $input);
            return $this->redirect($this->turmaFormErrorUrl('edit', $request, $cursoContextoId, $returnTo, $turmaId));
        }

        if ($returnTo !== '') {
            Session::flash('success', 'Turma atualizada com sucesso.');
            return $this->redirect($returnTo);
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
        $returnTo = $this->sanitizeReturnTo((string) $request->query('return_to', ''));
        $formData = $this->turmaService->formData($turmaId);

        if (empty($formData['turma'])) {
            Session::flash('errors', array('Turma não encontrada.'));
            return $this->redirect('/admin/turmas');
        }

        return $this->view('admin/turmas/show', array_merge(
            array(
                'title' => 'Turma',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'return_to' => $returnTo,
            ),
            $formData
        ));
    }

    public function destroy(Request $request)
    {
        $turmaId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));
        $returnTo = $this->sanitizeReturnTo((string) $request->input('return_to', ''));

        $result = $this->turmaService->excluir($turmaId, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['message']) ? $result['message'] : 'Não foi possível excluir a turma.');
            return $this->redirect($returnTo !== '' ? $returnTo : $this->turmaFormErrorUrl('edit', $request, (int) $request->input('curso_id', 0), $returnTo, $turmaId));
        }

        Session::flash('success', 'Turma excluida e enviada para a lixeira.');
        return $this->redirect($returnTo !== '' ? $returnTo : '/admin/turmas');
    }

    public function updateStatus(Request $request)
    {
        $turmaId = (int) $request->input('id', 0);
        $status = trim((string) $request->input('status', ''));
        $justificativa = trim((string) $request->input('justificativa', ''));
        $returnTo = $this->sanitizeReturnTo((string) $request->input('return_to', ''));

        $result = $this->turmaService->atualizarStatus($turmaId, $status, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['message']) ? $result['message'] : 'Não foi possível atualizar o status da turma.');
            return $this->redirect($returnTo !== '' ? $returnTo : $this->turmaFormErrorUrl('show', $request, (int) $request->input('curso_id', 0), $returnTo, $turmaId));
        }

        Session::flash('success', 'Status da turma atualizado com sucesso.');
        return $this->redirect($returnTo !== '' ? $returnTo : $this->turmaShowUrl($turmaId, $returnTo));
    }

    private function turmaFormErrorUrl($modo, Request $request, $cursoId = 0, $returnTo = '', $turmaId = 0)
    {
        $cursoId = (int) $cursoId;
        $turmaId = (int) $turmaId;
        $url = $modo === 'show'
            ? '/admin/turmas/show?turma_id=' . $turmaId
            : '/admin/turmas/criar';

        if ($modo === 'edit') {
            $url = '/admin/turmas/editar?turma_id=' . $turmaId;
        }

        if ($cursoId > 0) {
            $url .= ($modo === 'show' ? '&' : '?') . 'curso_id=' . $cursoId;
        }

        if ($returnTo !== '') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'return_to=' . urlencode($returnTo);
        }

        return $url;
    }

    private function turmaShowUrl($turmaId, $returnTo = '')
    {
        $url = '/admin/turmas/show?turma_id=' . (int) $turmaId;
        if ($returnTo !== '') {
            $url .= '&return_to=' . urlencode($returnTo);
        }

        return $url;
    }

    private function sanitizeReturnTo($returnTo)
    {
        $returnTo = trim((string) $returnTo);
        if ($returnTo === '') {
            return '';
        }

        if (strpos($returnTo, '/') !== 0) {
            return '';
        }

        return $returnTo;
    }
}


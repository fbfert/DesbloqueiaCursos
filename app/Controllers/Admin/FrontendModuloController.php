<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\FrontendModuloService;

class FrontendModuloController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new FrontendModuloService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/frontend/modulos/index', array_merge(
            array(
                'title' => 'Módulos do frontend',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->service->listAdmin()
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/frontend/modulos/form', array(
            'title' => 'Novo módulo do frontend',
            'action_url' => '/admin/frontend/modulos/criar',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'form_data' => $this->service->formData(),
        ));
    }

    public function store(Request $request)
    {
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');

        if ($action === 'save_copy') {
            $result = $this->service->duplicar($input, isset($_FILES) ? $_FILES : array(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível salvar o módulo.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/frontend/modulos/criar');
            }

            Session::flash('success', 'Cópia do módulo criada com sucesso.');
            return $this->redirect('/admin/frontend/modulos/editar?modulo_id=' . (int) $result['id']);
        }

        $result = $this->service->salvar($input, isset($_FILES) ? $_FILES : array(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível salvar o módulo.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/frontend/modulos/criar');
        }
        if ($action === 'save_stay') {
            Session::flash('success', 'Módulo salvo com sucesso.');
            return $this->redirect('/admin/frontend/modulos/editar?modulo_id=' . (int) $result['id']);
        }
        if ($action === 'save_new') {
            Session::flash('success', 'Módulo salvo com sucesso. Você já pode criar um novo módulo.');
            return $this->redirect('/admin/frontend/modulos/criar');
        }
        Session::flash('success', 'Módulo salvo com sucesso.');
        return $this->redirect('/admin/frontend/modulos');
    }

    public function edit(Request $request)
    {
        $moduloId = (int) $request->query('modulo_id', 0);
        return $this->view('admin/frontend/modulos/form', array(
            'title' => 'Editar módulo do frontend',
            'action_url' => '/admin/frontend/modulos/editar',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'form_data' => $this->service->formData($moduloId),
        ));
    }

    public function update(Request $request)
    {
        $moduloId = (int) $request->input('id', 0);
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');

        if ($action === 'save_copy') {
            $result = $this->service->duplicar($input, isset($_FILES) ? $_FILES : array(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível criar a cópia do módulo.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/frontend/modulos/editar?modulo_id=' . $moduloId);
            }

            Session::flash('success', 'Cópia do módulo criada com sucesso.');
            return $this->redirect('/admin/frontend/modulos/editar?modulo_id=' . (int) $result['id']);
        }

        $result = $this->service->salvar($input, isset($_FILES) ? $_FILES : array(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível atualizar o módulo.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/frontend/modulos/editar?modulo_id=' . $moduloId);
        }
        if ($action === 'save_stay') {
            Session::flash('success', 'Módulo atualizado com sucesso.');
            return $this->redirect('/admin/frontend/modulos/editar?modulo_id=' . (int) $result['id']);
        }
        if ($action === 'save_new') {
            Session::flash('success', 'Módulo atualizado com sucesso. Você já pode criar um novo módulo.');
            return $this->redirect('/admin/frontend/modulos/criar');
        }
        Session::flash('success', 'Módulo atualizado com sucesso.');
        return $this->redirect('/admin/frontend/modulos');
    }

    public function destroy(Request $request)
    {
        $moduloId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));
        $result = $this->service->excluir($moduloId, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível excluir o módulo.'));
            return $this->redirect('/admin/frontend/modulos');
        }

        Session::flash('success', 'Módulo excluído e enviado para a lixeira.');
        return $this->redirect('/admin/frontend/modulos');
    }
}

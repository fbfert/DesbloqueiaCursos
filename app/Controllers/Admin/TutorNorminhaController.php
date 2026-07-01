<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\TutorNorminhaService;

class TutorNorminhaController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new TutorNorminhaService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/tutor-norminha/index', array_merge(
            array(
                'title' => 'Tutor Virtual Norminha',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->service->listAdmin($request->queryAll())
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/tutor-norminha/form', array(
            'title' => 'Nova fala da Norminha',
            'action_url' => '/admin/tutor-norminha/salvar',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'oldInput' => Session::pullFlash('old_input', array()),
            'form_data' => $this->service->formData(),
        ));
    }

    public function edit(Request $request)
    {
        $id = (int) $request->query('id', 0);
        $formData = $this->service->formData($id);
        if (empty($formData['fala']) || empty($formData['fala']['id'])) {
            Session::flash('errors', array('Fala da Norminha não encontrada.'));
            return $this->redirect('/admin/tutor-norminha');
        }

        return $this->view('admin/tutor-norminha/form', array(
            'title' => 'Editar fala da Norminha',
            'action_url' => '/admin/tutor-norminha/salvar',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'oldInput' => Session::pullFlash('old_input', array()),
            'form_data' => $formData,
        ));
    }

    public function salvar(Request $request)
    {
        $input = $request->all();
        $result = $this->service->salvar($input, isset($_FILES) ? $_FILES : array(), Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', $this->normalizeErrors(isset($result['errors']) ? $result['errors'] : array('Não foi possível salvar a fala.')));
            Session::flash('old_input', $input);

            $id = isset($input['id']) ? (int) $input['id'] : 0;
            return $this->redirect($id > 0 ? '/admin/tutor-norminha/editar?id=' . $id : '/admin/tutor-norminha/criar');
        }

        Session::flash('success', 'Fala salva com sucesso.');
        return $this->redirect('/admin/tutor-norminha/editar?id=' . (int) $result['id']);
    }

    public function status(Request $request)
    {
        $id = (int) $request->input('id', 0);
        $ativo = !empty($request->input('ativo', 0)) ? 1 : 0;

        $result = $this->service->alternarStatus($id, $ativo, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível atualizar o status da fala.'));
            return $this->redirect('/admin/tutor-norminha');
        }

        Session::flash('success', $ativo ? 'Fala ativada.' : 'Fala desativada.');
        return $this->redirect('/admin/tutor-norminha');
    }

    public function configuracoes(Request $request)
    {
        return $this->view('admin/tutor-norminha/configuracoes', array(
            'title' => 'Configurações da Norminha',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'oldInput' => Session::pullFlash('old_input', array()),
            'configuracoes' => $this->service->configuracoesFormData(),
        ));
    }

    public function salvarConfiguracoes(Request $request)
    {
        $input = $request->all();
        $result = $this->service->salvarConfiguracoes($input, isset($_FILES) ? $_FILES : array(), Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', $this->normalizeErrors(isset($result['errors']) ? $result['errors'] : array('Não foi possível salvar as configurações da Norminha.')));
            Session::flash('old_input', $input);
            return $this->redirect('/admin/tutor-norminha/configuracoes');
        }

        Session::flash('success', 'Configurações da Norminha salvas com sucesso.');
        return $this->redirect('/admin/tutor-norminha/configuracoes');
    }

    private function normalizeErrors(array $errors)
    {
        $lista = array();
        foreach ($errors as $erro) {
            if (is_array($erro)) {
                foreach ($erro as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $lista[] = $item;
                    }
                }
                continue;
            }

            if (is_string($erro) && trim($erro) !== '') {
                $lista[] = $erro;
            }
        }

        return $lista ?: array('Não foi possível salvar a fala.');
    }
}

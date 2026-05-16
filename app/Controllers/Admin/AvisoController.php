<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AvisoService;

class AvisoController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new AvisoService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/avisos/index', array_merge(
            array(
                'title' => 'Avisos',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->service->listar($request->all())
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/avisos/form', array(
            'title' => 'Novo aviso',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'old' => Session::pullFlash('old', array()),
            'action_url' => '/admin/avisos/criar',
            'submit_label' => 'Salvar aviso',
            'form_data' => $this->service->formData(),
        ));
    }

    public function store(Request $request)
    {
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');
        $result = $this->service->salvar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', $result['errors'] ?? array('Não foi possível salvar o aviso.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/avisos/criar');
        }

        if ($action === 'save_stay') {
            Session::flash('success', 'Aviso salvo com sucesso.');
            return $this->redirect('/admin/avisos/editar?aviso_id=' . (int) $result['id']);
        }

        if ($action === 'save_new') {
            Session::flash('success', 'Aviso salvo com sucesso. Você já pode criar um novo aviso.');
            return $this->redirect('/admin/avisos/criar');
        }

        Session::flash('success', 'Aviso salvo com sucesso.');
        return $this->redirect('/admin/avisos');
    }

    public function edit(Request $request)
    {
        $avisoId = (int) $request->query('aviso_id', 0);
        $aviso = $this->service->encontrar($avisoId);
        if (empty($aviso)) {
            Session::flash('errors', array('Aviso não encontrado.'));
            return $this->redirect('/admin/avisos');
        }

        return $this->view('admin/avisos/form', array(
            'title' => 'Editar aviso',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'old' => Session::pullFlash('old', array()),
            'action_url' => '/admin/avisos/editar',
            'submit_label' => 'Atualizar aviso',
            'form_data' => $this->service->formData($avisoId),
        ));
    }

    public function update(Request $request)
    {
        $avisoId = (int) $request->input('id', 0);
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');
        $result = $this->service->salvar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', $result['errors'] ?? array('Não foi possível atualizar o aviso.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/avisos/editar?aviso_id=' . $avisoId);
        }

        if ($action === 'save_stay') {
            Session::flash('success', 'Aviso atualizado com sucesso.');
            return $this->redirect('/admin/avisos/editar?aviso_id=' . (int) $result['id']);
        }

        if ($action === 'save_new') {
            Session::flash('success', 'Aviso atualizado com sucesso. Você já pode criar um novo aviso.');
            return $this->redirect('/admin/avisos/criar');
        }

        Session::flash('success', 'Aviso atualizado com sucesso.');
        return $this->redirect('/admin/avisos');
    }

    public function show(Request $request)
    {
        $avisoId = (int) $request->query('aviso_id', 0);
        $aviso = $this->service->encontrar($avisoId);
        if (empty($aviso)) {
            Session::flash('errors', array('Aviso não encontrado.'));
            return $this->redirect('/admin/avisos');
        }

        return $this->view('admin/avisos/show', array(
            'title' => 'Aviso',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'aviso' => $aviso,
            'resumo_destinatarios' => $this->service->resumoDestinatarios($avisoId),
        ));
    }

    public function destroy(Request $request)
    {
        $avisoId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));
        $result = $this->service->excluir($avisoId, Session::get('usuario_id'), $justificativa, $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível excluir o aviso.'));
            return $this->redirect('/admin/avisos/show?aviso_id=' . $avisoId);
        }

        Session::flash('success', 'Aviso enviado para a lixeira.');
        return $this->redirect('/admin/avisos');
    }

    public function enviar(Request $request)
    {
        $avisoId = (int) $request->input('id', 0);
        $result = $this->service->enviar($avisoId, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível enviar o aviso.'));
            return $this->redirect('/admin/avisos/show?aviso_id=' . $avisoId);
        }

        Session::flash('success', 'Aviso enviado com sucesso.');
        return $this->redirect('/admin/avisos/show?aviso_id=' . $avisoId);
    }

    public function destinatarios(Request $request)
    {
        $avisoId = (int) $request->query('aviso_id', 0);
        $aviso = $this->service->encontrar($avisoId);
        if (empty($aviso)) {
            Session::flash('errors', array('Aviso não encontrado.'));
            return $this->redirect('/admin/avisos');
        }

        return $this->view('admin/avisos/destinatarios', array_merge(
            array(
                'title' => 'Destinatários do aviso',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'aviso' => $aviso,
            ),
            $this->service->destinatariosDoAviso($avisoId, $request->all())
        ));
    }

    public function alunos(Request $request)
    {
        return $this->json(array(
            'ok' => true,
            'alunos' => $this->service->buscarAlunos($request->all()),
        ));
    }
}

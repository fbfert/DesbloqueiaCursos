<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\RbacService;
use App\Services\EmailModeloService;

class EmailModelosController extends Controller
{
    private $service;
    private $rbacService;

    public function __construct()
    {
        $this->service = new EmailModeloService();
        $this->rbacService = new RbacService();
    }

    private function isSuperAdmin()
    {
        return $this->rbacService->isSuperAdmin(Session::get('usuario_id'));
    }

    public function index(Request $request)
    {
        $busca = trim((string) $request->query('q', ''));

        return $this->view('admin/emails/modelos/index', array(
            'title' => 'Modelos de e-mail',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'busca' => $busca,
            'modelos' => $this->service->listar($busca),
            'is_superadmin' => $this->isSuperAdmin(),
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/emails/modelos/form', array(
            'title' => 'Novo modelo de e-mail',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'old' => Session::pullFlash('old', array()),
            'action_url' => '/admin/emails/modelos/criar',
            'submit_label' => 'Salvar modelo',
            'form_data' => $this->service->formData(null, $request->query('evento')),
            'placeholder_inventario' => $this->service->inventarioPlaceholders((string) $request->query('evento', '')),
            'placeholder_avisos' => array('desconhecidos' => array(), 'incompativeis' => array()),
            'is_superadmin' => $this->isSuperAdmin(),
        ));
    }

    public function store(Request $request)
    {
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');

        if ($action === 'save_copy') {
            $result = $this->service->duplicar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível salvar o modelo de e-mail.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/emails/modelos/criar');
            }

            Session::flash('success', 'Cópia do modelo de e-mail criada com sucesso.');
            return $this->redirect('/admin/emails/modelos/editar?modelo_id=' . (int) $result['id']);
        }

        $result = $this->service->save($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível salvar o modelo de e-mail.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/emails/modelos/criar');
        }

        if ($action === 'save_stay') {
            Session::flash('success', 'Modelo salvo com sucesso.');
            return $this->redirect('/admin/emails/modelos/editar?modelo_id=' . (int) $result['id']);
        }

        if ($action === 'save_new') {
            Session::flash('success', 'Modelo salvo com sucesso. Você já pode criar um novo modelo.');
            return $this->redirect('/admin/emails/modelos/criar');
        }

        Session::flash('success', 'Modelo salvo com sucesso.');
        return $this->redirect('/admin/emails/modelos');
    }

    public function edit(Request $request)
    {
        $modeloId = (int) $request->query('modelo_id', 0);
        $evento = (string) $request->query('evento', '');
        $formData = $modeloId > 0
            ? $this->service->formData($modeloId)
            : $this->service->formData(null, $evento);

        if (empty($formData) || (empty($formData['id']) && trim($evento) === '')) {
            Session::flash('errors', array('Modelo de e-mail não encontrado.'));
            return $this->redirect('/admin/emails/modelos');
        }

        $eventoModelo = (string) ($formData['evento'] ?? '');

        return $this->view('admin/emails/modelos/form', array(
            'title' => 'Editar modelo de e-mail',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'old' => Session::pullFlash('old', array()),
            'action_url' => '/admin/emails/modelos/editar',
            'submit_label' => 'Atualizar modelo',
            'form_data' => $formData,
            'placeholder_inventario' => $this->service->inventarioPlaceholders($eventoModelo),
            'placeholder_avisos' => $this->service->analisarModeloParaAdmin(
                isset($formData['assunto']) ? $formData['assunto'] : '',
                isset($formData['corpo_html']) ? $formData['corpo_html'] : '',
                $eventoModelo
            ),
            'is_superadmin' => $this->isSuperAdmin(),
        ));
    }

    public function update(Request $request)
    {
        $modeloId = (int) $request->input('id', 0);
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');
        $stored = $modeloId > 0 ? $this->service->formData($modeloId) : $this->service->formData(null, isset($input['evento']) ? $input['evento'] : '');

        if ($action === 'save_copy') {
            if (!empty($stored['is_default_event']) && !$this->isSuperAdmin()) {
                Session::flash('errors', array('Somente o superadministrador pode alterar os modelos padrão de e-mail.'));
                return $this->redirect('/admin/emails/modelos/editar?modelo_id=' . $modeloId);
            }

            $result = $this->service->duplicar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível criar a cópia do modelo de e-mail.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/emails/modelos/editar?modelo_id=' . $modeloId);
            }

            Session::flash('success', 'Cópia do modelo de e-mail criada com sucesso.');
            return $this->redirect('/admin/emails/modelos/editar?modelo_id=' . (int) $result['id']);
        }

        if (!empty($stored['is_default_event']) && !$this->isSuperAdmin()) {
            Session::flash('errors', array('Somente o superadministrador pode alterar os modelos padrão de e-mail.'));
            return $this->redirect('/admin/emails/modelos/editar?modelo_id=' . $modeloId);
        }

        $result = $this->service->save($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível atualizar o modelo de e-mail.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/emails/modelos/editar?modelo_id=' . $modeloId);
        }

        if ($action === 'save_stay') {
            Session::flash('success', 'Modelo atualizado com sucesso.');
            return $this->redirect('/admin/emails/modelos/editar?modelo_id=' . (int) $result['id']);
        }

        if ($action === 'save_new') {
            Session::flash('success', 'Modelo atualizado com sucesso. Você já pode criar um novo modelo.');
            return $this->redirect('/admin/emails/modelos/criar');
        }

        Session::flash('success', 'Modelo atualizado com sucesso.');
        return $this->redirect('/admin/emails/modelos');
    }

    public function toggleStatus(Request $request)
    {
        $modeloId = (int) $request->input('id', 0);
        $stored = $this->service->formData($modeloId);
        if (!empty($stored['is_default_event']) && !$this->isSuperAdmin()) {
            Session::flash('errors', array('Somente o superadministrador pode alterar os modelos padrão de e-mail.'));
            return $this->redirect('/admin/emails/modelos/editar?modelo_id=' . $modeloId);
        }

        $ativo = !empty($request->input('ativo', 0)) ? 1 : 0;
        $result = $this->service->alternarAtivo($modeloId, $ativo, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível atualizar o status do modelo.'));
            return $this->redirect('/admin/emails/modelos');
        }

        Session::flash('success', $ativo ? 'Modelo ativado.' : 'Modelo desativado.');
        return $this->redirect('/admin/emails/modelos');
    }

    public function restoreDefault(Request $request)
    {
        $modeloId = (int) $request->input('id', 0);
        $stored = $this->service->formData($modeloId);
        if (!empty($stored['is_default_event']) && !$this->isSuperAdmin()) {
            Session::flash('errors', array('Somente o superadministrador pode alterar os modelos padrão de e-mail.'));
            return $this->redirect('/admin/emails/modelos/editar?modelo_id=' . $modeloId);
        }

        $result = $this->service->restaurarPadrao($modeloId, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível restaurar o conteúdo padrão.'));
            return $this->redirect('/admin/emails/modelos/editar?modelo_id=' . $modeloId);
        }

        Session::flash('success', 'Conteúdo padrão restaurado.');
        return $this->redirect('/admin/emails/modelos/editar?modelo_id=' . (int) $result['id']);
    }
}

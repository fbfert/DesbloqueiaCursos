<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\CertificadoPlaceholderService;
use App\Services\CertificadoTemplateService;

class CertificadosTemplatesController extends Controller
{
    private $service;
    private $placeholderService;

    public function __construct()
    {
        $this->service = new CertificadoTemplateService();
        $this->placeholderService = new CertificadoPlaceholderService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/certificados/templates/index', array(
            'title' => 'Templates de certificados',
            'templates' => $this->service->listAdmin(),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/certificados/templates/form', array(
            'title' => 'Novo template de certificado',
            'action_url' => '/admin/certificados/templates/criar',
            'form_data' => $this->service->formData(),
            'placeholders' => $this->placeholderService->catalogo(),
            'old' => Session::pullFlash('old_input', array()),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    public function store(Request $request)
    {
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');

        if ($action === 'save_copy') {
            $result = $this->service->save($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        } else {
            $result = $this->service->save($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        }

        $redirect = $this->redirectAfterCrudSave($request, $result, array(
            'create_url' => '/admin/certificados/templates/criar',
            'edit_url_pattern' => '/admin/certificados/templates/editar?template_id={id}',
            'list_url' => '/admin/certificados/templates',
            'success_messages' => array(
                'default' => 'Template salvo com sucesso.',
            ),
        ));

        if (empty($redirect['ok'])) {
            Session::flash('errors', !empty($redirect['errors']) ? $redirect['errors'] : array('Não foi possível salvar o template.'));
            Session::flash('old_input', $input);
            return $this->redirect($redirect['url'] ?: '/admin/certificados/templates/criar');
        }

        Session::flash('success', $redirect['message']);
        return $this->redirect($redirect['url']);
    }

    public function edit(Request $request)
    {
        $id = (int) $request->query('template_id', 0);
        $template = $this->service->find($id);

        if (!$template) {
            Session::flash('errors', array('Template não encontrado.'));
            return $this->redirect('/admin/certificados/templates');
        }

        return $this->view('admin/certificados/templates/form', array(
            'title' => 'Editar template de certificado',
            'action_url' => '/admin/certificados/templates/editar',
            'form_data' => $template,
            'placeholders' => $this->placeholderService->catalogo(),
            'old' => Session::pullFlash('old_input', array()),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    public function update(Request $request)
    {
        $input = $request->all();
        $id = (int) $request->input('id', 0);

        $result = $this->service->save($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        $redirect = $this->redirectAfterCrudSave($request, $result, array(
            'create_url' => '/admin/certificados/templates/criar',
            'edit_url_pattern' => '/admin/certificados/templates/editar?template_id={id}',
            'list_url' => '/admin/certificados/templates',
            'success_messages' => array(
                'default' => 'Template atualizado com sucesso.',
            ),
        ));

        if (empty($redirect['ok'])) {
            Session::flash('errors', !empty($redirect['errors']) ? $redirect['errors'] : array('Não foi possível atualizar o template.'));
            Session::flash('old_input', $input);
            return $this->redirect($redirect['url'] ?: ('/admin/certificados/templates/editar?template_id=' . $id));
        }

        Session::flash('success', $redirect['message']);
        return $this->redirect($redirect['url']);
    }

    public function excluir(Request $request)
    {
        $id = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));
        $result = $this->service->excluir($id, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível enviar o template para a lixeira.'));
            return $this->redirect('/admin/certificados/templates');
        }

        Session::flash('success', 'Template enviado para a lixeira.');
        return $this->redirect('/admin/certificados/templates');
    }

    public function duplicar(Request $request)
    {
        $id = (int) $request->input('id', 0);
        $result = $this->service->duplicar($id, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível duplicar o template.'));
            return $this->redirect('/admin/certificados/templates');
        }

        Session::flash('success', 'Template duplicado com sucesso.');
        return $this->redirect('/admin/certificados/templates/editar?template_id=' . (int) $result['id']);
    }

    public function definirPadrao(Request $request)
    {
        $id = (int) $request->input('id', 0);
        $result = $this->service->definirPadrao($id, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível definir o template como padrão.'));
            return $this->redirect('/admin/certificados/templates');
        }

        Session::flash('success', 'Template padrão global atualizado.');
        return $this->redirect('/admin/certificados/templates');
    }

    public function preview(Request $request)
    {
        $id = (int) $request->query('template_id', 0);
        $template = $this->service->find($id);

        if (!$template) {
            Session::flash('errors', array('Template não encontrado.'));
            return $this->redirect('/admin/certificados/templates');
        }

        $contexto = $this->placeholderService->contextoPreview();
        $html = $this->placeholderService->renderizar((string) ($template['corpo_html'] ?? ''), $contexto, array('escape' => true));
        $css = (string) ($template['css'] ?? '');

        return $this->view('admin/certificados/templates/preview', array(
            'title' => 'Pré-visualização do template',
            'template' => $template,
            'rendered_html' => $html,
            'rendered_css' => $css,
        ));
    }
}


<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Services\CertificadoService;
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
            'preview_signature_url' => '',
            'old' => Session::pullFlash('old_input', array()),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    public function store(Request $request)
    {
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');
        $files = isset($_FILES) && is_array($_FILES) ? $_FILES : array();

        if ($action === 'save_copy') {
            $result = $this->service->save($input, Session::get('usuario_id'), $request->ip(), $request->userAgent(), $files);
        } else {
            $result = $this->service->save($input, Session::get('usuario_id'), $request->ip(), $request->userAgent(), $files);
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
        $template = null;
        $falhaCarregandoTemplate = false;

        try {
            $template = $this->service->find($id);
        } catch (\Throwable $exception) {
            $falhaCarregandoTemplate = true;
            Logger::error('admin.certificados_templates.editar.falhou', array(
                'template_id' => $id,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ));
        }

        if (!$template) {
            if ($falhaCarregandoTemplate && $id > 0) {
                $template = $this->service->formData();
                $template['id'] = $id;
            Session::flash('errors', array('Não foi possível carregar o template selecionado.'));
            } else {
                Session::flash('errors', array('Template não encontrado.'));
                return $this->redirect('/admin/certificados/templates');
            }
        }

        $previewSignatureUrl = '';
        try {
            $certificadoService = new CertificadoService();
            $previewSignatureUrl = $certificadoService->resolverImagemPublicaOuDataUri($template['assinatura_url'] ?? '');
        } catch (\Throwable $exception) {
            Logger::error('admin.certificados_templates.previa_assinatura.falhou', array(
                'template_id' => $id,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ));
        }

        return $this->view('admin/certificados/templates/form', array(
            'title' => 'Editar template de certificado',
            'action_url' => '/admin/certificados/templates/editar',
            'form_data' => $template,
            'placeholders' => $this->placeholderService->catalogo(),
            'preview_signature_url' => $previewSignatureUrl,
            'old' => Session::pullFlash('old_input', array()),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    public function update(Request $request)
    {
        $input = $request->all();
        $id = (int) $request->input('id', 0);
        $files = isset($_FILES) && is_array($_FILES) ? $_FILES : array();

        $result = $this->service->save($input, Session::get('usuario_id'), $request->ip(), $request->userAgent(), $files);

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
        try {
            $template = $this->service->find($id);
        } catch (\Throwable $exception) {
            Logger::error('admin.certificados_templates.preview.falhou', array(
                'template_id' => $id,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ));
            Session::flash('errors', array('Não foi possível abrir a pré-visualização do template.'));
            return $this->redirect('/admin/certificados/templates/editar?template_id=' . $id);
        }

        if (!$template) {
            Session::flash('errors', array('Template não encontrado.'));
            return $this->redirect('/admin/certificados/templates');
        }

        $certificadoService = new CertificadoService();
        $logoPublico = $certificadoService->resolverLogoTemplatePublico($template);
        $backgroundPublico = $certificadoService->resolverImagemFundoTemplatePublica($template);
        $assinaturaPublica = $certificadoService->resolverImagemPublicaOuDataUri($template['assinatura_url'] ?? '');

        $contexto = $this->placeholderService->contextoPreview();
        $contexto['logo_url'] = $logoPublico;
        $contexto['background_url'] = $backgroundPublico;
        $contexto['imagem_fundo_url'] = $backgroundPublico;
        if (!isset($contexto['instituicao']) || !is_array($contexto['instituicao'])) {
            $contexto['instituicao'] = array();
        }
        if (!isset($contexto['curso']) || !is_array($contexto['curso'])) {
            $contexto['curso'] = array();
        }
        $contexto['curso']['conteudo_programatico_tipo'] = 'modulos';
        $contexto['curso']['conteudo_programatico_texto'] = "Módulo introdutório.\nMódulo prático.";
        $contexto['curso']['conteudo_programatico_modulos'] = '[{"titulo":"Módulo 1 — Apresentação","itens":["Introdução","Organização do curso"]},{"titulo":"Módulo 2 — Prática","itens":["Exercícios","Avaliação final"]}]';
        $contexto['modulos_nomes_html'] = '<div style="font-size:9.3pt;line-height:1.36;color:#202020;"><div style="margin:0 0 4px 0;"><strong>Módulo 1:</strong> Apresentação</div><div style="margin:0 0 4px 0;"><strong>Módulo 2:</strong> Prática</div></div>';
        $contexto['modulos_conteudos_html'] = '<div style="font-size:9.3pt;line-height:1.36;color:#202020;"><div style="margin:0 0 8px 0;"><strong>Módulo 1 - Apresentação</strong><br>&nbsp;&nbsp;&bull; Introdução<br>&nbsp;&nbsp;&bull; Organização do curso</div><div style="margin:0 0 8px 0;"><strong>Módulo 2 - Prática</strong><br>&nbsp;&nbsp;&bull; Exercícios<br>&nbsp;&nbsp;&bull; Avaliação final</div></div>';
        $contexto['instituicao']['logo_url'] = $logoPublico;
        $html = $this->placeholderService->renderizar((string) ($template['corpo_html'] ?? ''), $contexto, array('escape' => true));
        $contextoSegundaPagina = $contexto;
        $contextoSegundaPagina['curso']['carga_horaria'] = trim((string) ($contextoSegundaPagina['curso']['carga_horaria'] ?? '')) !== '' ? $contextoSegundaPagina['curso']['carga_horaria'] : 'Não informado';
        $contextoSegundaPagina['curso']['ementa'] = trim((string) ($contextoSegundaPagina['curso']['ementa'] ?? '')) !== '' ? $contextoSegundaPagina['curso']['ementa'] : 'Não informado';
        $contextoSegundaPagina['curso']['objetivo_geral'] = trim((string) ($contextoSegundaPagina['curso']['objetivo_geral'] ?? '')) !== '' ? $contextoSegundaPagina['curso']['objetivo_geral'] : 'Não informado';
        $contextoSegundaPagina['curso']['professor_responsavel'] = trim((string) ($contextoSegundaPagina['curso']['professor_responsavel'] ?? '')) !== '' ? $contextoSegundaPagina['curso']['professor_responsavel'] : 'Não informado';
        $contextoSegundaPagina['turma']['nome'] = trim((string) ($contextoSegundaPagina['turma']['nome'] ?? '')) !== '' ? $contextoSegundaPagina['turma']['nome'] : 'Não informado';
        $contextoSegundaPagina['turma']['periodo'] = trim((string) ($contextoSegundaPagina['turma']['periodo'] ?? '')) !== '' ? $contextoSegundaPagina['turma']['periodo'] : 'Não informado';
        $segundaPagina = $certificadoService->renderizarSegundaPaginaCertificado($template, $contextoSegundaPagina);
        $css = (string) ($template['css'] ?? '');
        $backgroundColor = trim((string) ($template['cor_fundo'] ?? '#ffffff'));
        if ($backgroundColor === '') {
            $backgroundColor = '#ffffff';
        }
        $textColor = trim((string) ($template['cor_texto'] ?? '#111827'));
        if ($textColor === '') {
            $textColor = '#111827';
        }
        $backgroundImage = trim((string) $backgroundPublico);
        $previewWrapperStyle = 'padding:16px;background:' . $backgroundColor . ';color:' . $textColor . ';';
        if ($backgroundImage !== '') {
            $previewWrapperStyle .= 'background-image:url("' . $backgroundImage . '");background-size:cover;background-position:center center;background-repeat:no-repeat;';
        }

        return $this->view('admin/certificados/templates/preview', array(
            'title' => 'Pré-visualização do template',
            'template' => $template,
            'rendered_html' => $html,
            'rendered_second_page_html' => $segundaPagina,
            'rendered_css' => $css,
            'preview_wrapper_style' => $previewWrapperStyle,
            'preview_logo_url' => $logoPublico,
            'preview_background_url' => $backgroundPublico,
            'preview_signature_url' => $assinaturaPublica,
        ));
    }
}


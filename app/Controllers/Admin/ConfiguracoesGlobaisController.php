<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ConfiguracaoGlobalService;
use App\Services\CertificadoPlaceholderService;
use App\Services\CertificadoTemplateService;

class ConfiguracoesGlobaisController extends Controller
{
    private $service;
    private $templateService;
    private $placeholderService;

    public function __construct()
    {
        $this->service = new ConfiguracaoGlobalService();
        $this->templateService = new CertificadoTemplateService();
        $this->placeholderService = new CertificadoPlaceholderService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/configuracoes-globais/index', array(
            'title' => 'Configurações globais',
            'configuracoes' => $this->service->all(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function certificados(Request $request)
    {
        return $this->view('admin/configuracoes-globais/certificados', array(
            'title' => 'Configurações de certificados',
            'configuracao' => $this->service->certificados(),
            'templates' => $this->templateService->listAdmin(),
            'placeholders' => $this->placeholderService->catalogo(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function financeiro(Request $request)
    {
        return $this->view('admin/configuracoes-globais/financeiro', array(
            'title' => 'Configurações financeiras',
            'configuracao' => $this->service->financeiro(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function frontend(Request $request)
    {
        return $this->view('admin/configuracoes-globais/frontend', array(
            'title' => 'Configurações de frontend',
            'configuracao' => $this->service->frontend(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function seguranca(Request $request)
    {
        return $this->view('admin/configuracoes-globais/seguranca', array(
            'title' => 'Configurações de seguranca',
            'configuracao' => $this->service->seguranca(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function salvarInstitucional(Request $request)
    {
        $result = $this->service->saveInstitucional(
            $request->all(),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent(),
            isset($_FILES) ? $_FILES : array()
        );
        return $this->handleSaveResult($result, '/admin/configuracoes-globais');
    }

    public function salvarCertificados(Request $request)
    {
        $result = $this->service->saveCertificados($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->handleSaveResult($result, '/admin/configuracoes-globais/certificados');
    }

    public function salvarFinanceiro(Request $request)
    {
        $result = $this->service->saveFinanceiro($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->handleSaveResult($result, '/admin/configuracoes-globais/financeiro');
    }

    public function salvarFrontend(Request $request)
    {
        $result = $this->service->saveFrontend($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->handleSaveResult($result, '/admin/configuracoes-globais/frontend');
    }

    public function salvarSeguranca(Request $request)
    {
        $result = $this->service->saveSeguranca($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        return $this->handleSaveResult($result, '/admin/configuracoes-globais/seguranca');
    }

    private function handleSaveResult(array $result, $redirectTo)
    {
        if (empty($result['ok'])) {
            $errors = array();
            if (!empty($result['errors']) && is_array($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $errors[] = $error;
                }
            } elseif (!empty($result['message'])) {
                $errors[] = $result['message'];
            } else {
                $errors[] = 'Não foi possivel salvar as configuracoes.';
            }

            Session::flash('errors', $errors);
            return $this->redirect($redirectTo);
        }

        Session::flash('success', 'Configurações atualizadas com sucesso.');
        return $this->redirect($redirectTo);
    }
}




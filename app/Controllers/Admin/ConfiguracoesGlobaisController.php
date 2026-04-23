<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ConfiguracaoGlobalService;

class ConfiguracoesGlobaisController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new ConfiguracaoGlobalService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/configuracoes-globais/index', array(
            'title' => 'Configuracoes globais',
            'configuracoes' => $this->service->all(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function certificados(Request $request)
    {
        return $this->view('admin/configuracoes-globais/certificados', array(
            'title' => 'Configuracoes de certificados',
            'configuracao' => $this->service->certificados(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function financeiro(Request $request)
    {
        return $this->view('admin/configuracoes-globais/financeiro', array(
            'title' => 'Configuracoes financeiras',
            'configuracao' => $this->service->financeiro(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function frontend(Request $request)
    {
        return $this->view('admin/configuracoes-globais/frontend', array(
            'title' => 'Configuracoes de frontend',
            'configuracao' => $this->service->frontend(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function seguranca(Request $request)
    {
        return $this->view('admin/configuracoes-globais/seguranca', array(
            'title' => 'Configuracoes de seguranca',
            'configuracao' => $this->service->seguranca(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function salvarInstitucional(Request $request)
    {
        $result = $this->service->saveInstitucional($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
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
                $errors[] = 'Nao foi possivel salvar as configuracoes.';
            }

            Session::flash('errors', $errors);
            return $this->redirect($redirectTo);
        }

        Session::flash('success', 'Configuracoes atualizadas com sucesso.');
        return $this->redirect($redirectTo);
    }
}

<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\EmailService;

class EmailsController extends Controller
{
    private $emailService;

    public function __construct()
    {
        $this->emailService = new EmailService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/emails/index', array(
            'title' => 'Emails transacionais',
            'configuracao' => $this->emailService->configuration(),
            'emails' => $this->emailService->listQueue(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function save(Request $request)
    {
        $result = $this->emailService->saveConfiguration(
            $request->all(),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            Session::flash('errors', array('config' => isset($result['message']) ? $result['message'] : 'Nao foi possivel salvar a configuracao.'));
            return $this->redirect('/admin/emails');
        }

        Session::flash('success', 'Configuracao SMTP atualizada.');
        return $this->redirect('/admin/emails');
    }
}

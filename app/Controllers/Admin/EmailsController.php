<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Validator;
use App\Services\EmailService;
use App\Services\EmailAdminService;

class EmailsController extends Controller
{
    private $emailService;
    private $adminService;

    public function __construct()
    {
        $this->emailService = new EmailService();
        $this->adminService = new EmailAdminService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/emails/index', array(
            'title' => 'E-mails',
            'configuracao' => $this->emailService->configuration(),
            'resumo' => $this->adminService->resumo(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function fila(Request $request)
    {
        $filters = array(
            'status' => (string) $request->query('status', ''),
            'q' => (string) $request->query('q', ''),
            'de' => (string) $request->query('de', ''),
            'ate' => (string) $request->query('ate', ''),
        );
        $page = (int) $request->query('page', 1);

        $result = $this->adminService->listarFilaHistorico($filters, $page, 100);

        return $this->view('admin/emails/fila', array(
            'title' => 'Fila e histórico de e-mails',
            'filters' => $filters,
            'emails' => $result['items'],
            'pagination' => $result,
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function reenviar(Request $request)
    {
        if (!Csrf::validate($request->input('_token'))) {
            Session::flash('errors', array('Token CSRF inválido.'));
            return $this->redirect('/admin/emails/fila');
        }

        $id = (int) $request->input('id', 0);
        $result = $this->adminService->reenviarEmail($id, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Este e-mail não pode ser reenviado.'));
            return $this->redirect('/admin/emails/fila');
        }

        Session::flash('success', 'E-mail reenviado com sucesso.');
        return $this->redirect('/admin/emails/fila');
    }

    public function reenviarSelecionados(Request $request)
    {
        if (!Csrf::validate($request->input('_token'))) {
            Session::flash('errors', array('Token CSRF inválido.'));
            return $this->redirect('/admin/emails/fila');
        }

        $ids = $request->input('ids', array());
        if (!is_array($ids)) {
            $ids = array();
        }

        $result = $this->adminService->reenviarSelecionados($ids, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        Session::flash('success', (int) $result['reenviados'] . ' e-mails reenviados. ' . (int) $result['ignorados'] . ' ignorados.');
        return $this->redirect('/admin/emails/fila');
    }

    public function reenviarPendentes(Request $request)
    {
        if (!Csrf::validate($request->input('_token'))) {
            Session::flash('errors', array('Token CSRF inválido.'));
            return $this->redirect('/admin/emails');
        }

        $result = $this->adminService->reenviarPendentes(Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['reenviados'])) {
            Session::flash('success', 'Não há e-mails pendentes para reenviar.');
            return $this->redirect('/admin/emails');
        }

        Session::flash('success', (int) $result['reenviados'] . ' e-mails pendentes/falhos foram reenviados.');
        return $this->redirect('/admin/emails');
    }

    public function excluirFalha(Request $request)
    {
        if (!Csrf::validate($request->input('_token'))) {
            Session::flash('errors', array('Token CSRF inválido.'));
            return $this->redirect('/admin/emails/fila');
        }

        $id = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));

        $result = $this->adminService->excluirFalha($id, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Este e-mail não pode ser excluído.'));
            return $this->redirect('/admin/emails/fila');
        }

        Session::flash('success', isset($result['message']) ? $result['message'] : 'E-mail com falha excluído da fila.');
        return $this->redirect('/admin/emails/fila');
    }

    public function excluirFalhasSelecionadas(Request $request)
    {
        if (!Csrf::validate($request->input('_token'))) {
            Session::flash('errors', array('Token CSRF inválido.'));
            return $this->redirect('/admin/emails/fila');
        }

        $ids = $request->input('ids', array());
        if (!is_array($ids)) {
            $ids = array();
        }

        $justificativa = trim((string) $request->input('justificativa', ''));

        $result = $this->adminService->excluirFalhasSelecionadas($ids, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível excluir os e-mails com falha selecionados.'));
            return $this->redirect('/admin/emails/fila');
        }

        Session::flash('success', (int) ($result['excluidos'] ?? 0) . ' e-mails com falha foram excluídos. ' . (int) ($result['ignorados'] ?? 0) . ' ignorados.');
        return $this->redirect('/admin/emails/fila');
    }

    public function save(Request $request)
    {
        $result = $this->emailService->saveConfiguration(
            $request->all(),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        $action = $this->submitAction($request);
        if (!$result['ok']) {
            Session::flash('errors', array('config' => isset($result['message']) ? $result['message'] : 'Não foi possivel salvar a configuracao.'));
            return $this->redirect('/admin/emails');
        }

        Session::flash('success', 'Configuracao SMTP atualizada.');
        return $action === 'save_exit' ? $this->redirect('/admin/emails') : $this->redirect('/admin/emails');
    }

    public function teste(Request $request)
    {
        if (!Csrf::validate($request->input('_token'))) {
            Session::flash('errors', array('Token CSRF inválido.'));
            return $this->redirect('/admin/emails');
        }

        $destinatarioEmail = trim((string) $request->input('email_teste', ''));
        if ($destinatarioEmail === '' || !Validator::email($destinatarioEmail)) {
            Session::flash('errors', array('Informe um endereço de e-mail válido para o teste.'));
            return $this->redirect('/admin/emails');
        }

        $result = $this->emailService->sendTestEmail(
            $destinatarioEmail,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível enviar o e-mail de teste.'));
            return $this->redirect('/admin/emails');
        }

        Session::flash('success', 'E-mail de teste enviado para ' . $destinatarioEmail . '.');
        return $this->redirect('/admin/emails');
    }
}



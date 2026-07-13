<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\TurmaEmailService;

class TurmaEmailsController extends Controller
{
    private $turmaEmailService;

    public function __construct()
    {
        $this->turmaEmailService = new TurmaEmailService();
    }

    /**
     * Historico: todos os comunicados ja enviados para a turma.
     */
    public function index(Request $request)
    {
        $turmaId = (int) $request->query('turma_id', 0);
        $turma = $this->turmaEmailService->turma($turmaId);

        if (!$turma) {
            Session::flash('errors', array('Turma não encontrada.'));
            return $this->redirect('/admin/turmas');
        }

        return $this->view('admin/turmas/emails/index', array(
            'title' => 'E-mails da turma',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'turma' => $turma,
            'comunicados' => $this->turmaEmailService->historico($turmaId),
            'total_destinatarios' => count($this->turmaEmailService->destinatarios($turmaId)),
        ));
    }

    /**
     * Formulario de um novo comunicado.
     */
    public function create(Request $request)
    {
        $turmaId = (int) $request->query('turma_id', 0);
        $turma = $this->turmaEmailService->turma($turmaId);

        if (!$turma) {
            Session::flash('errors', array('Turma não encontrada.'));
            return $this->redirect('/admin/turmas');
        }

        return $this->view('admin/turmas/emails/form', array(
            'title' => 'Novo e-mail para a turma',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'turma' => $turma,
            'destinatarios' => $this->turmaEmailService->destinatarios($turmaId),
        ));
    }

    /**
     * Cria o comunicado e enfileira os destinatarios. O envio em si acontece em processar(),
     * chamado em lotes pela tela de progresso.
     */
    public function store(Request $request)
    {
        $turmaId = (int) $request->input('turma_id', 0);

        $result = $this->turmaEmailService->criarLote(
            $turmaId,
            (string) $request->input('assunto', ''),
            (string) $request->input('corpo_html', ''),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível preparar o envio.'));
            Session::flash('old', $request->all());
            return $this->redirect('/admin/turmas/emails/novo?turma_id=' . $turmaId);
        }

        return $this->redirect('/admin/turmas/emails/detalhe?lote_id=' . (int) $result['lote_id']);
    }

    /**
     * Detalhe de um comunicado: placar, lista de destinatarios e, se ainda houver pendentes,
     * a barra de progresso que dispara o envio em lotes.
     */
    public function show(Request $request)
    {
        $loteId = (int) $request->query('lote_id', 0);
        $lote = $this->turmaEmailService->lote($loteId);

        if (!$lote) {
            Session::flash('errors', array('Comunicado não encontrado.'));
            return $this->redirect('/admin/turmas');
        }

        return $this->view('admin/turmas/emails/show', array(
            'title' => 'E-mail da turma',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'lote' => $lote,
            'resumo' => $this->turmaEmailService->resumoLote($loteId),
            'destinatarios' => $this->turmaEmailService->destinatariosDoLote($loteId),
        ));
    }

    /**
     * Envia o proximo lote de pendentes. Consumido via AJAX pela tela de progresso.
     */
    public function processar(Request $request)
    {
        $loteId = (int) $request->input('lote_id', 0);
        $limite = (int) $request->input('limite', TurmaEmailService::LOTE_PADRAO);

        $result = $this->turmaEmailService->processarLote(
            $loteId,
            $limite,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }
}

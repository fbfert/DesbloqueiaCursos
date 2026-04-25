<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\CertificadoService;

class CertificadosController extends Controller
{
    private $certificadoService;

    public function __construct()
    {
        $this->certificadoService = new CertificadoService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/certificados/index', array(
            'title' => 'Certificados',
            'certificados' => $this->certificadoService->listarCertificados(),
            'aptos' => $this->certificadoService->listarAptos(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function show(Request $request)
    {
        $certificadoId = (int) $request->query('certificado_id', 0);
        $detalhe = $this->certificadoService->detalhar($certificadoId);

        if (empty($detalhe['certificado'])) {
            Session::flash('errors', array('Certificado nao encontrado.'));
            return $this->redirect('/admin/certificados');
        }

        return $this->view('admin/certificados/show', array_merge(
            array(
                'title' => 'Certificado #' . (int) $detalhe['certificado']['id'],
                'errors' => Session::pullFlash('errors', array()),
                'success' => Session::pullFlash('success'),
            ),
            $detalhe
        ));
    }

    public function emitir(Request $request)
    {
        if ($request->method() === 'GET') {
            $inscricaoId = (int) $request->query('inscricao_id', 0);
            return $this->view('admin/certificados/emitir', array(
                'title' => 'Emitir certificado',
                'aptos' => $this->certificadoService->listarAptos(),
                'templates' => $this->certificadoService->listarTemplates(),
                'selectedInscriçãoId' => $inscricaoId,
                'errors' => Session::pullFlash('errors', array()),
                'success' => Session::pullFlash('success'),
            ));
        }

        $result = $this->certificadoService->emitir(
            (int) $request->input('inscricao_id', 0),
            array(
                'template_id' => (int) $request->input('template_id', 0),
                'manter_codigo' => !empty($request->input('manter_codigo')),
            ),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possivel emitir o certificado.'));
            return $this->redirect('/admin/certificados/emitir?inscricao_id=' . (int) $request->input('inscricao_id', 0));
        }

        Session::flash('success', 'Certificado emitido com sucesso.');
        return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $result['certificado_id']);
    }

    public function reemitir(Request $request)
    {
        $result = $this->certificadoService->reemitir(
            (int) $request->input('certificado_id', 0),
            !empty($request->input('manter_codigo')),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possivel reemitir o certificado.'));
            return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $request->input('certificado_id', 0));
        }

        Session::flash('success', 'Certificado reemitido com sucesso.');
        return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $result['certificado_id']);
    }

    public function cancelar(Request $request)
    {
        $result = $this->certificadoService->cancelar(
            (int) $request->input('certificado_id', 0),
            trim((string) $request->input('observacao', '')),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possivel cancelar o certificado.'));
            return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $request->input('certificado_id', 0));
        }

        Session::flash('success', 'Certificado cancelado.');
        return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $request->input('certificado_id', 0));
    }

    public function revogar(Request $request)
    {
        $result = $this->certificadoService->revogar(
            (int) $request->input('certificado_id', 0),
            trim((string) $request->input('observacao', '')),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possivel revogar o certificado.'));
            return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $request->input('certificado_id', 0));
        }

        Session::flash('success', 'Certificado revogado.');
        return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $request->input('certificado_id', 0));
    }

    public function pdf(Request $request)
    {
        $codigo = strtoupper(trim((string) $request->query('codigo', '')));
        $pdf = $this->certificadoService->pdfBytesByCodigo($codigo);

        if ($pdf === null) {
            return new Response(View::render('errors/404', array('title' => 'Certificado nao encontrado')), 404);
        }

        return new Response($pdf, 200, array(
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificado-' . $codigo . '.pdf"',
        ));
    }
}


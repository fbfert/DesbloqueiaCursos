<?php

namespace App\Controllers;

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

    public function validar(Request $request)
    {
        if ($request->method() === 'POST' || $request->query('codigo')) {
            $codigo = $request->method() === 'POST' ? $request->input('codigo') : $request->query('codigo');
            $cpf = $request->method() === 'POST' ? $request->input('cpf') : $request->query('cpf');
            $resultado = $this->certificadoService->validarPublicamente($codigo, $cpf, $request->ip(), $request->userAgent());

            return $this->view('certificados/validar', array(
                'title' => 'Validar certificado',
                'resultado' => $resultado,
                'codigo' => $codigo,
                'cpf' => $cpf,
                'errors' => Session::pullFlash('errors', array()),
            ));
        }

        return $this->view('certificados/validar', array(
            'title' => 'Validar certificado',
            'resultado' => null,
            'codigo' => '',
            'cpf' => '',
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    public function show(Request $request)
    {
        $codigo = strtoupper(trim((string) $request->query('codigo', '')));
        $resultado = $this->certificadoService->validarPublicamente($codigo, null, $request->ip(), $request->userAgent());

        if (empty($resultado['ok'])) {
            return new Response(View::render('errors/404', array('title' => 'Certificado nao encontrado')), 404);
        }

        return $this->view('certificados/show', array(
            'title' => 'Certificado ' . $codigo,
            'certificado' => $resultado['certificado'],
            'canSeePdf' => $this->certificadoService->usuarioPodeAcessarCertificado($resultado['certificado'], Session::get('usuario_id')),
        ));
    }

    public function pdf(Request $request)
    {
        $codigo = strtoupper(trim((string) $request->query('codigo', '')));
        $resultado = $this->certificadoService->validarPublicamente($codigo, null, $request->ip(), $request->userAgent());

        if (empty($resultado['ok'])) {
            return new Response(View::render('errors/404', array('title' => 'Certificado nao encontrado')), 404);
        }

        if (!$this->certificadoService->usuarioPodeAcessarCertificado($resultado['certificado'], Session::get('usuario_id'))) {
            Session::flash('errors', array('certificado' => 'Voce nao tem permissao para abrir este PDF.'));
            return new Response(View::render('errors/403', array('title' => 'Acesso negado')), 403);
        }

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

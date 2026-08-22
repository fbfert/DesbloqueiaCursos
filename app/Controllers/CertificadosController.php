<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Logger;
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

    private function configCertificados()
    {
        return $this->certificadoService->configuracaoCertificados();
    }

    public function validar(Request $request)
    {
        $config = $this->configCertificados();
        if (empty($config['certificados_habilitado']) || empty($config['certificados_validacao_publica_habilitada'])) {
            return $this->view('certificados/validar', array(
                'title' => 'Validar certificado',
                'resultado' => array('ok' => false, 'message' => 'A validação pública está temporariamente indisponível.'),
                'codigo' => '',
                'cpf' => '',
                'errors' => Session::pullFlash('errors', array()),
            ));
        }

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
        $config = $this->configCertificados();
        if (empty($config['certificados_habilitado']) || empty($config['certificados_validacao_publica_habilitada'])) {
            return $this->view('certificados/show', array(
                'title' => 'Certificado',
                'certificado' => null,
                'canSeePdf' => false,
                'blockedMessage' => 'A validação pública está temporariamente indisponível.',
            ));
        }

        $certificado = $this->certificadoService->localizarCertificadoPublico($codigo);

        if (empty($certificado)) {
            return new Response(View::render('errors/404', array('title' => 'Certificado nao encontrado')), 404);
        }

        Logger::info('certificado.visualizacao_online', array(
            'codigo' => $codigo,
            'certificado_id' => (int) $certificado['id'],
            'usuario_id' => Session::get('usuario_id'),
        ));

        return $this->view('certificados/show', array(
            'title' => 'Certificado ' . $codigo,
            'certificado' => $certificado,
            'canSeePdf' => $this->certificadoService->usuarioPodeAcessarCertificado($certificado, Session::get('usuario_id')),
            'configCertificados' => $config,
        ));
    }

    public function versaoOnline(Request $request)
    {
        $codigo = strtoupper(trim((string) $request->query('codigo', '')));
        $config = $this->configCertificados();

        if (empty($config['certificados_habilitado']) || empty($config['certificados_validacao_publica_habilitada'])) {
            return new Response(View::render('errors/404', array('title' => 'Certificado nao encontrado')), 404);
        }

        if (!$this->codigoPublicoDoCertificadoEhValido($codigo)) {
            return new Response(View::render('errors/404', array('title' => 'Certificado nao encontrado')), 404);
        }

        $versaoOnline = $this->certificadoService->renderizarVersaoOnlinePublicaPorCodigo($codigo);
        if (empty($versaoOnline)) {
            return new Response(View::render('errors/404', array('title' => 'Certificado nao encontrado')), 404);
        }

        Logger::info('certificado.visualizacao_online_html', array(
            'codigo' => $codigo,
            'certificado_id' => (int) ($versaoOnline['certificado']['id'] ?? 0),
            'usuario_id' => Session::get('usuario_id'),
        ));

        return $this->view('certificados/versao-online', array(
            'title' => 'Certificado ' . $codigo . ' - versão online',
            'certificado' => $versaoOnline['certificado'],
            'versaoOnline' => $versaoOnline,
            'configCertificados' => $config,
            'canSeePdf' => $this->certificadoService->usuarioPodeAcessarCertificado($versaoOnline['certificado'], Session::get('usuario_id')),
        ));
    }

    public function pdf(Request $request)
    {
        $codigo = strtoupper(trim((string) $request->query('codigo', '')));
        $config = $this->configCertificados();
        if (empty($config['certificados_habilitado']) || empty($config['certificados_permitir_download'])) {
            return new Response('O download do certificado não está disponível no momento.', 403, array('Content-Type' => 'text/plain; charset=UTF-8'));
        }

        if (!$this->codigoPublicoDoCertificadoEhValido($codigo)) {
            return new Response(View::render('errors/404', array('title' => 'Certificado nao encontrado')), 404);
        }

        $certificado = $this->certificadoService->localizarCertificadoPublico($codigo);

        if (empty($certificado)) {
            return new Response(View::render('errors/404', array('title' => 'Certificado nao encontrado')), 404);
        }

        Logger::info('certificado.download_pdf_publico', array(
            'codigo' => $codigo,
            'certificado_id' => (int) $certificado['id'],
            'usuario_id' => Session::get('usuario_id'),
        ));

        try {
            $pdf = $this->certificadoService->pdfBytesByCodigo($codigo);
        } catch (\Throwable $exception) {
            Logger::error('certificado.download_pdf_publico.falhou', array(
                'codigo' => $codigo,
                'certificado_id' => (int) $certificado['id'],
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));

            return new Response('Nao foi possivel gerar o certificado.', 500, array('Content-Type' => 'text/plain; charset=UTF-8'));
        }

        if ($pdf === null) {
            return new Response(View::render('errors/404', array('title' => 'Certificado nao encontrado')), 404);
        }

        return new Response($pdf, 200, array(
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificado-' . $codigo . '.pdf"',
        ));
    }

    private function codigoPublicoDoCertificadoEhValido($codigo)
    {
        $codigo = strtoupper(trim((string) $codigo));
        if ($codigo === '') {
            return false;
        }

        $tamanho = function_exists('mb_strlen') ? mb_strlen($codigo) : strlen($codigo);
        if ($tamanho < 10 || $tamanho > 32) {
            return false;
        }

        return (bool) preg_match('/^[A-Z0-9-]+$/', $codigo);
    }
}



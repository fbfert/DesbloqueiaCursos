<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\FinanceiroService;

class FinanceiroController extends Controller
{
    private $financeiroService;

    public function __construct()
    {
        $this->financeiroService = new FinanceiroService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/financeiro/index', array_merge(
            array(
                'title' => 'Financeiro',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->financeiroService->painelAdmin()
        ));
    }

    public function repasses(Request $request)
    {
        $apuracaoId = (int) $request->query('apuracao_id', 0);

        return $this->view('admin/financeiro/repasses', array_merge(
            array(
                'title' => 'Repasses',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'apuracao_id' => $apuracaoId,
            ),
            array(
                'repasses' => $this->financeiroService->listarRepassesApuracao($apuracaoId > 0 ? $apuracaoId : null),
                'apuracoes' => $this->financeiroService->painelAdmin()['apuracoes'],
            )
        ));
    }

    public function apurar(Request $request)
    {
        $competencia = trim((string) $request->input('competencia', ''));
        $result = $this->financeiroService->apurarCompetencia(
            $competencia,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possivel apurar a competencia.'));
            return $this->redirect('/admin/financeiro');
        }

        Session::flash('success', 'Apuracao gerada com sucesso.');
        return $this->redirect('/admin/financeiro');
    }

    public function gerarRepasses(Request $request)
    {
        $apuracaoId = (int) $request->input('apuracao_id', 0);
        $result = $this->financeiroService->gerarRepasses(
            $apuracaoId,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possivel gerar os repasses.'));
            return $this->redirect('/admin/financeiro/repasses?apuracao_id=' . $apuracaoId);
        }

        Session::flash('success', 'Repasses gerados com sucesso.');
        return $this->redirect('/admin/financeiro/repasses?apuracao_id=' . $apuracaoId);
    }

    public function salvarProfessorFiscal(Request $request)
    {
        $result = $this->financeiroService->salvarProfessorFiscal(
            $request->all(),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possivel salvar o perfil fiscal.'));
            return $this->redirect('/admin/financeiro');
        }

        Session::flash('success', 'Perfil fiscal salvo com sucesso.');
        return $this->redirect('/admin/financeiro');
    }

    public function registrarDocumento(Request $request)
    {
        $repasseId = (int) $request->input('repasse_id', 0);
        $arquivo = isset($_FILES['arquivo']) ? $_FILES['arquivo'] : array();

        $result = $this->financeiroService->registrarDocumento(
            $repasseId,
            $arquivo,
            trim((string) $request->input('tipo_documento', 'outro')),
            trim((string) $request->input('numero_documento', '')),
            trim((string) $request->input('observacao', '')),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possivel registrar o documento.'));
        } else {
            Session::flash('success', 'Documento registrado com sucesso.');
        }

        return $this->redirect('/admin/financeiro/repasses');
    }

    public function registrarPagamento(Request $request)
    {
        $repasseId = (int) $request->input('repasse_id', 0);
        $arquivo = isset($_FILES['comprovante']) ? $_FILES['comprovante'] : array();

        $result = $this->financeiroService->registrarPagamento(
            $repasseId,
            $request->all(),
            !empty($arquivo) ? $arquivo : null,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possivel registrar o pagamento.'));
        } else {
            Session::flash('success', 'Pagamento registrado com sucesso.');
        }

        return $this->redirect('/admin/financeiro/repasses');
    }
}



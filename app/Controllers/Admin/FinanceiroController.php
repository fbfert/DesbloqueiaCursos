<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
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
        $payload = array();
        try {
            $payload = $this->financeiroService->painelAdmin($request->queryAll());
        } catch (\Throwable $throwable) {
            Logger::error('admin.financeiro.index_falha', array(
                'message' => $throwable->getMessage(),
            ));
            Session::flash('errors', array('Não foi possível carregar os dados financeiros. Exibindo visão resumida.'));
            $payload = array(
                'configuracao_financeira' => array(),
                'apuracoes' => array(),
                'repasses' => array(),
                'professores_fiscal' => array(),
                'professores' => array(),
                'filtros_entradas' => array(),
                'entradas_resumo' => array(),
                'entradas_mensais' => array(),
                'entradas_por_curso' => array(),
                'entradas_pedidos' => array(),
                'financeiro_options' => array(
                    'anos' => array((int) date('Y')),
                    'meses' => array(),
                    'cursos' => array(),
                    'turmas' => array(),
                    'categorias' => array(),
                ),
            );
        }

        return $this->view('admin/financeiro/index', array_merge(
            array(
                'title' => 'Financeiro',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $payload
        ));
    }

    public function exportarEntradas(Request $request)
    {
        try {
            $resultado = $this->financeiroService->exportarEntradasCsv($request->queryAll());
        } catch (\Throwable $throwable) {
            Logger::error('admin.financeiro.exportar_falha', array(
                'message' => $throwable->getMessage(),
            ));
            Session::flash('errors', array('Não foi possível exportar as entradas financeiras.'));
            return $this->redirect('/admin/financeiro');
        }

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível exportar as entradas.'));
            return $this->redirect('/admin/financeiro');
        }

        return new Response($resultado['content'], 200, array(
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $resultado['filename'] . '"',
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
                'repasses' => $this->comDocumentosEPagamentos(
                    $this->financeiroService->listarRepassesApuracao($apuracaoId > 0 ? $apuracaoId : null)
                ),
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
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível apurar a competência.'));
            return $this->redirect('/admin/financeiro');
        }

        Session::flash('success', 'Apuração gerada com sucesso.');
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
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível gerar os repasses.'));
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

        $action = $this->submitAction($request);
        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível salvar o perfil fiscal.'));
            return $this->redirect('/admin/financeiro');
        }

        Session::flash('success', 'Perfil fiscal salvo com sucesso.');
        return $action === 'save_exit' ? $this->redirect('/admin/financeiro') : $this->redirect('/admin/financeiro');
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
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível registrar o documento.'));
        } else {
            Session::flash('success', 'Documento registrado com sucesso.');
        }

        return $this->redirect('/admin/financeiro/repasses');
    }

    public function validarDocumento(Request $request)
    {
        $result = $this->financeiroService->validarDocumento(
            (int) $request->input('documento_id', 0),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível validar o documento.'));
        } else {
            Session::flash('success', 'Documento validado. O repasse está aprovado para pagamento.');
        }

        return $this->redirect($this->voltarParaRepasses($request));
    }

    public function rejeitarDocumento(Request $request)
    {
        $result = $this->financeiroService->rejeitarDocumento(
            (int) $request->input('documento_id', 0),
            (string) $request->input('motivo', ''),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível rejeitar o documento.'));
        } else {
            Session::flash('success', 'Documento rejeitado. O repasse voltou a aguardar documento.');
        }

        return $this->redirect($this->voltarParaRepasses($request));
    }

    public function arquivoDocumento(Request $request)
    {
        return $this->entregarArquivo(
            $this->financeiroService->arquivoAdmin('documento', (int) $request->query('id', 0), Session::get('usuario_id'), $request->ip(), $request->userAgent()),
            'Documento não encontrado'
        );
    }

    public function comprovantePagamento(Request $request)
    {
        return $this->entregarArquivo(
            $this->financeiroService->arquivoAdmin('comprovante', (int) $request->query('id', 0), Session::get('usuario_id'), $request->ip(), $request->userAgent()),
            'Comprovante não encontrado'
        );
    }

    private function entregarArquivo($arquivo, $tituloNaoEncontrado)
    {
        if (!$arquivo) {
            return new Response(\App\Core\View::render('errors/404', array('title' => $tituloNaoEncontrado)), 404);
        }

        return new Response(file_get_contents($arquivo['caminho']), 200, array(
            'Content-Type' => $arquivo['tipo'],
            'Content-Disposition' => 'inline; filename="' . str_replace('"', '', basename($arquivo['nome'])) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
        ));
    }

    private function comDocumentosEPagamentos(array $repasses)
    {
        foreach ($repasses as &$repasse) {
            $repasse['documentos'] = $this->financeiroService->documentosDoRepasse((int) $repasse['id']);
            $repasse['pagamentos'] = $this->financeiroService->pagamentosDoRepasse((int) $repasse['id']);
            $repasse['bloqueio_pagamento'] = $this->financeiroService->motivoBloqueioPagamento($repasse);
        }
        unset($repasse);

        return $repasses;
    }

    private function voltarParaRepasses(Request $request)
    {
        $apuracaoId = (int) $request->input('apuracao_id', 0);

        return '/admin/financeiro/repasses' . ($apuracaoId > 0 ? '?apuracao_id=' . $apuracaoId : '');
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
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível registrar o pagamento.'));
        } else {
            Session::flash('success', 'Pagamento registrado com sucesso.');
        }

        return $this->redirect('/admin/financeiro/repasses');
    }
}



<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Turma;
use App\Services\InscricaoService;
use App\Services\TurmaInscritosExportService;
use Throwable;

class TurmaInscritosController extends Controller
{
    private $inscricaoService;
    private $exportService;
    private $turmaModel;

    public function __construct()
    {
        $this->inscricaoService = new InscricaoService();
        $this->exportService = new TurmaInscritosExportService();
        $this->turmaModel = new Turma();
    }

    public function index(Request $request)
    {
        $turmaId = (int) $request->query('turma_id', 0);
        $turma = $this->turmaModel->findAdminById($turmaId);

        if (!$turma) {
            Session::flash('errors', array('Turma não encontrada.'));
            return $this->redirect('/admin/turmas');
        }

        $filters = array(
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'sort_by' => trim((string) $request->query('sort_by', 'nome')),
            'sort_dir' => strtolower(trim((string) $request->query('sort_dir', 'asc'))),
            'per_page' => (int) $request->query('per_page', 20),
        );
        $page = (int) $request->query('page', 1);

        return $this->view('admin/turmas/inscritos/index', array_merge(
            array(
                'title' => 'Inscritos da turma',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'turma' => $turma,
                'filters' => $filters,
            ),
            $this->inscricaoService->listarInscritosDaTurma($turmaId, $filters, $page, $filters['per_page'])
        ));
    }

    public function exportarCsv(Request $request)
    {
        return $this->exportar($request, 'csv');
    }

    public function exportarPdf(Request $request)
    {
        return $this->exportar($request, 'pdf');
    }

    /**
     * Exporta o resultado filtrado (sem paginacao) em CSV ou PDF.
     */
    private function exportar(Request $request, $formato)
    {
        $turmaId = (int) $request->query('turma_id', 0);
        $filters = array(
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'sort_by' => trim((string) $request->query('sort_by', 'nome')),
            'sort_dir' => strtolower(trim((string) $request->query('sort_dir', 'asc'))),
        );

        try {
            $resultado = $formato === 'pdf'
                ? $this->exportService->exportarPdf($turmaId, $filters, Session::get('usuario_id'), $request->ip(), $request->userAgent())
                : $this->exportService->exportarCsv($turmaId, $filters, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        } catch (Throwable $throwable) {
            Logger::error('turmas.inscritos.exportar_falha', array(
                'turma_id' => $turmaId,
                'formato' => $formato,
                'message' => $throwable->getMessage(),
            ));

            Session::flash('errors', array('Não foi possível exportar os inscritos.'));
            return $this->redirect($this->voltarParaListagem($turmaId, $filters));
        }

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível exportar os inscritos.'));
            return $this->redirect($this->voltarParaListagem($turmaId, $filters));
        }

        return new Response($resultado['content'], 200, array(
            'Content-Type' => $resultado['content_type'],
            'Content-Disposition' => 'attachment; filename="' . $resultado['filename'] . '"',
        ));
    }

    private function voltarParaListagem($turmaId, array $filters)
    {
        $query = array_filter(array_merge(array('turma_id' => (int) $turmaId), $filters), function ($valor) {
            return $valor !== '' && $valor !== null;
        });

        return '/admin/turmas/inscritos?' . http_build_query($query);
    }
}

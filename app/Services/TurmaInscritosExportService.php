<?php

namespace App\Services;

use App\Core\Helpers;
use App\Core\Logger;
use App\Models\Inscricao;
use App\Models\Turma;

/**
 * Exportacao da lista de inscritos de uma turma, em CSV (planilha) ou PDF (impressao).
 *
 * A exportacao sempre respeita os filtros aplicados na tela (busca, status e ordenacao),
 * mas ignora a paginacao: leva todas as linhas do resultado filtrado, nao so a pagina visivel.
 *
 * Como o arquivo carrega dados pessoais de alunos (nome, e-mail, telefone), toda exportacao
 * e registrada em auditoria.
 */
class TurmaInscritosExportService
{
    /** Teto de linhas por arquivo, para nao estourar memoria com turmas gigantes. */
    const MAX_LINHAS = 5000;

    private $inscricaoModel;
    private $turmaModel;
    private $auditService;

    private static $statusLabels = array(
        'pendente' => 'Pendente',
        'com_pendencia' => 'Com pendência',
        'ativa' => 'Ativa',
        'em_andamento' => 'Em andamento',
        'cancelada' => 'Cancelada',
        'reprovada' => 'Reprovada',
        'concluida' => 'Concluída',
        'concluida_sem_certificado' => 'Concluída sem certificado',
        'certificado_emitido' => 'Certificado emitido',
    );

    public function __construct()
    {
        $this->inscricaoModel = new Inscricao();
        $this->turmaModel = new Turma();
        $this->auditService = new AuditService();
    }

    public function exportarCsv($turmaId, array $filters = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $dados = $this->coletar($turmaId, $filters);
        if (empty($dados['ok'])) {
            return $dados;
        }

        $linhas = array();
        foreach ($dados['inscritos'] as $inscrito) {
            $linhas[] = array_values($this->montarLinha($inscrito));
        }

        $this->registrarAuditoria('csv', $dados['turma'], $filters, count($linhas), $actorUserId, $ipAddress, $userAgent);

        return array(
            'ok' => true,
            'content_type' => 'text/csv; charset=UTF-8',
            'filename' => $this->nomeArquivo($dados['turma'], 'csv'),
            'content' => $this->montarCsv($this->cabecalhos(), $linhas),
            'linhas' => count($linhas),
        );
    }

    public function exportarPdf($turmaId, array $filters = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $dados = $this->coletar($turmaId, $filters);
        if (empty($dados['ok'])) {
            return $dados;
        }

        if (!class_exists('TCPDF', false)) {
            $tcpdfFile = BASE_PATH . '/app/Support/Tcpdf/tcpdf.php';
            if (is_file($tcpdfFile)) {
                require_once $tcpdfFile;
            }
        }

        if (!class_exists('TCPDF', false)) {
            Logger::error('turmas.inscritos.exportar_pdf_falha', array('erro' => 'TCPDF indisponível.'));

            return array('ok' => false, 'message' => 'Não foi possível gerar o PDF agora.');
        }

        $conteudo = $this->montarPdf($dados['turma'], $dados['inscritos'], $filters);

        $this->registrarAuditoria('pdf', $dados['turma'], $filters, count($dados['inscritos']), $actorUserId, $ipAddress, $userAgent);

        return array(
            'ok' => true,
            'content_type' => 'application/pdf',
            'filename' => $this->nomeArquivo($dados['turma'], 'pdf'),
            'content' => $conteudo,
            'linhas' => count($dados['inscritos']),
        );
    }

    /**
     * Busca a turma e todas as linhas do resultado filtrado (sem paginacao).
     */
    private function coletar($turmaId, array $filters)
    {
        $turmaId = (int) $turmaId;
        $turma = $this->turmaModel->findAdminById($turmaId);

        if (!$turma) {
            return array('ok' => false, 'message' => 'Turma não encontrada.');
        }

        $total = $this->inscricaoModel->countInscritosDaTurma($turmaId, $filters);
        if ($total <= 0) {
            return array('ok' => false, 'message' => 'Não há inscritos para exportar com os filtros atuais.');
        }

        $limite = min($total, self::MAX_LINHAS);
        $inscritos = $this->inscricaoModel->listInscritosDaTurma($turmaId, $filters, $limite, 0);

        if ($total > self::MAX_LINHAS) {
            Logger::warning('turmas.inscritos.exportar_truncado', array(
                'turma_id' => $turmaId,
                'total' => $total,
                'exportado' => self::MAX_LINHAS,
            ));
        }

        return array(
            'ok' => true,
            'turma' => $turma,
            'inscritos' => $inscritos,
            'total' => $total,
        );
    }

    private function cabecalhos()
    {
        return array(
            'Aluno',
            'E-mail',
            'Telefone',
            'Status',
            'Progresso (%)',
            'Presença (%)',
            'Nota final',
            'Apto ao certificado',
            'Concluída em',
            'Inscrito em',
        );
    }

    private function montarLinha(array $inscrito)
    {
        return array(
            'aluno' => (string) $inscrito['nome'],
            'email' => (string) $inscrito['email'],
            'telefone' => (string) ($inscrito['telefone'] ?? ''),
            'status' => $this->rotuloStatus($inscrito['status']),
            'progresso' => $this->numero($inscrito['percentual_progresso'], 0),
            'presenca' => $this->numero($inscrito['presenca_percentual'], 0),
            'nota' => $this->numero($inscrito['nota_final'], 1),
            'apto' => !empty($inscrito['apto_certificado']) ? 'Sim' : 'Não',
            'concluida_em' => $this->data($inscrito['concluida_em']),
            'inscrito_em' => $this->data($inscrito['created_at']),
        );
    }

    /**
     * CSV com BOM UTF-8 e separador ponto-e-virgula: e o que o Excel em pt-BR abre
     * com acentuacao correta e colunas ja separadas.
     */
    private function montarCsv(array $cabecalhos, array $linhas)
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $cabecalhos, ';', '"', '\\');

        foreach ($linhas as $linha) {
            fputcsv($handle, $linha, ';', '"', '\\');
        }

        rewind($handle);
        $conteudo = stream_get_contents($handle);
        fclose($handle);

        return $conteudo;
    }

    private function montarPdf(array $turma, array $inscritos, array $filters)
    {
        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Desbloqueia Cursos');
        $pdf->SetTitle('Inscritos - ' . (string) $turma['nome']);
        $pdf->SetMargins(10, 12, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $subtitulo = array();
        if (!empty($turma['codigo'])) {
            $subtitulo[] = 'Código: ' . $turma['codigo'];
        }
        $subtitulo[] = 'Curso: ' . (string) $turma['curso_nome'];
        $subtitulo[] = count($inscritos) . ' inscrito(s)';

        $descricaoFiltros = $this->descreverFiltros($filters);
        if ($descricaoFiltros !== '') {
            $subtitulo[] = 'Filtros: ' . $descricaoFiltros;
        }

        $html = '<h1 style="font-size:14pt;">Inscritos — ' . Helpers::e((string) $turma['nome']) . '</h1>'
            . '<p style="font-size:8pt;color:#555;">' . Helpers::e(implode(' • ', $subtitulo)) . '</p>'
            . '<p style="font-size:7pt;color:#777;">Emitido em ' . date('d/m/Y H:i') . '</p>';

        $html .= '<table border="0.4" cellpadding="3" style="font-size:7.5pt;">'
            . '<thead><tr style="background-color:#eef2f7;font-weight:bold;">'
            . '<th width="19%">Aluno</th>'
            . '<th width="20%">E-mail</th>'
            . '<th width="11%">Telefone</th>'
            . '<th width="14%">Status</th>'
            . '<th width="8%">Progr.</th>'
            . '<th width="8%">Presença</th>'
            . '<th width="7%">Nota</th>'
            . '<th width="6%">Apto</th>'
            . '<th width="7%">Inscrito</th>'
            . '</tr></thead><tbody>';

        foreach ($inscritos as $inscrito) {
            $linha = $this->montarLinha($inscrito);
            $html .= '<tr>'
                . '<td>' . Helpers::e($linha['aluno']) . '</td>'
                . '<td>' . Helpers::e($linha['email']) . '</td>'
                . '<td>' . Helpers::e($linha['telefone']) . '</td>'
                . '<td>' . Helpers::e($linha['status']) . '</td>'
                . '<td>' . Helpers::e($linha['progresso']) . '</td>'
                . '<td>' . Helpers::e($linha['presenca']) . '</td>'
                . '<td>' . Helpers::e($linha['nota']) . '</td>'
                . '<td>' . Helpers::e($linha['apto']) . '</td>'
                . '<td>' . Helpers::e($linha['inscrito_em']) . '</td>'
                . '</tr>';
        }

        $html .= '</tbody></table>';

        $pdf->SetFont('dejavusans', '', 8);
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    private function registrarAuditoria($formato, array $turma, array $filters, $linhas, $actorUserId, $ipAddress, $userAgent)
    {
        $turmaId = (int) $turma['id'];

        $this->auditService->record(
            'turmas.inscritos.exportado',
            'turma',
            $turmaId,
            array(
                'formato' => $formato,
                'linhas' => (int) $linhas,
                'filtros' => array(
                    'q' => isset($filters['q']) ? (string) $filters['q'] : '',
                    'status' => isset($filters['status']) ? (string) $filters['status'] : '',
                ),
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('turmas.inscritos.exportado', array(
            'turma_id' => $turmaId,
            'formato' => $formato,
            'linhas' => (int) $linhas,
            'usuario_id' => $actorUserId,
        ));
    }

    private function descreverFiltros(array $filters)
    {
        $partes = array();

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $partes[] = 'busca "' . $q . '"';
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '') {
            $partes[] = 'status ' . $this->rotuloStatus($status);
        }

        return implode(', ', $partes);
    }

    private function nomeArquivo(array $turma, $extensao)
    {
        $base = 'inscritos-turma-' . (int) $turma['id'];

        $codigo = isset($turma['codigo']) ? trim((string) $turma['codigo']) : '';
        if ($codigo !== '') {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', $codigo);
            $slug = trim((string) $slug, '-');
            if ($slug !== '') {
                $base .= '-' . strtolower($slug);
            }
        }

        return $base . '-' . date('Ymd-His') . '.' . $extensao;
    }

    private function rotuloStatus($status)
    {
        $status = (string) $status;

        return isset(self::$statusLabels[$status]) ? self::$statusLabels[$status] : $status;
    }

    private function numero($valor, $decimais)
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return number_format((float) $valor, $decimais, ',', '');
    }

    private function data($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '' || strpos($valor, '0000-00-00') === 0) {
            return '';
        }

        $timestamp = strtotime($valor);

        return $timestamp ? date('d/m/Y', $timestamp) : '';
    }
}

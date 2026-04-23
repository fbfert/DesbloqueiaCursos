<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Helpers;
use App\Core\Logger;
use App\Core\Validator;
use App\Models\Certificado;
use App\Models\CertificadoAssinante;
use App\Models\CertificadoTemplate;
use App\Models\CursoPessoaVinculada;
use App\Models\Inscricao;
use App\Models\Pedido;
use App\Models\ParticipantePedido;
use App\Models\Turma;
use App\Services\RbacService;
use Exception;

class CertificadoService
{
    private $certificadoModel;
    private $templateModel;
    private $assinanteModel;
    private $cursoPessoaModel;
    private $inscricaoModel;
    private $pedidoModel;
    private $participanteModel;
    private $turmaModel;
    private $emailService;
    private $auditService;
    private $trashService;
    private $inscricaoService;
    private $templateService;
    private $globalConfigService;
    private $rbacService;

    public function __construct()
    {
        $this->certificadoModel = new Certificado();
        $this->templateModel = new CertificadoTemplate();
        $this->assinanteModel = new CertificadoAssinante();
        $this->cursoPessoaModel = new CursoPessoaVinculada();
        $this->inscricaoModel = new Inscricao();
        $this->pedidoModel = new Pedido();
        $this->participanteModel = new ParticipantePedido();
        $this->turmaModel = new Turma();
        $this->emailService = new EmailService();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
        $this->inscricaoService = new InscricaoService();
        $this->templateService = new CertificadoTemplateService();
        $this->globalConfigService = new ConfiguracaoGlobalService();
        $this->rbacService = new RbacService();
    }

    public function listarAptos()
    {
        return $this->certificadoModel->listEligible();
    }

    public function listarCertificados()
    {
        return $this->certificadoModel->listAdmin();
    }

    public function listarTemplates()
    {
        return $this->templateService->listTemplates();
    }

    public function detalhar($certificadoId)
    {
        $certificado = $this->certificadoModel->findById($certificadoId);

        if (!$certificado) {
            return array('certificado' => null);
        }

        $certificado['historico'] = $this->certificadoModel->historyForCertificado($certificadoId);
        $certificado['validacoes'] = $this->certificadoModel->validationLogsForCertificado($certificadoId);
        $certificado['assinantes'] = $this->assinantesDoCurso((int) $certificado['curso_evento_id'], isset($certificado['template_id']) ? (int) $certificado['template_id'] : null);

        return array('certificado' => $certificado);
    }

    public function emitir($inscricaoId, array $opcoes = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $inscricao = $this->inscricaoModel->findById($inscricaoId);
        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscricao nao encontrada.');
        }

        if ((int) $inscricao['apto_certificado'] !== 1 && !in_array($inscricao['status'], array('concluida', 'concluida_sem_certificado', 'certificado_emitido'), true)) {
            return array('ok' => false, 'message' => 'A inscricao ainda nao esta apta para certificado.');
        }

        $template = $this->resolveTemplate(isset($opcoes['template_id']) ? (int) $opcoes['template_id'] : null);
        $existente = $this->certificadoModel->findByInscricao($inscricaoId);
        $manterCodigo = !empty($opcoes['manter_codigo']);

        $pedido = $this->pedidoModel->findById($inscricao['pedido_id']);
        $participante = $this->findParticipante((int) $inscricao['participante_pedido_id']);
        $curso = $this->findCurso((int) $inscricao['curso_evento_id']);
        $turma = !empty($inscricao['turma_id']) ? $this->turmaModel->findPublicById((int) $inscricao['turma_id']) : null;

        $codigo = $existente && $manterCodigo ? $existente['codigo'] : $this->novoCodigo();
        $versao = $existente && $manterCodigo ? ((int) $existente['versao'] + 1) : 1;
        $cpfDigits = Validator::onlyDigits(isset($participante['cpf']) ? $participante['cpf'] : '');
        $cpfMasked = $this->mascararCpf($cpfDigits);
        $titulo = isset($template['nome']) ? $template['nome'] : 'Certificado de Conclusao';
        $assinantes = $this->assinantesDoCurso((int) $inscricao['curso_evento_id'], isset($template['id']) ? (int) $template['id'] : null);

        $payload = array(
            'template_id' => isset($template['id']) ? (int) $template['id'] : null,
            'curso_evento_id' => (int) $inscricao['curso_evento_id'],
            'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
            'pedido_id' => (int) $inscricao['pedido_id'],
            'inscricao_id' => (int) $inscricao['id'],
            'participante_pedido_id' => (int) $inscricao['participante_pedido_id'],
            'usuario_id' => !empty($inscricao['usuario_id']) ? (int) $inscricao['usuario_id'] : null,
            'codigo' => $codigo,
            'versao' => $versao,
            'nome_participante' => isset($participante['nome']) ? $participante['nome'] : 'Participante',
            'cpf_participante' => $cpfDigits,
            'cpf_mascarado' => $cpfMasked,
            'titulo' => $titulo,
            'status' => 'emitido',
            'emitido_por_usuario_id' => $actorUserId,
            'emitido_em' => date('Y-m-d H:i:s'),
            'reemitido_de_certificado_id' => $existente && !$manterCodigo ? (int) $existente['id'] : null,
        );

        $pdfBytes = $this->gerarPdf($payload, $template, $assinantes, $curso, $turma, $pedido);
        $pdfRelativePath = $this->salvarPdf($codigo, $pdfBytes);
        $payload['pdf_caminho'] = $pdfRelativePath;
        $payload['pdf_nome_original'] = 'certificado-' . $codigo . '.pdf';

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($existente && $manterCodigo) {
                $this->certificadoModel->update(array_merge($payload, array(
                    'substituido_por_certificado_id' => isset($existente['substituido_por_certificado_id']) ? $existente['substituido_por_certificado_id'] : null,
                )), $existente['id']);
                $certificadoId = (int) $existente['id'];
            } else {
                if ($existente) {
                    $this->certificadoModel->updateStatus($existente['id'], 'substituido', array(
                        'substituido_por_certificado_id' => null,
                    ));
                    $this->certificadoModel->createHistory($existente['id'], $existente['status'], 'substituido', 'Certificado substituido em nova emissao', $actorUserId);
                }

                $certificadoId = $this->certificadoModel->create($payload);
                if ($existente) {
                    $this->certificadoModel->updateStatus($existente['id'], 'substituido', array(
                        'substituido_por_certificado_id' => $certificadoId,
                    ));
                }
            }

            $this->certificadoModel->createHistory($certificadoId, $existente ? $existente['status'] : null, 'emitido', 'Emissao manual do certificado', $actorUserId);

            $this->auditService->record(
                'certificado.emitido',
                'certificado',
                $certificadoId,
                array(
                    'inscricao_id' => (int) $inscricao['id'],
                    'codigo' => $codigo,
                    'versao' => $versao,
                    'template_id' => isset($template['id']) ? (int) $template['id'] : null,
                    'manter_codigo' => $manterCodigo,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('certificado.emitido', array(
                'certificado_id' => $certificadoId,
                'codigo' => $codigo,
                'inscricao_id' => (int) $inscricao['id'],
            ));

            $pdo->commit();

            $certificado = $this->certificadoModel->findById($certificadoId);
            $this->inscricaoService->alterarStatus(
                (int) $inscricao['id'],
                'certificado_emitido',
                'Certificado emitido manualmente',
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            return array('ok' => true, 'certificado_id' => $certificadoId, 'codigo' => $codigo);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('certificado.emitir_falhou', array(
                'inscricao_id' => $inscricaoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function reemitir($certificadoId, $manterCodigo = true, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $certificado = $this->certificadoModel->findById($certificadoId);

        if (!$certificado) {
            return array('ok' => false, 'message' => 'Certificado nao encontrado.');
        }

        return $this->emitir(
            (int) $certificado['inscricao_id'],
            array(
                'template_id' => $certificado['template_id'],
                'manter_codigo' => (bool) $manterCodigo,
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );
    }

    public function cancelar($certificadoId, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->alterarStatusInterno($certificadoId, 'cancelado', $observacao, $actorUserId, $ipAddress, $userAgent, array(
            'cancelado_por_usuario_id' => $actorUserId,
            'cancelado_em' => date('Y-m-d H:i:s'),
        ));
    }

    public function revogar($certificadoId, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->alterarStatusInterno($certificadoId, 'revogado', $observacao, $actorUserId, $ipAddress, $userAgent, array(
            'revogado_por_usuario_id' => $actorUserId,
            'revogado_em' => date('Y-m-d H:i:s'),
        ));
    }

    public function validarPublicamente($codigo, $cpfInformado = null, $ipAddress = null, $userAgent = null)
    {
        $codigo = strtoupper(trim((string) $codigo));
        $certificado = $this->certificadoModel->findByCodigo($codigo);

        if (!$certificado) {
            $this->certificadoModel->logValidation(null, $codigo, Validator::onlyDigits($cpfInformado), 'nao_encontrado', $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Certificado nao encontrado.');
        }

        if ($certificado['status'] !== 'emitido') {
            $this->certificadoModel->logValidation((int) $certificado['id'], $codigo, Validator::onlyDigits($cpfInformado), $certificado['status'], $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Certificado nao esta ativo para validacao.');
        }

        $cpfDigits = Validator::onlyDigits($cpfInformado);
        if ($cpfDigits !== '' && $cpfDigits !== $certificado['cpf_participante']) {
            $this->certificadoModel->logValidation((int) $certificado['id'], $codigo, $cpfDigits, 'cpf_invalido', $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'CPF nao confere com o certificado.');
        }

        $this->certificadoModel->logValidation((int) $certificado['id'], $codigo, $cpfDigits !== '' ? $cpfDigits : null, 'valido', $ipAddress, $userAgent);

        $certificado['cpf_mascarado'] = $this->mascararCpf($certificado['cpf_participante']);
        $certificado['pdf_url'] = Helpers::url('certificados/pdf?codigo=' . urlencode($certificado['codigo']));
        $certificado['validacao_url'] = Helpers::url('certificados/validar?codigo=' . urlencode($certificado['codigo']));
        $certificado['codigo'] = $codigo;
        $certificado['qr_svg'] = $this->qrSvgDataUri($codigo);
        $certificado['assinantes'] = $this->assinantesDoCurso((int) $certificado['curso_evento_id'], isset($certificado['template_id']) ? (int) $certificado['template_id'] : null);
        $certificado['historico'] = $this->certificadoModel->historyForCertificado((int) $certificado['id']);

        return array('ok' => true, 'certificado' => $certificado);
    }

    public function pdfBytesByCodigo($codigo)
    {
        $certificado = $this->certificadoModel->findByCodigo($codigo);
        if (!$certificado) {
            return null;
        }

        return $this->pdfContentForCertificate($certificado);
    }

    public function usuarioPodeAcessarCertificado(array $certificado, $usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        if ($this->rbacService->userHasPermission($usuarioId, 'certificados.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'certificados.gerenciar')) {
            return true;
        }

        if (!empty($certificado['usuario_id']) && (int) $certificado['usuario_id'] === (int) $usuarioId) {
            return true;
        }

        if (!empty($certificado['pedido_id'])) {
            $pedido = $this->pedidoModel->findById((int) $certificado['pedido_id']);
            if ($pedido && ((int) $pedido['comprador_usuario_id'] === (int) $usuarioId || (int) $pedido['pagador_usuario_id'] === (int) $usuarioId)) {
                return true;
            }
        }

        if (!empty($certificado['inscricao_id'])) {
            $inscricao = $this->inscricaoModel->findById((int) $certificado['inscricao_id']);
            if ($inscricao && !empty($inscricao['usuario_id']) && (int) $inscricao['usuario_id'] === (int) $usuarioId) {
                return true;
            }
        }

        return false;
    }

    public function certificadoParaEmail(?array $certificado = null)
    {
        if (!$certificado) {
            return array();
        }

        $certificado['cpf_mascarado'] = $this->mascararCpf(isset($certificado['cpf_participante']) ? $certificado['cpf_participante'] : '');
        $certificado['pdf_url'] = Helpers::url('certificados/pdf?codigo=' . urlencode($certificado['codigo']));
        $certificado['validacao_url'] = Helpers::url('certificados/validar?codigo=' . urlencode($certificado['codigo']));
        return $certificado;
    }

    public function assinantesDoCurso($cursoId, $templateId = null)
    {
        $assinantes = $this->templateService->assinantesDoCurso($cursoId, $templateId);

        if (!empty($assinantes)) {
            return $assinantes;
        }

        return $this->cursoPessoaModel->forCourse($cursoId);
    }

    private function alterarStatusInterno($certificadoId, $status, $observacao, $actorUserId, $ipAddress, $userAgent, array $fields = array())
    {
        $certificado = $this->certificadoModel->findById($certificadoId);

        if (!$certificado) {
            return array('ok' => false, 'message' => 'Certificado nao encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->certificadoModel->updateStatus($certificadoId, $status, $fields);
            $this->certificadoModel->createHistory($certificadoId, $certificado['status'], $status, $observacao, $actorUserId);

            $this->auditService->record(
                'certificado.status.atualizado',
                'certificado',
                $certificadoId,
                array(
                    'status_anterior' => $certificado['status'],
                    'status_novo' => $status,
                    'observacao' => $observacao,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('certificado.status.atualizado', array(
                'certificado_id' => $certificadoId,
                'status_novo' => $status,
            ));

            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('certificado.status.falhou', array(
                'certificado_id' => $certificadoId,
                'message' => $exception->getMessage(),
            ));
            throw $exception;
        }
    }

    private function resolveTemplate($templateId = null)
    {
        if ($templateId) {
            $template = $this->templateModel->findById($templateId);
            if ($template) {
                return $template;
            }
        }

        return $this->templateModel->defaultTemplate();
    }

    private function findParticipante($participantePedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM participantes_pedido
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => $participantePedidoId));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: array();
    }

    private function findCurso($cursoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM cursos_eventos
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => $cursoId));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: array();
    }

    private function novoCodigo()
    {
        $prefixo = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $this->globalConfigService->certificatePrefix()));
        if ($prefixo === '') {
            $prefixo = 'PRC';
        }

        $prefixo = substr($prefixo, 0, 7);

        return $prefixo . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
    }

    private function mascararCpf($cpf)
    {
        $cpf = Validator::onlyDigits($cpf);
        if (strlen($cpf) !== 11) {
            return $cpf;
        }

        return substr($cpf, 0, 3) . '.***.***-' . substr($cpf, -2);
    }

    private function salvarPdf($codigo, $bytes)
    {
        $relative = 'certificados/' . date('Y/m') . '/' . $codigo . '.pdf';
        $absolute = BASE_PATH . '/storage/private_uploads/' . $relative;
        $directory = dirname($absolute);

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        file_put_contents($absolute, $bytes);

        return $relative;
    }

    private function gerarPdf(array $certificado, array $template, array $assinantes, array $curso, array $turma, array $pedido)
    {
        $qrMatrix = $this->buildQrMatrix($certificado['codigo']);
        $lines = array(
            'CERTIFICADO DE CONCLUSAO',
            'Codigo: ' . $certificado['codigo'],
            'Participante: ' . $certificado['nome_participante'],
            'CPF: ' . $certificado['cpf_participante'],
            'Curso: ' . (isset($curso['nome']) ? $curso['nome'] : ''),
            'Turma: ' . (isset($turma['nome']) ? $turma['nome'] : 'N/A'),
            'Pedido: ' . (isset($pedido['codigo']) ? $pedido['codigo'] : ''),
            'Validacao publica: ' . Helpers::url('certificados/validar?codigo=' . urlencode($certificado['codigo'])),
        );

        $assinaturas = array();
        foreach ($assinantes as $assinante) {
            $assinaturas[] = isset($assinante['nome']) ? $assinante['nome'] . ' - ' . $assinante['cargo'] : '';
        }

        return $this->buildSimplePdf($lines, $assinaturas, $qrMatrix);
    }

    private function pdfContentForCertificate(array $certificado)
    {
        $template = $this->resolveTemplate(isset($certificado['template_id']) ? (int) $certificado['template_id'] : null);
        $assinantes = $this->assinantesDoCurso((int) $certificado['curso_evento_id'], isset($template['id']) ? (int) $template['id'] : null);
        $curso = $this->findCurso((int) $certificado['curso_evento_id']);
        $turma = !empty($certificado['turma_id']) ? $this->turmaModel->findPublicById((int) $certificado['turma_id']) : array();
        $pedido = !empty($certificado['pedido_id']) ? $this->pedidoModel->findById((int) $certificado['pedido_id']) : array();

        return $this->gerarPdf($certificado, $template, $assinantes, $curso, $turma, $pedido);
    }

    private function buildSimplePdf(array $lines, array $assinaturas, array $qrMatrix)
    {
        $width = 842;
        $height = 595;
        $content = array();
        $content[] = 'q';
        $content[] = '0.95 0.97 1 rg';
        $content[] = '0 0 ' . $width . ' ' . $height . ' re f';
        $content[] = '0 0 0 rg';
        $content[] = 'BT';
        $content[] = '/F1 22 Tf';
        $content[] = '50 ' . ($height - 60) . ' Td';
        $content[] = '(' . $this->escapePdfText($lines[0]) . ') Tj';

        $content[] = '0 -34 Td';
        $content[] = '/F1 12 Tf';
        for ($i = 1; $i < count($lines); $i++) {
            $content[] = '(' . $this->escapePdfText($lines[$i]) . ') Tj';
            if ($i < count($lines) - 1) {
                $content[] = 'T*';
            }
        }
        $content[] = 'ET';

        $qrX = 560;
        $qrY = 170;
        $moduleSize = 6;
        $quiet = 4;
        $size = count($qrMatrix);

        $content[] = '0 0 0 rg';
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if (empty($qrMatrix[$row][$col])) {
                    continue;
                }

                $x = $qrX + (($col + $quiet) * $moduleSize);
                $y = $qrY + ((($size - 1 - $row) + $quiet) * $moduleSize);
                $content[] = $x . ' ' . $y . ' ' . $moduleSize . ' ' . $moduleSize . ' re f';
            }
        }

        $content[] = '0 0 0 rg';
        $sigY = 120;
        $content[] = 'BT';
        $content[] = '/F1 10 Tf';
        $content[] = '50 ' . $sigY . ' Td';
        if (empty($assinaturas)) {
            $content[] = '(Assinaturas cadastradas pelo administrador) Tj';
        } else {
            foreach ($assinaturas as $assinatura) {
                $content[] = '(' . $this->escapePdfText($assinatura) . ') Tj';
                $content[] = 'T*';
            }
        }
        $content[] = 'ET';
        $content[] = 'Q';

        $stream = implode("\n", $content);
        $objects = array();
        $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
        $objects[] = '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj';
        $objects[] = '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $width . ' ' . $height . '] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >> endobj';
        $objects[] = '4 0 obj << /Length ' . strlen($stream) . ' >> stream' . "\n" . $stream . "\nendstream endobj";
        $objects[] = '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';

        $pdf = "%PDF-1.4\n";
        $offsets = array(0);
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= 'xref' . "\n";
        $pdf .= '0 ' . (count($offsets)) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        }
        $pdf .= 'trailer << /Size ' . count($offsets) . ' /Root 1 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n" . $xrefPos . "\n%%EOF";

        return $pdf;
    }

    private function escapePdfText($text)
    {
        $text = (string) $text;
        $text = str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $text);
        return $text;
    }

    private function buildQrMatrix($text)
    {
        $data = $this->qrDataBytes($text);
        $ecc = $this->qrEccBytes($data, 7);
        $codewords = array_merge($data, $ecc);
        $bits = array();
        foreach ($codewords as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = (($byte >> $i) & 1) === 1;
            }
        }

        $size = 21;
        $matrix = array_fill(0, $size, array_fill(0, $size, null));

        $this->qrDrawFinder($matrix, 0, 0);
        $this->qrDrawFinder($matrix, $size - 7, 0);
        $this->qrDrawFinder($matrix, 0, $size - 7);
        for ($i = 0; $i < $size; $i++) {
            if ($matrix[6][$i] === null) {
                $matrix[6][$i] = ($i % 2) === 0;
            }
            if ($matrix[$i][6] === null) {
                $matrix[$i][6] = ($i % 2) === 0;
            }
        }

        $matrix[8][13] = true;

        $this->qrReserveFormatInfo($matrix);
        $this->qrPlaceData($matrix, $bits);
        $this->qrPlaceFormatBits($matrix, '111011111000100');

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix[$y][$x] === null) {
                    $matrix[$y][$x] = false;
                }
            }
        }

        return $matrix;
    }

    private function qrDataBytes($text)
    {
        $text = (string) $text;
        if (strlen($text) > 17) {
            throw new Exception('Codigo do certificado muito longo para QR de versao simples.');
        }

        $bits = array();
        $this->qrAppendBits($bits, 0b0100, 4);
        $this->qrAppendBits($bits, strlen($text), 8);
        foreach (str_split($text) as $char) {
            $this->qrAppendBits($bits, ord($char), 8);
        }

        $dataBytes = $this->qrBitsToBytes($bits);
        while (count($dataBytes) < 19) {
            $dataBytes[] = (count($dataBytes) % 2 === 0) ? 0xEC : 0x11;
        }

        return array_slice($dataBytes, 0, 19);
    }

    private function qrEccBytes(array $data, $ecLength)
    {
        $generator = array(1);
        for ($i = 0; $i < $ecLength; $i++) {
            $generator = $this->qrPolyMultiply($generator, array(1, $this->qrGfPow(2, $i)));
        }

        $message = array_merge($data, array_fill(0, $ecLength, 0));
        $limit = count($data);
        for ($i = 0; $i < $limit; $i++) {
            $coef = $message[$i];
            if ($coef === 0) {
                continue;
            }

            for ($j = 0; $j < count($generator); $j++) {
                $message[$i + $j] ^= $this->qrGfMul($generator[$j], $coef);
            }
        }

        return array_slice($message, -$ecLength);
    }

    private function qrPolyMultiply(array $a, array $b)
    {
        $result = array_fill(0, count($a) + count($b) - 1, 0);

        for ($i = 0; $i < count($a); $i++) {
            for ($j = 0; $j < count($b); $j++) {
                $result[$i + $j] ^= $this->qrGfMul($a[$i], $b[$j]);
            }
        }

        return $result;
    }

    private function qrGfMul($x, $y)
    {
        static $exp = null;
        static $log = null;

        if ($exp === null || $log === null) {
            $exp = array_fill(0, 512, 0);
            $log = array_fill(0, 256, 0);
            $value = 1;
            for ($i = 0; $i < 255; $i++) {
                $exp[$i] = $value;
                $log[$value] = $i;
                $value <<= 1;
                if ($value & 0x100) {
                    $value ^= 0x11D;
                }
            }
            for ($i = 255; $i < 512; $i++) {
                $exp[$i] = $exp[$i - 255];
            }
        }

        if ($x === 0 || $y === 0) {
            return 0;
        }

        return $exp[$log[$x] + $log[$y]];
    }

    private function qrGfPow($x, $power)
    {
        $result = 1;
        for ($i = 0; $i < $power; $i++) {
            $result = $this->qrGfMul($result, $x);
        }
        return $result;
    }

    private function qrAppendBits(array &$bits, $value, $length)
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = (($value >> $i) & 1) === 1;
        }
    }

    private function qrBitsToBytes(array $bits)
    {
        $bytes = array();
        $value = 0;
        $count = 0;
        foreach ($bits as $bit) {
            $value = ($value << 1) | ($bit ? 1 : 0);
            $count++;
            if ($count === 8) {
                $bytes[] = $value;
                $value = 0;
                $count = 0;
            }
        }
        if ($count > 0) {
            $bytes[] = $value << (8 - $count);
        }

        return $bytes;
    }

    private function qrDrawFinder(array &$matrix, $x, $y)
    {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx < 0 || $yy < 0 || $xx >= 21 || $yy >= 21) {
                    continue;
                }

                $border = $dx === -1 || $dx === 7 || $dy === -1 || $dy === 7;
                if ($border) {
                    $matrix[$yy][$xx] = false;
                    continue;
                }

                $inner = ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6);
                $middle = ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4);
                $matrix[$yy][$xx] = $inner || $middle;
            }
        }
    }

    private function qrReserveFormatInfo(array &$matrix)
    {
        $reserved = array(
            array(8, 0), array(8, 1), array(8, 2), array(8, 3), array(8, 4), array(8, 5),
            array(8, 7), array(8, 8), array(7, 8), array(5, 8), array(4, 8), array(3, 8), array(2, 8), array(1, 8), array(0, 8),
            array(20, 8), array(19, 8), array(18, 8), array(17, 8), array(16, 8), array(15, 8), array(14, 8), array(13, 8),
            array(8, 20), array(8, 19), array(8, 18), array(8, 17), array(8, 16), array(8, 15), array(8, 14),
        );

        foreach ($reserved as $cell) {
            $matrix[$cell[1]][$cell[0]] = false;
        }
    }

    private function qrPlaceData(array &$matrix, array $bits)
    {
        $size = 21;
        $bitIndex = 0;
        $directionUp = true;

        for ($x = $size - 1; $x > 0; $x -= 2) {
            if ($x === 6) {
                $x--;
            }

            for ($yOffset = 0; $yOffset < $size; $yOffset++) {
                $y = $directionUp ? ($size - 1 - $yOffset) : $yOffset;

                for ($dx = 0; $dx < 2; $dx++) {
                    $xx = $x - $dx;
                    if ($matrix[$y][$xx] !== null) {
                        continue;
                    }

                    $bit = isset($bits[$bitIndex]) ? $bits[$bitIndex] : false;
                    $mask = (($xx + $y) % 2) === 0;
                    $matrix[$y][$xx] = $bit ^ $mask;
                    $bitIndex++;
                }
            }

            $directionUp = !$directionUp;
        }
    }

    private function qrPlaceFormatBits(array &$matrix, $bits)
    {
        $bitsArray = str_split($bits);
        $positionsA = array(
            array(8, 0), array(8, 1), array(8, 2), array(8, 3), array(8, 4), array(8, 5),
            array(8, 7), array(8, 8), array(7, 8), array(5, 8), array(4, 8), array(3, 8), array(2, 8), array(1, 8), array(0, 8)
        );
        $positionsB = array(
            array(20, 8), array(19, 8), array(18, 8), array(17, 8), array(16, 8), array(15, 8), array(14, 8), array(13, 8),
            array(8, 20), array(8, 19), array(8, 18), array(8, 17), array(8, 16), array(8, 15), array(8, 14)
        );

        foreach ($positionsA as $index => $cell) {
            $matrix[$cell[1]][$cell[0]] = $bitsArray[$index] === '1';
        }

        foreach ($positionsB as $index => $cell) {
            $matrix[$cell[1]][$cell[0]] = $bitsArray[$index] === '1';
        }
    }

    private function qrSvgDataUri($text)
    {
        $matrix = $this->buildQrMatrix($text);
        $size = count($matrix);
        $quiet = 4;
        $moduleSize = 4;
        $svgSize = ($size + ($quiet * 2)) * $moduleSize;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $svgSize . ' ' . $svgSize . '" shape-rendering="crispEdges">';
        $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (empty($matrix[$y][$x])) {
                    continue;
                }
                $rectX = ($x + $quiet) * $moduleSize;
                $rectY = ($y + $quiet) * $moduleSize;
                $svg .= '<rect x="' . $rectX . '" y="' . $rectY . '" width="' . $moduleSize . '" height="' . $moduleSize . '" fill="#111827"/>';
            }
        }
        $svg .= '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function envelopeCertificadoParaEmail(?array $certificado = null)
    {
        if (!$certificado) {
            return array();
        }

        $certificado['cpf_mascarado'] = $this->mascararCpf(isset($certificado['cpf_participante']) ? $certificado['cpf_participante'] : '');
        $certificado['pdf_url'] = Helpers::url('certificados/pdf?codigo=' . urlencode($certificado['codigo']));
        $certificado['validacao_url'] = Helpers::url('certificados/validar?codigo=' . urlencode($certificado['codigo']));
        $certificado['qr_svg'] = $this->qrSvgDataUri($certificado['codigo']);
        return $certificado;
    }
}

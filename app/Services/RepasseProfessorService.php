<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Helpers;
use App\Core\Logger;
use App\Models\ApuracaoMensal;
use App\Models\ConfiguraçãoGlobal;
use App\Models\CursoRateioParticipante;
use App\Models\PagamentoProfessor;
use App\Models\ProfessorFiscal;
use App\Models\RepasseDocumento;
use App\Models\RepasseProfessor;
use App\Models\RpaEspelho;
use App\Models\CursoRateio;
use Exception;

class RepasseProfessorService
{
    private $apuracaoModel;
    private $rateioModel;
    private $rateioParticipanteModel;
    private $professorFiscalModel;
    private $repasseModel;
    private $documentoModel;
    private $pagamentoModel;
    private $rpaModel;
    private $fileStorageService;
    private $auditService;
    private $configuracaoGlobalService;

    public function __construct()
    {
        $this->apuracaoModel = new ApuracaoMensal();
        $this->rateioModel = new CursoRateio();
        $this->rateioParticipanteModel = new CursoRateioParticipante();
        $this->professorFiscalModel = new ProfessorFiscal();
        $this->repasseModel = new RepasseProfessor();
        $this->documentoModel = new RepasseDocumento();
        $this->pagamentoModel = new PagamentoProfessor();
        $this->rpaModel = new RpaEspelho();
        $this->fileStorageService = new FileStorageService();
        $this->auditService = new AuditService();
        $this->configuracaoGlobalService = new ConfiguraçãoGlobalService();
    }

    public function gerarRepassesDaApuracao($apuracaoId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $apuracao = $this->apuracaoModel->findById($apuracaoId);
        if (!$apuracao) {
            return array('ok' => false, 'message' => 'Apuracao nao encontrada.');
        }

        $rateios = $this->rateioModel->findByApuracao($apuracaoId);
        if (empty($rateios)) {
            return array('ok' => false, 'message' => 'Apuracao sem rateios.');
        }

        $participantes = array();
        foreach ($rateios as $rateio) {
            $itens = $this->rateioParticipanteModel->findByRateio($rateio['id']);
            foreach ($itens as $item) {
                $usuarioId = (int) $item['usuario_id'];
                if (!isset($participantes[$usuarioId])) {
                    $participantes[$usuarioId] = array(
                        'usuario_id' => $usuarioId,
                        'tipo_fiscal' => $item['tipo_fiscal'],
                        'percentual' => 0.00,
                        'base_liquida' => 0.00,
                        'valor_bruto' => 0.00,
                        'retencao_percentual' => 0.00,
                        'valor_retenido' => 0.00,
                        'valor_liquido' => 0.00,
                    );
                }

                $participantes[$usuarioId]['percentual'] += (float) $item['percentual'];
                $participantes[$usuarioId]['base_liquida'] += (float) $item['valor_base'];
                $participantes[$usuarioId]['valor_bruto'] += (float) $item['valor_rateado'];
                $participantes[$usuarioId]['retencao_percentual'] = max($participantes[$usuarioId]['retencao_percentual'], (float) $item['retencao_percentual']);
                $participantes[$usuarioId]['valor_retenido'] += (float) $item['valor_retenido'];
                $participantes[$usuarioId]['valor_liquido'] += (float) $item['valor_liquido'];
            }
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $repasses = array();
            foreach ($participantes as $participante) {
                $perfilFiscal = $this->professorFiscalModel->findByUsuarioId($participante['usuario_id']);
                $tipoFiscal = $perfilFiscal ? $perfilFiscal['tipo_pessoa'] : $participante['tipo_fiscal'];
                $documentoObrigatorio = $tipoFiscal === 'pj' ? 1 : 0;
                $status = $documentoObrigatorio ? 'aguardando_documento' : 'pendente';

                $repasseId = $this->repasseModel->create(array(
                    'apuracao_id' => $apuracaoId,
                    'usuario_id' => $participante['usuario_id'],
                    'tipo_fiscal' => $tipoFiscal,
                    'percentual' => round($participante['percentual'], 2),
                    'base_liquida' => round($participante['base_liquida'], 2),
                    'valor_bruto' => round($participante['valor_bruto'], 2),
                    'retencao_percentual' => $perfilFiscal ? (float) $perfilFiscal['aliquota_retencao'] : (float) $participante['retencao_percentual'],
                    'valor_retenido' => round($participante['valor_retenido'], 2),
                    'valor_liquido' => round($participante['valor_liquido'], 2),
                    'documento_obrigatorio' => $documentoObrigatorio,
                    'status' => $status,
                    'competencia' => $apuracao['competencia'],
                ));

                $repasses[] = $repasseId;

                if ($tipoFiscal === 'pf') {
                    $this->gerarEspelhoRpa($repasseId, $apuracao, $participante, $actorUserId, $ipAddress, $userAgent);
                }

                $this->auditService->record(
                    'financeiro.repasse.gerado',
                    'repasse_professor',
                    $repasseId,
                    $participante,
                    $actorUserId,
                    $ipAddress,
                    $userAgent
                );
            }

            $this->auditService->record(
                'financeiro.repasses.gerados',
                'apuracao_mensal',
                $apuracaoId,
                array('repasses' => $repasses),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('financeiro.repasses.gerados', array(
                'apuracao_id' => $apuracaoId,
                'total' => count($repasses),
            ));

            $pdo->commit();

            return array('ok' => true, 'repasses_ids' => $repasses);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('financeiro.repasses.falhou', array(
                'apuracao_id' => $apuracaoId,
                'message' => $exception->getMêssage(),
            ));

            throw $exception;
        }
    }

    public function anexarDocumento($repasseId, array $file, $tipoDocumento = 'outro', $numeroDocumento = null, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $repasse = $this->repasseModel->findById($repasseId);
        if (!$repasse) {
            return array('ok' => false, 'message' => 'Repasse nao encontrado.');
        }

        $stored = $this->fileStorageService->storeUploadedFile($file, 'financeiro/documentos', 'documento', array(
            'max_size_bytes' => 20 * 1024 * 1024,
            'allowed_extensions' => array('pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'),
            'allowed_mime_types' => array(
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
        ));

        $documentoId = $this->documentoModel->create(array(
            'repasse_professor_id' => $repasseId,
            'tipo_documento' => $tipoDocumento,
            'numero_documento' => $numeroDocumento,
            'arquivo_caminho' => $stored['relative_path'],
            'arquivo_nome_original' => $stored['original_name'],
            'arquivo_tipo' => $stored['mime_type'],
            'status' => 'recebido',
            'observacao' => $observacao,
            'enviado_por_usuario_id' => $actorUserId,
        ));

        $statusNovo = $repasse['tipo_fiscal'] === 'pj' ? 'documento_recebido' : 'aprovado';
        $this->repasseModel->updateStatus($repasseId, $statusNovo, $statusNovo === 'documento_recebido' ? date('Y-m-d H:i:s') : null);

        $this->auditService->record(
            'financeiro.documento.anexado',
            'repasse_documento',
            $documentoId,
            array(
                'repasse_professor_id' => $repasseId,
                'tipo_documento' => $tipoDocumento,
                'arquivo_caminho' => $stored['relative_path'],
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('financeiro.documento.anexado', array(
            'repasse_id' => $repasseId,
            'documento_id' => $documentoId,
        ));

        return array('ok' => true, 'documento_id' => $documentoId);
    }

    public function registrarPagamento($repasseId, array $dados, ?array $arquivo = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $repasse = $this->repasseModel->findById($repasseId);
        if (!$repasse) {
            return array('ok' => false, 'message' => 'Repasse nao encontrado.');
        }

        $stored = null;
        if (!empty($arquivo) && !empty($arquivo['tmp_name'])) {
            $stored = $this->fileStorageService->storeUploadedFile($arquivo, 'financeiro/pagamentos', 'comprovante', array(
                'max_size_bytes' => 20 * 1024 * 1024,
                'allowed_extensions' => array('pdf', 'jpg', 'jpeg', 'png', 'webp'),
                'allowed_mime_types' => array('application/pdf', 'image/jpeg', 'image/png', 'image/webp'),
            ));
        }

        $pagamentoId = $this->pagamentoModel->create(array(
            'repasse_professor_id' => $repasseId,
            'data_pagamento' => !empty($dados['data_pagamento']) ? $dados['data_pagamento'] : date('Y-m-d'),
            'valor' => isset($dados['valor']) ? (float) $dados['valor'] : (float) $repasse['valor_liquido'],
            'metodo' => isset($dados['metodo']) ? $dados['metodo'] : 'pix',
            'comprovante_caminho' => $stored ? $stored['relative_path'] : null,
            'comprovante_nome_original' => $stored ? $stored['original_name'] : null,
            'referencia_bancaria' => isset($dados['referencia_bancaria']) ? $dados['referencia_bancaria'] : null,
            'status' => 'pago',
        ));

        $this->repasseModel->updateStatus($repasseId, 'pago', date('Y-m-d H:i:s'));

        $this->auditService->record(
            'financeiro.pagamento.registrado',
            'pagamento_professor',
            $pagamentoId,
            array('repasse_professor_id' => $repasseId),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('financeiro.pagamento.registrado', array(
            'repasse_id' => $repasseId,
            'pagamento_id' => $pagamentoId,
        ));

        return array('ok' => true, 'pagamento_id' => $pagamentoId);
    }

    public function listarPorProfessor($usuarioId)
    {
        return array(
            'repasses' => $this->repasseModel->forProfessor($usuarioId),
            'espelhos' => $this->rpaModel->forProfessor($usuarioId),
            'fiscal' => $this->professorFiscalModel->findByUsuarioId($usuarioId),
        );
    }

    public function gerarEspelhoRpa($repasseId, ?array $apuracao = null, array $dados = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $repasse = $this->repasseModel->findById($repasseId);
        if (!$repasse) {
            return null;
        }

        $fiscal = $this->professorFiscalModel->findByUsuarioId((int) $repasse['usuario_id']);
        $nome = $fiscal && !empty($fiscal['razao_social']) ? $fiscal['razao_social'] : $repasse['usuario_nome'];
        $cpf = $fiscal && !empty($fiscal['cpf']) ? $fiscal['cpf'] : null;
        $competencia = $apuracao ? $apuracao['competencia'] : $repasse['competencia'];
        $html = $this->renderRpaEspelho($repasse, $nome, $cpf, $competencia);

        $relative = 'rpa_espelhos/' . $competencia . '/repasse-' . $repasseId . '.html';
        $absolute = BASE_PATH . '/storage/private_uploads/' . $relative;
        $directory = dirname($absolute);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
        file_put_contents($absolute, $html);

        $rpaId = $this->rpaModel->create(array(
            'repasse_professor_id' => $repasseId,
            'usuario_id' => (int) $repasse['usuario_id'],
            'competencia' => $competencia,
            'valor_bruto' => (float) $repasse['valor_bruto'],
            'valor_retenido' => (float) $repasse['valor_retenido'],
            'valor_liquido' => (float) $repasse['valor_liquido'],
            'cpf' => $cpf,
            'nome' => $nome,
            'arquivo_caminho' => $relative,
            'arquivo_nome_original' => 'espelho-rpa-' . $competencia . '.html',
            'status' => 'gerado',
        ));

        $this->auditService->record(
            'financeiro.rpa.gerado',
            'rpa_espelho',
            $rpaId,
            array('repasse_professor_id' => $repasseId),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        return $rpaId;
    }

    public function listarRepassesAdmin()
    {
        return $this->repasseModel->allAdmin();
    }

    public function listarApuracoesAdmin()
    {
        return $this->apuracaoModel->allAdmin();
    }

    public function salvarProfessorFiscal(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = $this->professorFiscalModel->save($data);

        $this->auditService->record(
            'financeiro.professor_fiscal.salvo',
            'professores_fiscal',
            $id,
            $data,
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('financeiro.professor_fiscal.salvo', array('professor_fiscal_id' => $id));

        return array('ok' => true, 'id' => $id);
    }

    private function renderRpaEspelho(array $repasse, $nome, $cpf, $competencia)
    {
        return '<html><body><h1>Espelho de RPA</h1><p>Competência: ' . htmlspecialchars($competencia, ENT_QUOTES, 'UTF-8') . '</p><p>Professor: ' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</p><p>CPF: ' . htmlspecialchars((string) $cpf, ENT_QUOTES, 'UTF-8') . '</p><p>Valor bruto: R$ ' . number_format((float) $repasse['valor_bruto'], 2, ',', '.') . '</p><p>Retenção: R$ ' . number_format((float) $repasse['valor_retenido'], 2, ',', '.') . '</p><p>Líquido: R$ ' . number_format((float) $repasse['valor_liquido'], 2, ',', '.') . '</p></body></html>';
    }
}


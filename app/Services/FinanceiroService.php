<?php

namespace App\Services;

use App\Core\Logger;
use App\Models\ApuracaoMensal;
use App\Models\ProfessorFiscal;
use App\Models\Usuario;
use App\Models\RepasseProfessor;

class FinanceiroService
{
    private $configuracaoGlobalService;
    private $rateioService;
    private $repasseService;
    private $apuracaoModel;
    private $repasseModel;
    private $professorFiscalModel;
    private $usuarioModel;
    private $auditService;

    public function __construct()
    {
        $this->configuracaoGlobalService = new ConfiguracaoGlobalService();
        $this->rateioService = new RateioService();
        $this->repasseService = new RepasseProfessorService();
        $this->apuracaoModel = new ApuracaoMensal();
        $this->repasseModel = new RepasseProfessor();
        $this->professorFiscalModel = new ProfessorFiscal();
        $this->usuarioModel = new Usuario();
        $this->auditService = new AuditService();
    }

    public function painelAdmin()
    {
        return array(
            'configuracao_financeira' => $this->configuracaoGlobalService->financeiro(),
            'apuracoes' => $this->apuracaoModel->allAdmin(),
            'repasses' => $this->repasseModel->allAdmin(),
            'professores_fiscal' => $this->professorFiscalModel->allActive(),
            'professores' => $this->usuarioModel->professores(),
        );
    }

    public function painelProfessor($usuarioId)
    {
        return $this->repasseService->listarPorProfessor($usuarioId);
    }

    public function apurarCompetencia($competencia, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $result = $this->rateioService->apurarCompetencia($competencia, $actorUserId, $ipAddress, $userAgent);

        if (empty($result['ok'])) {
            return $result;
        }

        $this->auditService->record(
            'financeiro.apuracao.gerada',
            'apuracao_mensal',
            $result['apuracao_id'],
            array('competencia' => $competencia),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('financeiro.apuracao.gerada', array(
            'apuracao_id' => $result['apuracao_id'],
            'competencia' => $competencia,
        ));

        return $result;
    }

    public function gerarRepasses($apuracaoId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->repasseService->gerarRepassesDaApuracao($apuracaoId, $actorUserId, $ipAddress, $userAgent);
    }

    public function salvarProfessorFiscal(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (empty($data['usuario_id']) || (int) $data['usuario_id'] <= 0) {
            return array('ok' => false, 'message' => 'Informe o professor.');
        }

        $tipoPessoa = isset($data['tipo_pessoa']) ? trim((string) $data['tipo_pessoa']) : 'pf';
        if (!in_array($tipoPessoa, array('pf', 'pj'), true)) {
            return array('ok' => false, 'message' => 'Tipo fiscal invalido.');
        }

        if ($tipoPessoa === 'pj' && empty($data['cnpj']) && empty($data['razao_social'])) {
            return array('ok' => false, 'message' => 'Para PJ informe CNPJ e razao social.');
        }

        return $this->repasseService->salvarProfessorFiscal($data, $actorUserId, $ipAddress, $userAgent);
    }

    public function registrarDocumento($repasseId, array $file, $tipoDocumento = 'outro', $numeroDocumento = null, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->repasseService->anexarDocumento($repasseId, $file, $tipoDocumento, $numeroDocumento, $observacao, $actorUserId, $ipAddress, $userAgent);
    }

    public function registrarPagamento($repasseId, array $dados, ?array $arquivo = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->repasseService->registrarPagamento($repasseId, $dados, $arquivo, $actorUserId, $ipAddress, $userAgent);
    }

    public function listarRepassesApuracao($apuracaoId = null)
    {
        if (!empty($apuracaoId)) {
            return $this->repasseModel->forApuracao($apuracaoId);
        }

        return $this->repasseModel->allAdmin();
    }
}

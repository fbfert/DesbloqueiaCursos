<?php

namespace App\Services;

use App\Core\Logger;
use App\Models\ApuracaoMensal;
use App\Models\ProfessorFiscal;
use App\Models\Usuario;
use App\Models\RepasseProfessor;
use App\Services\RbacService;

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
    private $rbacService;
    private $trashService;

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
        $this->rbacService = new RbacService();
        $this->trashService = new TrashService();
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
        if (!$this->podeGerirFinanceiro($actorUserId)) {
            $this->registrarAcessoNegado('financeiro.repasses.negado', $apuracaoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        return $this->repasseService->gerarRepassesDaApuracao($apuracaoId, $actorUserId, $ipAddress, $userAgent);
    }

    public function salvarProfessorFiscal(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->podeGerirFinanceiro($actorUserId)) {
            $this->registrarAcessoNegado('financeiro.professor_fiscal.negado', isset($data['usuario_id']) ? $data['usuario_id'] : null, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

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

    public function removerProfessorFiscal($perfilId, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->podeGerirFinanceiro($actorUserId)) {
            $this->registrarAcessoNegado('financeiro.professor_fiscal.remocao_negada', $perfilId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        $perfil = $this->professorFiscalModel->findById($perfilId);
        if (!$perfil) {
            return array('ok' => false, 'message' => 'Perfil fiscal nao encontrado.');
        }

        $justificativa = trim((string) $justificativa);
        if ($justificativa === '') {
            return array('ok' => false, 'message' => 'Informe a justificativa para remover o perfil fiscal.');
        }

        $this->trashService->record('professores_fiscal', $perfilId, $justificativa, $perfil, $actorUserId, $ipAddress, $userAgent);
        $this->professorFiscalModel->softDelete($perfilId);

        $this->auditService->record(
            'financeiro.professor_fiscal.removido',
            'professores_fiscal',
            $perfilId,
            array('justificativa' => $justificativa, 'snapshot' => $perfil),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('financeiro.professor_fiscal.removido', array('professor_fiscal_id' => $perfilId));

        return array('ok' => true);
    }

    public function registrarDocumento($repasseId, array $file, $tipoDocumento = 'outro', $numeroDocumento = null, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->podeManipularRepasse($repasseId, $actorUserId)) {
            $this->registrarAcessoNegado('financeiro.documento.negado', $repasseId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        return $this->repasseService->anexarDocumento($repasseId, $file, $tipoDocumento, $numeroDocumento, $observacao, $actorUserId, $ipAddress, $userAgent);
    }

    public function registrarPagamento($repasseId, array $dados, ?array $arquivo = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->podeManipularRepasse($repasseId, $actorUserId)) {
            $this->registrarAcessoNegado('financeiro.pagamento.negado', $repasseId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        return $this->repasseService->registrarPagamento($repasseId, $dados, $arquivo, $actorUserId, $ipAddress, $userAgent);
    }

    public function listarRepassesApuracao($apuracaoId = null)
    {
        if (!empty($apuracaoId)) {
            return $this->repasseModel->forApuracao($apuracaoId);
        }

        return $this->repasseModel->allAdmin();
    }

    private function podeGerirFinanceiro($usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        return $this->rbacService->userHasPermission($usuarioId, 'financeiro.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'pedidos.ver');
    }

    private function podeManipularRepasse($repasseId, $usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        if ($this->podeGerirFinanceiro($usuarioId)) {
            return true;
        }

        $repasse = $this->repasseModel->findById($repasseId);
        if (!$repasse) {
            return false;
        }

        return (int) $repasse['usuario_id'] === (int) $usuarioId;
    }

    private function registrarAcessoNegado($evento, $recursoId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $payload = array(
            'recurso_id' => $recursoId,
            'usuario_id' => $actorUserId,
            'ip_address' => $ipAddress,
        );

        $this->auditService->record($evento, 'financeiro', $recursoId, $payload, $actorUserId, $ipAddress, $userAgent);
        Logger::error($evento, $payload);
    }
}

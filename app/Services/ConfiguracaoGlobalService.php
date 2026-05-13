<?php

namespace App\Services;

use App\Core\Logger;
use App\Core\Validator;
use App\Models\ConfiguracaoCertificado;
use App\Models\ConfiguracaoFinanceira;
use App\Models\ConfiguracaoFrontend;
use App\Models\ConfiguracaoGlobal;
use App\Models\ConfiguracaoSeguranca;

class ConfiguracaoGlobalService
{
    private $globalModel;
    private $certificadoModel;
    private $financeiraModel;
    private $frontendModel;
    private $segurancaModel;
    private $auditService;

    public function __construct()
    {
        $this->globalModel = new ConfiguracaoGlobal();
        $this->certificadoModel = new ConfiguracaoCertificado();
        $this->financeiraModel = new ConfiguracaoFinanceira();
        $this->frontendModel = new ConfiguracaoFrontend();
        $this->segurancaModel = new ConfiguracaoSeguranca();
        $this->auditService = new AuditService();
    }

    public function all()
    {
        return array(
            'institucional' => $this->institucional(),
            'certificados' => $this->certificados(),
            'financeiro' => $this->financeiro(),
            'frontend' => $this->frontend(),
            'seguranca' => $this->seguranca(),
        );
    }

    public function institucional()
    {
        $current = $this->globalModel->current();

        return $current ?: array(
            'nome_fantasia' => 'Desbloqueia Cursos',
            'razao_social' => null,
            'cnpj' => null,
            'cidade' => null,
            'uf' => null,
            'email_institucional' => null,
            'email_financeiro' => null,
            'email_suporte' => null,
            'email_certificados' => null,
            'telefone' => null,
            'logo_caminho' => null,
        );
    }

    public function certificados()
    {
        $current = $this->certificadoModel->current();

        return $current ?: array(
            'prefixo_certificado' => 'PRC',
            'titulo_padrao' => null,
            'texto_validacao_publica' => null,
        );
    }

    public function financeiro()
    {
        $current = $this->financeiraModel->current();

        return $current ?: array(
            'data_corte_financeiro' => null,
            'percentual_rateio_maximo' => 75.00,
            'observacao_repasse' => null,
        );
    }

    public function frontend()
    {
        $current = $this->frontendModel->current();

        return $current ?: array(
            'template_visual_portal' => 'padrao',
            'cor_primaria' => null,
            'cor_secundaria' => null,
            'logo_caminho' => null,
            'banner_caminho' => null,
            'descricao_home' => null,
            'home_destaques_limite' => 6,
        );
    }

    public function seguranca()
    {
        $current = $this->segurancaModel->current();

        return $current ?: array(
            'politica_login' => 'email_cpf',
            'validade_reset_senha_minutos' => 60,
            'max_tentativas_login' => 5,
            'tempo_bloqueio_login_minutos' => 15,
        );
    }

    public function emailDefaults()
    {
        $institucional = $this->institucional();

        return array(
            'from_email' => !empty($institucional['email_institucional']) ? $institucional['email_institucional'] : (!empty($institucional['email_suporte']) ? $institucional['email_suporte'] : null),
            'from_name' => !empty($institucional['nome_fantasia']) ? $institucional['nome_fantasia'] : 'Desbloqueia Cursos',
            'reply_to' => !empty($institucional['email_suporte']) ? $institucional['email_suporte'] : (!empty($institucional['email_institucional']) ? $institucional['email_institucional'] : null),
        );
    }

    public function certificatePrefix()
    {
        $certificados = $this->certificados();

        return !empty($certificados['prefixo_certificado']) ? $certificados['prefixo_certificado'] : 'PRC';
    }

    public function saveInstitucional(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $payload = array(
            'nome_fantasia' => trim((string) (isset($data['nome_fantasia']) ? $data['nome_fantasia'] : '')),
            'razao_social' => isset($data['razao_social']) ? trim((string) $data['razao_social']) : null,
            'cnpj' => isset($data['cnpj']) ? Validator::onlyDigits($data['cnpj']) : null,
            'cidade' => isset($data['cidade']) ? trim((string) $data['cidade']) : null,
            'uf' => isset($data['uf']) ? strtoupper(substr(trim((string) $data['uf']), 0, 2)) : null,
            'email_institucional' => isset($data['email_institucional']) ? trim((string) $data['email_institucional']) : null,
            'email_financeiro' => isset($data['email_financeiro']) ? trim((string) $data['email_financeiro']) : null,
            'email_suporte' => isset($data['email_suporte']) ? trim((string) $data['email_suporte']) : null,
            'email_certificados' => isset($data['email_certificados']) ? trim((string) $data['email_certificados']) : null,
            'telefone' => isset($data['telefone']) ? trim((string) $data['telefone']) : null,
            'logo_caminho' => isset($data['logo_caminho']) ? trim((string) $data['logo_caminho']) : null,
        );

        $errors = $this->validateInstitucional($payload);
        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $id = $this->globalModel->save($payload);
        $this->auditSave('configuracoes_globais', $id, 'configuracoes_globais.atualizada', $payload, $actorUserId, $ipAddress, $userAgent);

        return array('ok' => true, 'id' => $id);
    }

    public function saveCertificados(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $payload = array(
            'prefixo_certificado' => isset($data['prefixo_certificado']) ? strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $data['prefixo_certificado'])) : 'PRC',
            'titulo_padrao' => isset($data['titulo_padrao']) ? trim((string) $data['titulo_padrao']) : null,
            'texto_validacao_publica' => isset($data['texto_validacao_publica']) ? trim((string) $data['texto_validacao_publica']) : null,
        );

        $errors = $this->validateCertificados($payload);
        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $id = $this->certificadoModel->save($payload);
        $this->auditSave('configuracoes_certificados', $id, 'configuracoes_certificados.atualizada', $payload, $actorUserId, $ipAddress, $userAgent);

        return array('ok' => true, 'id' => $id);
    }

    public function saveFinanceiro(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $payload = array(
            'data_corte_financeiro' => !empty($data['data_corte_financeiro']) ? $data['data_corte_financeiro'] : null,
            'percentual_rateio_maximo' => isset($data['percentual_rateio_maximo']) ? (float) $data['percentual_rateio_maximo'] : 75.00,
            'observacao_repasse' => isset($data['observacao_repasse']) ? trim((string) $data['observacao_repasse']) : null,
        );

        $errors = $this->validateFinanceiro($payload);
        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $id = $this->financeiraModel->save($payload);
        $this->auditSave('configuracoes_financeiras', $id, 'configuracoes_financeiras.atualizada', $payload, $actorUserId, $ipAddress, $userAgent);

        return array('ok' => true, 'id' => $id);
    }

    public function saveFrontend(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $payload = array(
            'template_visual_portal' => isset($data['template_visual_portal']) ? trim((string) $data['template_visual_portal']) : 'padrao',
            'cor_primaria' => isset($data['cor_primaria']) ? trim((string) $data['cor_primaria']) : null,
            'cor_secundaria' => isset($data['cor_secundaria']) ? trim((string) $data['cor_secundaria']) : null,
            'logo_caminho' => isset($data['logo_caminho']) ? trim((string) $data['logo_caminho']) : null,
            'banner_caminho' => isset($data['banner_caminho']) ? trim((string) $data['banner_caminho']) : null,
            'descricao_home' => isset($data['descricao_home']) ? trim((string) $data['descricao_home']) : null,
            'home_destaques_limite' => $this->normalizeHomeDestaquesLimite(isset($data['home_destaques_limite']) ? $data['home_destaques_limite'] : null),
        );

        $errors = $this->validateFrontend($payload);
        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $id = $this->frontendModel->save($payload);
        $this->auditSave('configuracoes_frontend', $id, 'configuracoes_frontend.atualizada', $payload, $actorUserId, $ipAddress, $userAgent);

        return array('ok' => true, 'id' => $id);
    }

    public function saveSeguranca(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $payload = array(
            'politica_login' => isset($data['politica_login']) ? trim((string) $data['politica_login']) : 'email_cpf',
            'validade_reset_senha_minutos' => isset($data['validade_reset_senha_minutos']) ? (int) $data['validade_reset_senha_minutos'] : 60,
            'max_tentativas_login' => isset($data['max_tentativas_login']) ? (int) $data['max_tentativas_login'] : 5,
            'tempo_bloqueio_login_minutos' => isset($data['tempo_bloqueio_login_minutos']) ? (int) $data['tempo_bloqueio_login_minutos'] : 15,
        );

        $errors = $this->validateSeguranca($payload);
        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $id = $this->segurancaModel->save($payload);
        $this->auditSave('configuracoes_seguranca', $id, 'configuracoes_seguranca.atualizada', $payload, $actorUserId, $ipAddress, $userAgent);

        return array('ok' => true, 'id' => $id);
    }

    public function templateVisualPortal()
    {
        $frontend = $this->frontend();
        return $frontend['template_visual_portal'];
    }

    public function homeDestaquesLimite()
    {
        $frontend = $this->frontend();
        return $this->normalizeHomeDestaquesLimite(isset($frontend['home_destaques_limite']) ? $frontend['home_destaques_limite'] : null);
    }

    private function auditSave($entityType, $entityId, $action, array $metadata, $actorUserId, $ipAddress, $userAgent)
    {
        $this->auditService->record($action, $entityType, $entityId, $metadata, $actorUserId, $ipAddress, $userAgent);
        Logger::info($action, array('entity_type' => $entityType, 'entity_id' => $entityId));
    }

    private function validateInstitucional(array $payload)
    {
        $errors = array();

        if ($payload['nome_fantasia'] === '') {
            $errors['nome_fantasia'] = 'Informe o nome fantasia.';
        }

        if (!empty($payload['cnpj']) && strlen($payload['cnpj']) !== 14) {
            $errors['cnpj'] = 'Informe um CNPJ valido.';
        }

        if (!empty($payload['email_institucional']) && !Validator::email($payload['email_institucional'])) {
            $errors['email_institucional'] = 'Informe um e-mail institucional valido.';
        }

        if (!empty($payload['email_financeiro']) && !Validator::email($payload['email_financeiro'])) {
            $errors['email_financeiro'] = 'Informe um e-mail financeiro valido.';
        }

        if (!empty($payload['email_suporte']) && !Validator::email($payload['email_suporte'])) {
            $errors['email_suporte'] = 'Informe um e-mail de suporte valido.';
        }

        if (!empty($payload['email_certificados']) && !Validator::email($payload['email_certificados'])) {
            $errors['email_certificados'] = 'Informe um e-mail de certificados valido.';
        }

        return $errors;
    }

    private function validateCertificados(array $payload)
    {
        $errors = array();

        if ($payload['prefixo_certificado'] === '') {
            $errors['prefixo_certificado'] = 'Informe o prefixo do certificado.';
        }

        if (strlen($payload['prefixo_certificado']) > 20) {
            $errors['prefixo_certificado'] = 'O prefixo do certificado deve ter ate 20 caracteres.';
        }

        return $errors;
    }

    private function validateFinanceiro(array $payload)
    {
        $errors = array();

        if ($payload['percentual_rateio_maximo'] < 0 || $payload['percentual_rateio_maximo'] > 75) {
            $errors['percentual_rateio_maximo'] = 'O rateio maximo nao pode ultrapassar 75%.';
        }

        return $errors;
    }

    private function validateFrontend(array $payload)
    {
        $errors = array();

        if ($payload['template_visual_portal'] === '') {
            $errors['template_visual_portal'] = 'Informe o template visual do portal.';
        }

        return $errors;
    }

    private function validateSeguranca(array $payload)
    {
        $errors = array();
        $allowed = array('email_cpf', 'email', 'cpf');

        if (!in_array($payload['politica_login'], $allowed, true)) {
            $errors['politica_login'] = 'Politica de login invalida.';
        }

        if ($payload['validade_reset_senha_minutos'] <= 0) {
            $errors['validade_reset_senha_minutos'] = 'A validade do reset de senha deve ser maior que zero.';
        }

        if ($payload['max_tentativas_login'] <= 0) {
            $errors['max_tentativas_login'] = 'O numero maximo de tentativas deve ser maior que zero.';
        }

        if ($payload['tempo_bloqueio_login_minutos'] <= 0) {
            $errors['tempo_bloqueio_login_minutos'] = 'O tempo de bloqueio deve ser maior que zero.';
        }

        return $errors;
    }

    private function normalizeHomeDestaquesLimite($value)
    {
        if ($value === null) {
            return 6;
        }

        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^\d+$/', $value)) {
            return 6;
        }

        $limite = (int) $value;
        if ($limite < 1 || $limite > 12) {
            return 6;
        }

        return $limite;
    }
}




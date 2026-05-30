<?php

namespace App\Services;

use App\Core\Logger;
use App\Core\Helpers;
use App\Core\Validator;
use App\Models\ConfiguracaoCertificado;
use App\Models\ConfiguracaoFinanceira;
use App\Models\ConfiguracaoFrontend;
use App\Models\ConfiguracaoGlobal;
use App\Models\ConfiguracaoSeguranca;

class ConfiguracaoGlobalService
{
    const FRONTEND_CARD_GAP_DEFAULT = 'clamp(16px, 2vw, 24px)';
    const FRONTEND_SECTION_GAP_DEFAULT = 'clamp(24px, 3vw, 40px)';

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

        $defaults = array(
            'prefixo_certificado' => 'PRC',
            'titulo_padrao' => null,
            'texto_validacao_publica' => null,

            'certificados_habilitado' => 1,
            'certificados_emissao_habilitada' => 1,
            'certificados_modo_emissao' => 'manual',
            'certificados_exibir_area_aluno' => 1,
            'certificados_permitir_download' => 1,
            'certificados_permitir_reemissao_aluno' => 0,
            'certificados_exibir_botao_validacao_publica' => 1,

            'certificados_exigir_inscricao_concluida' => 0,
            'certificados_exigir_pagamento_aprovado' => 0,
            'certificados_exigir_presenca_minima' => 0,
            'certificados_percentual_presenca_minima' => 75.00,
            'certificados_exigir_conclusao_aulas' => 0,
            'certificados_percentual_conclusao_minima' => 100.00,
            'certificados_exigir_avaliacao' => 0,
            'certificados_nota_minima' => 70.00,
            'certificados_exigir_atividades_aprovadas' => 0,
            'certificados_permitir_emissao_com_pendencias_admin' => 1,
            'certificados_status_inscricao_permitidos' => 'ativa,em_andamento,concluida,concluida_sem_certificado,certificado_emitido',
            'certificados_observacao_regras_emissao' => null,

            'certificados_exibir_nome_aluno' => 1,
            'certificados_exibir_documento_aluno' => 1,
            'certificados_exibir_nome_curso' => 1,
            'certificados_exibir_turma' => 1,
            'certificados_exibir_carga_horaria' => 1,
            'certificados_exibir_modalidade' => 0,
            'certificados_exibir_periodo_curso' => 1,
            'certificados_exibir_data_conclusao' => 1,
            'certificados_exibir_data_emissao' => 1,
            'certificados_exibir_codigo_certificado' => 1,
            'certificados_exibir_qrcode' => 1,
            'certificados_exibir_url_validacao' => 1,
            'certificados_exibir_professor_responsavel' => 0,
            'certificados_exibir_coordenador_institucional' => 0,
            'certificados_exibir_cnpj_instituicao' => 0,
            'certificados_exibir_local_emissao' => 1,

            'certificados_validacao_publica_habilitada' => 1,
            'certificados_validacao_exibir_nome_aluno' => 1,
            'certificados_validacao_exibir_curso' => 1,
            'certificados_validacao_exibir_carga_horaria' => 0,
            'certificados_validacao_exibir_data_emissao' => 1,
            'certificados_validacao_exibir_status' => 1,
            'certificados_validacao_exibir_motivo_bloqueio' => 0,
            'certificados_codigo_formato' => 'alfanumerico',
            'certificados_codigo_prefixo' => null,
            'certificados_codigo_tamanho_minimo' => 10,
            'certificados_permitir_validacao_por_qrcode' => 1,
            'certificados_url_validacao_publica_base' => null,
            'certificados_mensagem_valido' => null,
            'certificados_mensagem_invalido' => null,
            'certificados_mensagem_cancelado' => null,

            'certificados_template_padrao_id' => null,
            'certificados_orientacao_padrao' => 'paisagem',
            'certificados_tamanho_papel_padrao' => 'A4',
            'certificados_margem_top_padrao' => null,
            'certificados_margem_bottom_padrao' => null,
            'certificados_margem_left_padrao' => null,
            'certificados_margem_right_padrao' => null,
            'certificados_usar_imagem_fundo' => 0,
            'certificados_imagem_fundo_padrao' => null,
            'certificados_usar_logo_institucional' => 1,
            'certificados_logo_padrao' => null,
            'certificados_qrcode_habilitado' => 1,
            'certificados_qrcode_posicao_padrao' => 'inferior_direita',
            'certificados_observacoes_layout' => null,

            'certificados_assinatura_1_exibir' => 0,
            'certificados_assinatura_1_nome' => null,
            'certificados_assinatura_1_cargo' => null,
            'certificados_assinatura_1_imagem' => null,
            'certificados_assinatura_2_exibir' => 0,
            'certificados_assinatura_2_nome' => null,
            'certificados_assinatura_2_cargo' => null,
            'certificados_assinatura_2_imagem' => null,
            'certificados_assinatura_3_exibir' => 0,
            'certificados_assinatura_3_nome' => null,
            'certificados_assinatura_3_cargo' => null,
            'certificados_assinatura_3_imagem' => null,
            'certificados_permitir_assinatura_professor' => 0,
            'certificados_permitir_assinatura_coordenador' => 0,

            'certificados_permitir_segunda_via' => 1,
            'certificados_registrar_numero_via' => 1,
            'certificados_manter_historico_reemissoes' => 1,
            'certificados_permitir_cancelamento' => 1,
            'certificados_exigir_motivo_cancelamento' => 1,
            'certificados_registrar_usuario_emissor' => 1,
            'certificados_registrar_usuario_cancelou' => 1,
            'certificados_registrar_ip_data_hora_emissao' => 1,
            'certificados_regenerar_pdf_mesmo_codigo' => 1,
            'certificados_bloquear_alteracao_apos_emitido' => 0,

            'certificados_texto_padrao' => null,
            'certificados_texto_rodape' => null,
            'certificados_texto_validacao' => null,
            'certificados_texto_observacoes_legais' => null,
            'certificados_texto_indisponivel' => null,
            'certificados_texto_requisitos_nao_cumpridos' => null,
        );

        if (!$current) {
            return $defaults;
        }

        return array_merge($defaults, $current);
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

        $defaults = array(
            'template_visual_portal' => 'padrao',
            'cor_primaria' => null,
            'cor_secundaria' => null,
            'logo_caminho' => null,
            'banner_caminho' => null,
            'descricao_home' => null,
            'home_destaques_limite' => 6,
            'frontend_card_gap' => self::FRONTEND_CARD_GAP_DEFAULT,
            'frontend_section_gap' => self::FRONTEND_SECTION_GAP_DEFAULT,
        );

        if (!$current) {
            return $defaults;
        }

        $frontend = array_merge($defaults, $current);
        $frontend['frontend_card_gap'] = Helpers::sanitizeCssSpacingValue(
            isset($frontend['frontend_card_gap']) ? $frontend['frontend_card_gap'] : null,
            self::FRONTEND_CARD_GAP_DEFAULT
        );
        $frontend['frontend_section_gap'] = Helpers::sanitizeCssSpacingValue(
            isset($frontend['frontend_section_gap']) ? $frontend['frontend_section_gap'] : null,
            self::FRONTEND_SECTION_GAP_DEFAULT
        );

        return $frontend;
    }

    public function frontendCardGap()
    {
        $frontend = $this->frontend();

        return Helpers::sanitizeCssSpacingValue(
            isset($frontend['frontend_card_gap']) ? $frontend['frontend_card_gap'] : null,
            self::FRONTEND_CARD_GAP_DEFAULT
        );
    }

    public function frontendSectionGap()
    {
        $frontend = $this->frontend();

        return Helpers::sanitizeCssSpacingValue(
            isset($frontend['frontend_section_gap']) ? $frontend['frontend_section_gap'] : null,
            self::FRONTEND_SECTION_GAP_DEFAULT
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

            'certificados_habilitado' => !empty($data['certificados_habilitado']) ? 1 : 0,
            'certificados_emissao_habilitada' => !empty($data['certificados_emissao_habilitada']) ? 1 : 0,
            'certificados_modo_emissao' => isset($data['certificados_modo_emissao']) ? trim((string) $data['certificados_modo_emissao']) : 'manual',
            'certificados_exibir_area_aluno' => !empty($data['certificados_exibir_area_aluno']) ? 1 : 0,
            'certificados_permitir_download' => !empty($data['certificados_permitir_download']) ? 1 : 0,
            'certificados_permitir_reemissao_aluno' => !empty($data['certificados_permitir_reemissao_aluno']) ? 1 : 0,
            'certificados_exibir_botao_validacao_publica' => !empty($data['certificados_exibir_botao_validacao_publica']) ? 1 : 0,

            'certificados_exigir_inscricao_concluida' => !empty($data['certificados_exigir_inscricao_concluida']) ? 1 : 0,
            'certificados_exigir_pagamento_aprovado' => !empty($data['certificados_exigir_pagamento_aprovado']) ? 1 : 0,
            'certificados_exigir_presenca_minima' => !empty($data['certificados_exigir_presenca_minima']) ? 1 : 0,
            'certificados_percentual_presenca_minima' => isset($data['certificados_percentual_presenca_minima']) ? (float) $data['certificados_percentual_presenca_minima'] : 75.00,
            'certificados_exigir_conclusao_aulas' => !empty($data['certificados_exigir_conclusao_aulas']) ? 1 : 0,
            'certificados_percentual_conclusao_minima' => isset($data['certificados_percentual_conclusao_minima']) ? (float) $data['certificados_percentual_conclusao_minima'] : 100.00,
            'certificados_exigir_avaliacao' => !empty($data['certificados_exigir_avaliacao']) ? 1 : 0,
            'certificados_nota_minima' => isset($data['certificados_nota_minima']) ? (float) $data['certificados_nota_minima'] : 70.00,
            'certificados_exigir_atividades_aprovadas' => !empty($data['certificados_exigir_atividades_aprovadas']) ? 1 : 0,
            'certificados_permitir_emissao_com_pendencias_admin' => !empty($data['certificados_permitir_emissao_com_pendencias_admin']) ? 1 : 0,
            'certificados_status_inscricao_permitidos' => isset($data['certificados_status_inscricao_permitidos']) ? trim((string) $data['certificados_status_inscricao_permitidos']) : null,
            'certificados_observacao_regras_emissao' => isset($data['certificados_observacao_regras_emissao']) ? trim((string) $data['certificados_observacao_regras_emissao']) : null,

            'certificados_exibir_nome_aluno' => !empty($data['certificados_exibir_nome_aluno']) ? 1 : 0,
            'certificados_exibir_documento_aluno' => !empty($data['certificados_exibir_documento_aluno']) ? 1 : 0,
            'certificados_exibir_nome_curso' => !empty($data['certificados_exibir_nome_curso']) ? 1 : 0,
            'certificados_exibir_turma' => !empty($data['certificados_exibir_turma']) ? 1 : 0,
            'certificados_exibir_carga_horaria' => !empty($data['certificados_exibir_carga_horaria']) ? 1 : 0,
            'certificados_exibir_modalidade' => !empty($data['certificados_exibir_modalidade']) ? 1 : 0,
            'certificados_exibir_periodo_curso' => !empty($data['certificados_exibir_periodo_curso']) ? 1 : 0,
            'certificados_exibir_data_conclusao' => !empty($data['certificados_exibir_data_conclusao']) ? 1 : 0,
            'certificados_exibir_data_emissao' => !empty($data['certificados_exibir_data_emissao']) ? 1 : 0,
            'certificados_exibir_codigo_certificado' => !empty($data['certificados_exibir_codigo_certificado']) ? 1 : 0,
            'certificados_exibir_qrcode' => !empty($data['certificados_exibir_qrcode']) ? 1 : 0,
            'certificados_exibir_url_validacao' => !empty($data['certificados_exibir_url_validacao']) ? 1 : 0,
            'certificados_exibir_professor_responsavel' => !empty($data['certificados_exibir_professor_responsavel']) ? 1 : 0,
            'certificados_exibir_coordenador_institucional' => !empty($data['certificados_exibir_coordenador_institucional']) ? 1 : 0,
            'certificados_exibir_cnpj_instituicao' => !empty($data['certificados_exibir_cnpj_instituicao']) ? 1 : 0,
            'certificados_exibir_local_emissao' => !empty($data['certificados_exibir_local_emissao']) ? 1 : 0,

            'certificados_validacao_publica_habilitada' => !empty($data['certificados_validacao_publica_habilitada']) ? 1 : 0,
            'certificados_validacao_exibir_nome_aluno' => !empty($data['certificados_validacao_exibir_nome_aluno']) ? 1 : 0,
            'certificados_validacao_exibir_curso' => !empty($data['certificados_validacao_exibir_curso']) ? 1 : 0,
            'certificados_validacao_exibir_carga_horaria' => !empty($data['certificados_validacao_exibir_carga_horaria']) ? 1 : 0,
            'certificados_validacao_exibir_data_emissao' => !empty($data['certificados_validacao_exibir_data_emissao']) ? 1 : 0,
            'certificados_validacao_exibir_status' => !empty($data['certificados_validacao_exibir_status']) ? 1 : 0,
            'certificados_validacao_exibir_motivo_bloqueio' => !empty($data['certificados_validacao_exibir_motivo_bloqueio']) ? 1 : 0,
            'certificados_codigo_formato' => isset($data['certificados_codigo_formato']) ? trim((string) $data['certificados_codigo_formato']) : 'alfanumerico',
            'certificados_codigo_prefixo' => isset($data['certificados_codigo_prefixo']) ? strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $data['certificados_codigo_prefixo'])) : null,
            'certificados_codigo_tamanho_minimo' => isset($data['certificados_codigo_tamanho_minimo']) ? (int) $data['certificados_codigo_tamanho_minimo'] : 10,
            'certificados_permitir_validacao_por_qrcode' => !empty($data['certificados_permitir_validacao_por_qrcode']) ? 1 : 0,
            'certificados_url_validacao_publica_base' => isset($data['certificados_url_validacao_publica_base']) ? trim((string) $data['certificados_url_validacao_publica_base']) : null,
            'certificados_mensagem_valido' => isset($data['certificados_mensagem_valido']) ? trim((string) $data['certificados_mensagem_valido']) : null,
            'certificados_mensagem_invalido' => isset($data['certificados_mensagem_invalido']) ? trim((string) $data['certificados_mensagem_invalido']) : null,
            'certificados_mensagem_cancelado' => isset($data['certificados_mensagem_cancelado']) ? trim((string) $data['certificados_mensagem_cancelado']) : null,

            'certificados_template_padrao_id' => !empty($data['certificados_template_padrao_id']) ? (int) $data['certificados_template_padrao_id'] : null,
            'certificados_orientacao_padrao' => isset($data['certificados_orientacao_padrao']) ? trim((string) $data['certificados_orientacao_padrao']) : 'paisagem',
            'certificados_tamanho_papel_padrao' => isset($data['certificados_tamanho_papel_padrao']) ? trim((string) $data['certificados_tamanho_papel_padrao']) : 'A4',
            'certificados_margem_top_padrao' => isset($data['certificados_margem_top_padrao']) && (string) $data['certificados_margem_top_padrao'] !== '' ? (float) $data['certificados_margem_top_padrao'] : null,
            'certificados_margem_bottom_padrao' => isset($data['certificados_margem_bottom_padrao']) && (string) $data['certificados_margem_bottom_padrao'] !== '' ? (float) $data['certificados_margem_bottom_padrao'] : null,
            'certificados_margem_left_padrao' => isset($data['certificados_margem_left_padrao']) && (string) $data['certificados_margem_left_padrao'] !== '' ? (float) $data['certificados_margem_left_padrao'] : null,
            'certificados_margem_right_padrao' => isset($data['certificados_margem_right_padrao']) && (string) $data['certificados_margem_right_padrao'] !== '' ? (float) $data['certificados_margem_right_padrao'] : null,
            'certificados_usar_imagem_fundo' => !empty($data['certificados_usar_imagem_fundo']) ? 1 : 0,
            'certificados_imagem_fundo_padrao' => isset($data['certificados_imagem_fundo_padrao']) ? trim((string) $data['certificados_imagem_fundo_padrao']) : null,
            'certificados_usar_logo_institucional' => !empty($data['certificados_usar_logo_institucional']) ? 1 : 0,
            'certificados_logo_padrao' => isset($data['certificados_logo_padrao']) ? trim((string) $data['certificados_logo_padrao']) : null,
            'certificados_qrcode_habilitado' => !empty($data['certificados_qrcode_habilitado']) ? 1 : 0,
            'certificados_qrcode_posicao_padrao' => isset($data['certificados_qrcode_posicao_padrao']) ? trim((string) $data['certificados_qrcode_posicao_padrao']) : 'inferior_direita',
            'certificados_observacoes_layout' => isset($data['certificados_observacoes_layout']) ? trim((string) $data['certificados_observacoes_layout']) : null,

            'certificados_assinatura_1_exibir' => !empty($data['certificados_assinatura_1_exibir']) ? 1 : 0,
            'certificados_assinatura_1_nome' => isset($data['certificados_assinatura_1_nome']) ? trim((string) $data['certificados_assinatura_1_nome']) : null,
            'certificados_assinatura_1_cargo' => isset($data['certificados_assinatura_1_cargo']) ? trim((string) $data['certificados_assinatura_1_cargo']) : null,
            'certificados_assinatura_1_imagem' => isset($data['certificados_assinatura_1_imagem']) ? trim((string) $data['certificados_assinatura_1_imagem']) : null,
            'certificados_assinatura_2_exibir' => !empty($data['certificados_assinatura_2_exibir']) ? 1 : 0,
            'certificados_assinatura_2_nome' => isset($data['certificados_assinatura_2_nome']) ? trim((string) $data['certificados_assinatura_2_nome']) : null,
            'certificados_assinatura_2_cargo' => isset($data['certificados_assinatura_2_cargo']) ? trim((string) $data['certificados_assinatura_2_cargo']) : null,
            'certificados_assinatura_2_imagem' => isset($data['certificados_assinatura_2_imagem']) ? trim((string) $data['certificados_assinatura_2_imagem']) : null,
            'certificados_assinatura_3_exibir' => !empty($data['certificados_assinatura_3_exibir']) ? 1 : 0,
            'certificados_assinatura_3_nome' => isset($data['certificados_assinatura_3_nome']) ? trim((string) $data['certificados_assinatura_3_nome']) : null,
            'certificados_assinatura_3_cargo' => isset($data['certificados_assinatura_3_cargo']) ? trim((string) $data['certificados_assinatura_3_cargo']) : null,
            'certificados_assinatura_3_imagem' => isset($data['certificados_assinatura_3_imagem']) ? trim((string) $data['certificados_assinatura_3_imagem']) : null,
            'certificados_permitir_assinatura_professor' => !empty($data['certificados_permitir_assinatura_professor']) ? 1 : 0,
            'certificados_permitir_assinatura_coordenador' => !empty($data['certificados_permitir_assinatura_coordenador']) ? 1 : 0,

            'certificados_permitir_segunda_via' => !empty($data['certificados_permitir_segunda_via']) ? 1 : 0,
            'certificados_registrar_numero_via' => !empty($data['certificados_registrar_numero_via']) ? 1 : 0,
            'certificados_manter_historico_reemissoes' => !empty($data['certificados_manter_historico_reemissoes']) ? 1 : 0,
            'certificados_permitir_cancelamento' => !empty($data['certificados_permitir_cancelamento']) ? 1 : 0,
            'certificados_exigir_motivo_cancelamento' => !empty($data['certificados_exigir_motivo_cancelamento']) ? 1 : 0,
            'certificados_registrar_usuario_emissor' => !empty($data['certificados_registrar_usuario_emissor']) ? 1 : 0,
            'certificados_registrar_usuario_cancelou' => !empty($data['certificados_registrar_usuario_cancelou']) ? 1 : 0,
            'certificados_registrar_ip_data_hora_emissao' => !empty($data['certificados_registrar_ip_data_hora_emissao']) ? 1 : 0,
            'certificados_regenerar_pdf_mesmo_codigo' => !empty($data['certificados_regenerar_pdf_mesmo_codigo']) ? 1 : 0,
            'certificados_bloquear_alteracao_apos_emitido' => !empty($data['certificados_bloquear_alteracao_apos_emitido']) ? 1 : 0,

            'certificados_texto_padrao' => isset($data['certificados_texto_padrao']) ? trim((string) $data['certificados_texto_padrao']) : null,
            'certificados_texto_rodape' => isset($data['certificados_texto_rodape']) ? trim((string) $data['certificados_texto_rodape']) : null,
            'certificados_texto_validacao' => isset($data['certificados_texto_validacao']) ? trim((string) $data['certificados_texto_validacao']) : null,
            'certificados_texto_observacoes_legais' => isset($data['certificados_texto_observacoes_legais']) ? trim((string) $data['certificados_texto_observacoes_legais']) : null,
            'certificados_texto_indisponivel' => isset($data['certificados_texto_indisponivel']) ? trim((string) $data['certificados_texto_indisponivel']) : null,
            'certificados_texto_requisitos_nao_cumpridos' => isset($data['certificados_texto_requisitos_nao_cumpridos']) ? trim((string) $data['certificados_texto_requisitos_nao_cumpridos']) : null,
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
        $frontendCardGap = isset($data['frontend_card_gap']) ? trim((string) $data['frontend_card_gap']) : '';
        if ($frontendCardGap === '') {
            $frontendCardGap = self::FRONTEND_CARD_GAP_DEFAULT;
        } elseif (!Helpers::isValidCssSpacingValue($frontendCardGap)) {
            return array(
                'ok' => false,
                'errors' => array(
                    'frontend_card_gap' => 'Informe um valor de espaçamento CSS válido.',
                ),
            );
        }

        $frontendSectionGap = isset($data['frontend_section_gap']) ? trim((string) $data['frontend_section_gap']) : '';
        if ($frontendSectionGap === '') {
            $frontendSectionGap = self::FRONTEND_SECTION_GAP_DEFAULT;
        } elseif (!Helpers::isValidCssSpacingValue($frontendSectionGap)) {
            return array(
                'ok' => false,
                'errors' => array(
                    'frontend_section_gap' => 'Informe um valor de espaçamento CSS válido.',
                ),
            );
        }

        $payload = array(
            'template_visual_portal' => isset($data['template_visual_portal']) ? trim((string) $data['template_visual_portal']) : 'padrao',
            'cor_primaria' => isset($data['cor_primaria']) ? trim((string) $data['cor_primaria']) : null,
            'cor_secundaria' => isset($data['cor_secundaria']) ? trim((string) $data['cor_secundaria']) : null,
            'logo_caminho' => isset($data['logo_caminho']) ? trim((string) $data['logo_caminho']) : null,
            'banner_caminho' => isset($data['banner_caminho']) ? trim((string) $data['banner_caminho']) : null,
            'descricao_home' => isset($data['descricao_home']) ? trim((string) $data['descricao_home']) : null,
            'home_destaques_limite' => $this->normalizeHomeDestaquesLimite(isset($data['home_destaques_limite']) ? $data['home_destaques_limite'] : null),
            'frontend_card_gap' => $frontendCardGap,
            'frontend_section_gap' => $frontendSectionGap,
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

        if ($payload['certificados_percentual_presenca_minima'] < 0 || $payload['certificados_percentual_presenca_minima'] > 100) {
            $errors['certificados_percentual_presenca_minima'] = 'O percentual mínimo de presença deve estar entre 0 e 100.';
        }

        if ($payload['certificados_percentual_conclusao_minima'] < 0 || $payload['certificados_percentual_conclusao_minima'] > 100) {
            $errors['certificados_percentual_conclusao_minima'] = 'O percentual mínimo de conclusão deve estar entre 0 e 100.';
        }

        if ($payload['certificados_nota_minima'] < 0 || $payload['certificados_nota_minima'] > 100) {
            $errors['certificados_nota_minima'] = 'A nota mínima deve estar entre 0 e 100.';
        }

        if (!in_array($payload['certificados_modo_emissao'], array('manual', 'automatica_conclusao', 'manual_prevalidacao'), true)) {
            $errors['certificados_modo_emissao'] = 'Modo de emissão inválido.';
        }

        if (!in_array($payload['certificados_codigo_formato'], array('alfanumerico', 'prefixo_ano_sequencial', 'hash_curto'), true)) {
            $errors['certificados_codigo_formato'] = 'Formato de código inválido.';
        }

        if (!in_array($payload['certificados_orientacao_padrao'], array('paisagem', 'retrato'), true)) {
            $errors['certificados_orientacao_padrao'] = 'Orientação padrão inválida.';
        }

        if (!in_array($payload['certificados_tamanho_papel_padrao'], array('A4', 'Carta'), true)) {
            $errors['certificados_tamanho_papel_padrao'] = 'Tamanho de papel padrão inválido.';
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

        if (!empty($payload['frontend_card_gap']) && !Helpers::isValidCssSpacingValue($payload['frontend_card_gap'])) {
            $errors['frontend_card_gap'] = 'Informe um valor de espaçamento CSS válido.';
        }

        if (!empty($payload['frontend_section_gap']) && !Helpers::isValidCssSpacingValue($payload['frontend_section_gap'])) {
            $errors['frontend_section_gap'] = 'Informe um valor de espaçamento CSS válido.';
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




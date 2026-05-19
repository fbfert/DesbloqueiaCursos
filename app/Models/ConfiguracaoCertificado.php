<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConfiguracaoCertificado
{
    private function fields()
    {
        return array(
            'prefixo_certificado',
            'titulo_padrao',
            'texto_validacao_publica',

            'certificados_habilitado',
            'certificados_emissao_habilitada',
            'certificados_modo_emissao',
            'certificados_exibir_area_aluno',
            'certificados_permitir_download',
            'certificados_permitir_reemissao_aluno',
            'certificados_exibir_botao_validacao_publica',

            'certificados_exigir_inscricao_concluida',
            'certificados_exigir_pagamento_aprovado',
            'certificados_exigir_presenca_minima',
            'certificados_percentual_presenca_minima',
            'certificados_exigir_conclusao_aulas',
            'certificados_percentual_conclusao_minima',
            'certificados_exigir_avaliacao',
            'certificados_nota_minima',
            'certificados_exigir_atividades_aprovadas',
            'certificados_permitir_emissao_com_pendencias_admin',
            'certificados_status_inscricao_permitidos',
            'certificados_observacao_regras_emissao',

            'certificados_exibir_nome_aluno',
            'certificados_exibir_documento_aluno',
            'certificados_exibir_nome_curso',
            'certificados_exibir_turma',
            'certificados_exibir_carga_horaria',
            'certificados_exibir_modalidade',
            'certificados_exibir_periodo_curso',
            'certificados_exibir_data_conclusao',
            'certificados_exibir_data_emissao',
            'certificados_exibir_codigo_certificado',
            'certificados_exibir_qrcode',
            'certificados_exibir_url_validacao',
            'certificados_exibir_professor_responsavel',
            'certificados_exibir_coordenador_institucional',
            'certificados_exibir_cnpj_instituicao',
            'certificados_exibir_local_emissao',

            'certificados_validacao_publica_habilitada',
            'certificados_validacao_exibir_nome_aluno',
            'certificados_validacao_exibir_curso',
            'certificados_validacao_exibir_carga_horaria',
            'certificados_validacao_exibir_data_emissao',
            'certificados_validacao_exibir_status',
            'certificados_validacao_exibir_motivo_bloqueio',
            'certificados_codigo_formato',
            'certificados_codigo_prefixo',
            'certificados_codigo_tamanho_minimo',
            'certificados_permitir_validacao_por_qrcode',
            'certificados_url_validacao_publica_base',
            'certificados_mensagem_valido',
            'certificados_mensagem_invalido',
            'certificados_mensagem_cancelado',

            'certificados_template_padrao_id',
            'certificados_orientacao_padrao',
            'certificados_tamanho_papel_padrao',
            'certificados_margem_top_padrao',
            'certificados_margem_bottom_padrao',
            'certificados_margem_left_padrao',
            'certificados_margem_right_padrao',
            'certificados_usar_imagem_fundo',
            'certificados_imagem_fundo_padrao',
            'certificados_usar_logo_institucional',
            'certificados_logo_padrao',
            'certificados_qrcode_habilitado',
            'certificados_qrcode_posicao_padrao',
            'certificados_observacoes_layout',

            'certificados_assinatura_1_exibir',
            'certificados_assinatura_1_nome',
            'certificados_assinatura_1_cargo',
            'certificados_assinatura_1_imagem',
            'certificados_assinatura_2_exibir',
            'certificados_assinatura_2_nome',
            'certificados_assinatura_2_cargo',
            'certificados_assinatura_2_imagem',
            'certificados_assinatura_3_exibir',
            'certificados_assinatura_3_nome',
            'certificados_assinatura_3_cargo',
            'certificados_assinatura_3_imagem',
            'certificados_permitir_assinatura_professor',
            'certificados_permitir_assinatura_coordenador',

            'certificados_permitir_segunda_via',
            'certificados_registrar_numero_via',
            'certificados_manter_historico_reemissoes',
            'certificados_permitir_cancelamento',
            'certificados_exigir_motivo_cancelamento',
            'certificados_registrar_usuario_emissor',
            'certificados_registrar_usuario_cancelou',
            'certificados_registrar_ip_data_hora_emissao',
            'certificados_regenerar_pdf_mesmo_codigo',
            'certificados_bloquear_alteracao_apos_emitido',

            'certificados_texto_padrao',
            'certificados_texto_rodape',
            'certificados_texto_validacao',
            'certificados_texto_observacoes_legais',
            'certificados_texto_indisponivel',
            'certificados_texto_requisitos_nao_cumpridos',
        );
    }

    public function current()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM configuracoes_certificados
             WHERE deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function save(array $data)
    {
        $current = $this->current();

        $payload = array();
        foreach ($this->fields() as $field) {
            $payload[$field] = array_key_exists($field, $data) ? $data[$field] : null;
        }

        if (!isset($payload['prefixo_certificado']) || trim((string) $payload['prefixo_certificado']) === '') {
            $payload['prefixo_certificado'] = 'PRC';
        } else {
            $payload['prefixo_certificado'] = strtoupper(trim((string) $payload['prefixo_certificado']));
        }

        if ($current) {
            $sets = array();
            foreach ($this->fields() as $field) {
                $sets[] = $field . ' = :' . $field;
            }

            $sql = 'UPDATE configuracoes_certificados SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id';
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute(array_merge($payload, array('id' => (int) $current['id'])));
            return (int) $current['id'];
        }

        $columns = $this->fields();
        $placeholders = array();
        foreach ($columns as $field) {
            $placeholders[] = ':' . $field;
        }

        $sql = 'INSERT INTO configuracoes_certificados (' . implode(', ', $columns) . ', created_at, updated_at, deleted_at) '
            . 'VALUES (' . implode(', ', $placeholders) . ', NOW(), NOW(), NULL)';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($payload);

        return (int) Database::connection()->lastInsertId();
    }
}

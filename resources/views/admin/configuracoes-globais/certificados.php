<?php
use App\Core\Helpers;

$config = isset($configuracao) && is_array($configuracao) ? $configuracao : array();
$v = function ($key, $default = '') use ($config) {
    return array_key_exists($key, $config) ? $config[$key] : $default;
};
$checked = function ($key, $default = 0) use ($v) {
    return !empty($v($key, $default)) ? 'checked' : '';
};
?>

<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Configurações de certificados</h1>
            <p class="admin-page__subtitle">Regras globais, validação pública, layout padrão e templates.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link" href="/admin/certificados/templates">Gerenciar templates de certificados</a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card" style="margin-bottom:12px;">
        <p class="muted" style="margin:0;">
            Alterações nas configurações podem afetar novas emissões. Certificados já emitidos seguem a regra de preservação definida pelo sistema.
        </p>
    </section>

    <form method="post" action="/admin/configuracoes-globais/certificados">
        <section class="status-card">
            <h2 style="margin-top:0;">Status do módulo</h2>
            <div class="form-grid">
                <label class="checkbox">
                    <input type="checkbox" name="certificados_habilitado" value="1" <?php echo $checked('certificados_habilitado', 1); ?>>
                    Habilitar módulo de certificados
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="certificados_emissao_habilitada" value="1" <?php echo $checked('certificados_emissao_habilitada', 1); ?>>
                    Permitir emissão de certificados
                </label>

                <label>
                    Modo de emissão
                    <?php $modo = (string) $v('certificados_modo_emissao', 'manual'); ?>
                    <select name="certificados_modo_emissao">
                        <option value="manual" <?php echo $modo === 'manual' ? 'selected' : ''; ?>>Manual</option>
                        <option value="automatica_conclusao" <?php echo $modo === 'automatica_conclusao' ? 'selected' : ''; ?>>Automática após conclusão</option>
                        <option value="manual_prevalidacao" <?php echo $modo === 'manual_prevalidacao' ? 'selected' : ''; ?>>Manual com pré-validação automática</option>
                    </select>
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="certificados_exibir_area_aluno" value="1" <?php echo $checked('certificados_exibir_area_aluno', 1); ?>>
                    Exibir certificados na área do aluno
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="certificados_permitir_download" value="1" <?php echo $checked('certificados_permitir_download', 1); ?>>
                    Permitir download pelo aluno
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="certificados_permitir_reemissao_aluno" value="1" <?php echo $checked('certificados_permitir_reemissao_aluno', 0); ?>>
                    Permitir reemissão pelo aluno
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="certificados_exibir_botao_validacao_publica" value="1" <?php echo $checked('certificados_exibir_botao_validacao_publica', 1); ?>>
                    Exibir botão de validação pública
                </label>
            </div>
        </section>

        <section class="status-card" style="margin-top:12px;">
            <h2 style="margin-top:0;">Regras de emissão</h2>
            <div class="form-grid">
                <label class="checkbox">
                    <input type="checkbox" name="certificados_exigir_inscricao_concluida" value="1" <?php echo $checked('certificados_exigir_inscricao_concluida', 0); ?>>
                    Exigir inscrição ativa/concluída (global)
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="certificados_exigir_pagamento_aprovado" value="1" <?php echo $checked('certificados_exigir_pagamento_aprovado', 0); ?>>
                    Exigir pagamento aprovado/liberado (global)
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="certificados_exigir_presenca_minima" value="1" <?php echo $checked('certificados_exigir_presenca_minima', 0); ?>>
                    Exigir presença mínima (global)
                </label>
                <label>
                    Percentual mínimo de presença
                    <input type="number" step="0.01" min="0" max="100" name="certificados_percentual_presenca_minima" value="<?php echo Helpers::e($v('certificados_percentual_presenca_minima', 75)); ?>">
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="certificados_exigir_conclusao_aulas" value="1" <?php echo $checked('certificados_exigir_conclusao_aulas', 0); ?>>
                    Exigir conclusão de aulas/módulos (global)
                </label>
                <label>
                    Percentual mínimo de conclusão
                    <input type="number" step="0.01" min="0" max="100" name="certificados_percentual_conclusao_minima" value="<?php echo Helpers::e($v('certificados_percentual_conclusao_minima', 100)); ?>">
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="certificados_exigir_avaliacao" value="1" <?php echo $checked('certificados_exigir_avaliacao', 0); ?>>
                    Exigir aprovação em avaliação (global)
                </label>
                <label>
                    Nota mínima / percentual mínimo
                    <input type="number" step="0.01" min="0" max="100" name="certificados_nota_minima" value="<?php echo Helpers::e($v('certificados_nota_minima', 70)); ?>">
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="certificados_exigir_atividades_aprovadas" value="1" <?php echo $checked('certificados_exigir_atividades_aprovadas', 0); ?>>
                    Exigir entrega/aprovação de atividades (global)
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="certificados_permitir_emissao_com_pendencias_admin" value="1" <?php echo $checked('certificados_permitir_emissao_com_pendencias_admin', 1); ?>>
                    Permitir emissão se houver pendências administrativas
                </label>
                <label style="grid-column:1/-1;">
                    Status de inscrição permitidos (separados por vírgula)
                    <input type="text" name="certificados_status_inscricao_permitidos" value="<?php echo Helpers::e($v('certificados_status_inscricao_permitidos')); ?>">
                </label>
                <label style="grid-column:1/-1;">
                    Observação administrativa sobre regras de emissão
                    <textarea name="certificados_observacao_regras_emissao" rows="3"><?php echo Helpers::e($v('certificados_observacao_regras_emissao')); ?></textarea>
                </label>
                <div class="status-card" style="grid-column:1/-1;margin-top:4px;">
                    <p class="muted" style="margin:0;">Observação: regras acadêmicas por curso/turma continuam sendo controladas no módulo acadêmico.</p>
                </div>
            </div>
        </section>

        <section class="status-card" style="margin-top:12px;">
            <h2 style="margin-top:0;">Dados exibidos</h2>
            <div class="form-grid">
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_nome_aluno" value="1" <?php echo $checked('certificados_exibir_nome_aluno', 1); ?>> Exibir nome do aluno</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_documento_aluno" value="1" <?php echo $checked('certificados_exibir_documento_aluno', 1); ?>> Exibir documento do aluno</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_nome_curso" value="1" <?php echo $checked('certificados_exibir_nome_curso', 1); ?>> Exibir nome do curso</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_turma" value="1" <?php echo $checked('certificados_exibir_turma', 1); ?>> Exibir turma</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_carga_horaria" value="1" <?php echo $checked('certificados_exibir_carga_horaria', 1); ?>> Exibir carga horária</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_modalidade" value="1" <?php echo $checked('certificados_exibir_modalidade', 0); ?>> Exibir modalidade</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_periodo_curso" value="1" <?php echo $checked('certificados_exibir_periodo_curso', 1); ?>> Exibir período do curso</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_data_conclusao" value="1" <?php echo $checked('certificados_exibir_data_conclusao', 1); ?>> Exibir data de conclusão</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_data_emissao" value="1" <?php echo $checked('certificados_exibir_data_emissao', 1); ?>> Exibir data de emissão</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_codigo_certificado" value="1" <?php echo $checked('certificados_exibir_codigo_certificado', 1); ?>> Exibir código do certificado</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_qrcode" value="1" <?php echo $checked('certificados_exibir_qrcode', 1); ?>> Exibir QR Code</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_url_validacao" value="1" <?php echo $checked('certificados_exibir_url_validacao', 1); ?>> Exibir URL de validação</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_professor_responsavel" value="1" <?php echo $checked('certificados_exibir_professor_responsavel', 0); ?>> Exibir professor responsável</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_coordenador_institucional" value="1" <?php echo $checked('certificados_exibir_coordenador_institucional', 0); ?>> Exibir coordenador/responsável institucional</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_cnpj_instituicao" value="1" <?php echo $checked('certificados_exibir_cnpj_instituicao', 0); ?>> Exibir CNPJ da instituição</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exibir_local_emissao" value="1" <?php echo $checked('certificados_exibir_local_emissao', 1); ?>> Exibir local/cidade de emissão</label>
            </div>
        </section>

        <section class="status-card" style="margin-top:12px;">
            <h2 style="margin-top:0;">Validação pública</h2>
            <div class="form-grid">
                <label class="checkbox"><input type="checkbox" name="certificados_validacao_publica_habilitada" value="1" <?php echo $checked('certificados_validacao_publica_habilitada', 1); ?>> Habilitar página pública de validação</label>
                <label class="checkbox"><input type="checkbox" name="certificados_validacao_exibir_nome_aluno" value="1" <?php echo $checked('certificados_validacao_exibir_nome_aluno', 1); ?>> Exibir nome do aluno</label>
                <label class="checkbox"><input type="checkbox" name="certificados_validacao_exibir_curso" value="1" <?php echo $checked('certificados_validacao_exibir_curso', 1); ?>> Exibir curso</label>
                <label class="checkbox"><input type="checkbox" name="certificados_validacao_exibir_carga_horaria" value="1" <?php echo $checked('certificados_validacao_exibir_carga_horaria', 0); ?>> Exibir carga horária</label>
                <label class="checkbox"><input type="checkbox" name="certificados_validacao_exibir_data_emissao" value="1" <?php echo $checked('certificados_validacao_exibir_data_emissao', 1); ?>> Exibir data de emissão</label>
                <label class="checkbox"><input type="checkbox" name="certificados_validacao_exibir_status" value="1" <?php echo $checked('certificados_validacao_exibir_status', 1); ?>> Exibir status</label>
                <label class="checkbox"><input type="checkbox" name="certificados_validacao_exibir_motivo_bloqueio" value="1" <?php echo $checked('certificados_validacao_exibir_motivo_bloqueio', 0); ?>> Exibir motivo de bloqueio/cancelamento</label>

                <label>
                    Formato do código
                    <?php $fmt = (string) $v('certificados_codigo_formato', 'alfanumerico'); ?>
                    <select name="certificados_codigo_formato">
                        <option value="alfanumerico" <?php echo $fmt === 'alfanumerico' ? 'selected' : ''; ?>>Alfanumérico</option>
                        <option value="prefixo_ano_sequencial" <?php echo $fmt === 'prefixo_ano_sequencial' ? 'selected' : ''; ?>>Prefixo + ano + sequencial</option>
                        <option value="hash_curto" <?php echo $fmt === 'hash_curto' ? 'selected' : ''; ?>>Hash curto</option>
                    </select>
                </label>
                <label>
                    Prefixo do código (opcional)
                    <input type="text" name="certificados_codigo_prefixo" value="<?php echo Helpers::e($v('certificados_codigo_prefixo')); ?>" placeholder="DC">
                </label>
                <label>
                    Tamanho mínimo do código/hash
                    <input type="number" min="4" max="80" name="certificados_codigo_tamanho_minimo" value="<?php echo Helpers::e($v('certificados_codigo_tamanho_minimo', 10)); ?>">
                </label>
                <label class="checkbox"><input type="checkbox" name="certificados_permitir_validacao_por_qrcode" value="1" <?php echo $checked('certificados_permitir_validacao_por_qrcode', 1); ?>> Permitir validação por QR Code</label>
                <label style="grid-column:1/-1;">
                    URL base de validação pública (opcional)
                    <input type="text" name="certificados_url_validacao_publica_base" value="<?php echo Helpers::e($v('certificados_url_validacao_publica_base')); ?>" placeholder="https://.../certificados/validar">
                </label>
                <label style="grid-column:1/-1;">
                    Mensagem para certificado válido
                    <textarea name="certificados_mensagem_valido" rows="2"><?php echo Helpers::e($v('certificados_mensagem_valido')); ?></textarea>
                </label>
                <label style="grid-column:1/-1;">
                    Mensagem para certificado inválido
                    <textarea name="certificados_mensagem_invalido" rows="2"><?php echo Helpers::e($v('certificados_mensagem_invalido')); ?></textarea>
                </label>
                <label style="grid-column:1/-1;">
                    Mensagem para certificado cancelado/revogado
                    <textarea name="certificados_mensagem_cancelado" rows="2"><?php echo Helpers::e($v('certificados_mensagem_cancelado')); ?></textarea>
                </label>

                <label style="grid-column:1/-1;">
                    Texto de validação pública (conteúdo complementar)
                    <textarea name="texto_validacao_publica" rows="3"><?php echo Helpers::e($v('texto_validacao_publica')); ?></textarea>
                </label>
            </div>
        </section>

        <section class="status-card" style="margin-top:12px;">
            <h2 style="margin-top:0;">Layout padrão</h2>
            <div class="form-grid">
                <label>
                    Prefixo do certificado
                    <input type="text" name="prefixo_certificado" value="<?php echo Helpers::e($v('prefixo_certificado', 'PRC')); ?>">
                </label>
                <label>
                    Título padrão
                    <input type="text" name="titulo_padrao" value="<?php echo Helpers::e($v('titulo_padrao')); ?>">
                </label>

                <label>
                    Template padrão global
                    <select name="certificados_template_padrao_id">
                        <option value="">(Automático)</option>
                        <?php $templatePadraoId = (int) $v('certificados_template_padrao_id', 0); ?>
                        <?php foreach (($templates ?? array()) as $t): ?>
                            <?php
                            $isGlobal = empty($t['curso_id']) && empty($t['turma_id']) && (empty($t['contexto']) || $t['contexto'] === 'global');
                            if (!$isGlobal) { continue; }
                            $label = '#' . (int) $t['id'] . ' - ' . (string) $t['nome'];
                            ?>
                            <option value="<?php echo (int) $t['id']; ?>" <?php echo (int) $t['id'] === $templatePadraoId ? 'selected' : ''; ?>>
                                <?php echo Helpers::e($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Orientação padrão
                    <?php $ori = (string) $v('certificados_orientacao_padrao', 'paisagem'); ?>
                    <select name="certificados_orientacao_padrao">
                        <option value="paisagem" <?php echo $ori === 'paisagem' ? 'selected' : ''; ?>>Paisagem</option>
                        <option value="retrato" <?php echo $ori === 'retrato' ? 'selected' : ''; ?>>Retrato</option>
                    </select>
                </label>

                <label>
                    Tamanho do papel
                    <?php $papel = (string) $v('certificados_tamanho_papel_padrao', 'A4'); ?>
                    <select name="certificados_tamanho_papel_padrao">
                        <option value="A4" <?php echo $papel === 'A4' ? 'selected' : ''; ?>>A4</option>
                        <option value="Carta" <?php echo $papel === 'Carta' ? 'selected' : ''; ?>>Carta</option>
                    </select>
                </label>

                <label>Margem superior <input type="number" step="0.01" min="0" name="certificados_margem_top_padrao" value="<?php echo Helpers::e($v('certificados_margem_top_padrao')); ?>"></label>
                <label>Margem inferior <input type="number" step="0.01" min="0" name="certificados_margem_bottom_padrao" value="<?php echo Helpers::e($v('certificados_margem_bottom_padrao')); ?>"></label>
                <label>Margem esquerda <input type="number" step="0.01" min="0" name="certificados_margem_left_padrao" value="<?php echo Helpers::e($v('certificados_margem_left_padrao')); ?>"></label>
                <label>Margem direita <input type="number" step="0.01" min="0" name="certificados_margem_right_padrao" value="<?php echo Helpers::e($v('certificados_margem_right_padrao')); ?>"></label>

                <label class="checkbox"><input type="checkbox" name="certificados_usar_imagem_fundo" value="1" <?php echo $checked('certificados_usar_imagem_fundo', 0); ?>> Usar imagem de fundo</label>
                <label style="grid-column:1/-1;">Imagem de fundo padrão <input type="text" name="certificados_imagem_fundo_padrao" value="<?php echo Helpers::e($v('certificados_imagem_fundo_padrao')); ?>"></label>

                <label class="checkbox"><input type="checkbox" name="certificados_usar_logo_institucional" value="1" <?php echo $checked('certificados_usar_logo_institucional', 1); ?>> Usar logo institucional</label>
                <label style="grid-column:1/-1;">Logo padrão (opcional) <input type="text" name="certificados_logo_padrao" value="<?php echo Helpers::e($v('certificados_logo_padrao')); ?>"></label>

                <label class="checkbox"><input type="checkbox" name="certificados_qrcode_habilitado" value="1" <?php echo $checked('certificados_qrcode_habilitado', 1); ?>> Usar QR Code</label>
                <label>
                    Posição padrão do QR Code
                    <?php $pos = (string) $v('certificados_qrcode_posicao_padrao', 'inferior_direita'); ?>
                    <select name="certificados_qrcode_posicao_padrao">
                        <option value="inferior_direita" <?php echo $pos === 'inferior_direita' ? 'selected' : ''; ?>>Inferior direita</option>
                        <option value="inferior_esquerda" <?php echo $pos === 'inferior_esquerda' ? 'selected' : ''; ?>>Inferior esquerda</option>
                        <option value="topo_direita" <?php echo $pos === 'topo_direita' ? 'selected' : ''; ?>>Topo direita</option>
                        <option value="topo_esquerda" <?php echo $pos === 'topo_esquerda' ? 'selected' : ''; ?>>Topo esquerda</option>
                    </select>
                </label>

                <label style="grid-column:1/-1;">Observações de layout <textarea name="certificados_observacoes_layout" rows="3"><?php echo Helpers::e($v('certificados_observacoes_layout')); ?></textarea></label>
            </div>
        </section>

        <section class="status-card" style="margin-top:12px;">
            <h2 style="margin-top:0;">Assinaturas</h2>
            <div class="form-grid">
                <label class="checkbox"><input type="checkbox" name="certificados_assinatura_1_exibir" value="1" <?php echo $checked('certificados_assinatura_1_exibir', 0); ?>> Exibir assinatura 1</label>
                <label>Nome assinatura 1 <input type="text" name="certificados_assinatura_1_nome" value="<?php echo Helpers::e($v('certificados_assinatura_1_nome')); ?>"></label>
                <label>Cargo assinatura 1 <input type="text" name="certificados_assinatura_1_cargo" value="<?php echo Helpers::e($v('certificados_assinatura_1_cargo')); ?>"></label>
                <label style="grid-column:1/-1;">Imagem assinatura 1 <input type="text" name="certificados_assinatura_1_imagem" value="<?php echo Helpers::e($v('certificados_assinatura_1_imagem')); ?>"></label>

                <label class="checkbox"><input type="checkbox" name="certificados_assinatura_2_exibir" value="1" <?php echo $checked('certificados_assinatura_2_exibir', 0); ?>> Exibir assinatura 2</label>
                <label>Nome assinatura 2 <input type="text" name="certificados_assinatura_2_nome" value="<?php echo Helpers::e($v('certificados_assinatura_2_nome')); ?>"></label>
                <label>Cargo assinatura 2 <input type="text" name="certificados_assinatura_2_cargo" value="<?php echo Helpers::e($v('certificados_assinatura_2_cargo')); ?>"></label>
                <label style="grid-column:1/-1;">Imagem assinatura 2 <input type="text" name="certificados_assinatura_2_imagem" value="<?php echo Helpers::e($v('certificados_assinatura_2_imagem')); ?>"></label>

                <label class="checkbox"><input type="checkbox" name="certificados_assinatura_3_exibir" value="1" <?php echo $checked('certificados_assinatura_3_exibir', 0); ?>> Exibir assinatura 3</label>
                <label>Nome assinatura 3 <input type="text" name="certificados_assinatura_3_nome" value="<?php echo Helpers::e($v('certificados_assinatura_3_nome')); ?>"></label>
                <label>Cargo assinatura 3 <input type="text" name="certificados_assinatura_3_cargo" value="<?php echo Helpers::e($v('certificados_assinatura_3_cargo')); ?>"></label>
                <label style="grid-column:1/-1;">Imagem assinatura 3 <input type="text" name="certificados_assinatura_3_imagem" value="<?php echo Helpers::e($v('certificados_assinatura_3_imagem')); ?>"></label>

                <label class="checkbox"><input type="checkbox" name="certificados_permitir_assinatura_professor" value="1" <?php echo $checked('certificados_permitir_assinatura_professor', 0); ?>> Permitir assinatura do professor responsável</label>
                <label class="checkbox"><input type="checkbox" name="certificados_permitir_assinatura_coordenador" value="1" <?php echo $checked('certificados_permitir_assinatura_coordenador', 0); ?>> Permitir assinatura do coordenador do curso</label>
            </div>
        </section>

        <section class="status-card" style="margin-top:12px;">
            <h2 style="margin-top:0;">Reemissão e auditoria</h2>
            <div class="form-grid">
                <label class="checkbox"><input type="checkbox" name="certificados_permitir_segunda_via" value="1" <?php echo $checked('certificados_permitir_segunda_via', 1); ?>> Permitir segunda via</label>
                <label class="checkbox"><input type="checkbox" name="certificados_registrar_numero_via" value="1" <?php echo $checked('certificados_registrar_numero_via', 1); ?>> Registrar número da via</label>
                <label class="checkbox"><input type="checkbox" name="certificados_manter_historico_reemissoes" value="1" <?php echo $checked('certificados_manter_historico_reemissoes', 1); ?>> Manter histórico de reemissões</label>
                <label class="checkbox"><input type="checkbox" name="certificados_permitir_cancelamento" value="1" <?php echo $checked('certificados_permitir_cancelamento', 1); ?>> Permitir cancelamento</label>
                <label class="checkbox"><input type="checkbox" name="certificados_exigir_motivo_cancelamento" value="1" <?php echo $checked('certificados_exigir_motivo_cancelamento', 1); ?>> Exigir motivo ao cancelar</label>
                <label class="checkbox"><input type="checkbox" name="certificados_registrar_usuario_emissor" value="1" <?php echo $checked('certificados_registrar_usuario_emissor', 1); ?>> Registrar usuário emissor</label>
                <label class="checkbox"><input type="checkbox" name="certificados_registrar_usuario_cancelou" value="1" <?php echo $checked('certificados_registrar_usuario_cancelou', 1); ?>> Registrar usuário que cancelou</label>
                <label class="checkbox"><input type="checkbox" name="certificados_registrar_ip_data_hora_emissao" value="1" <?php echo $checked('certificados_registrar_ip_data_hora_emissao', 1); ?>> Registrar IP/data/hora de emissão</label>
                <label class="checkbox"><input type="checkbox" name="certificados_regenerar_pdf_mesmo_codigo" value="1" <?php echo $checked('certificados_regenerar_pdf_mesmo_codigo', 1); ?>> Permitir regenerar PDF mantendo o mesmo código</label>
                <label class="checkbox"><input type="checkbox" name="certificados_bloquear_alteracao_apos_emitido" value="1" <?php echo $checked('certificados_bloquear_alteracao_apos_emitido', 0); ?>> Bloquear alteração de certificado já emitido</label>
            </div>
        </section>

        <section class="status-card" style="margin-top:12px;">
            <h2 style="margin-top:0;">Textos padrão</h2>
            <div class="form-grid">
                <label style="grid-column:1/-1;">Texto padrão do certificado <textarea name="certificados_texto_padrao" rows="4"><?php echo Helpers::e($v('certificados_texto_padrao')); ?></textarea></label>
                <label style="grid-column:1/-1;">Texto padrão de rodapé <textarea name="certificados_texto_rodape" rows="3"><?php echo Helpers::e($v('certificados_texto_rodape')); ?></textarea></label>
                <label style="grid-column:1/-1;">Texto padrão de validação <textarea name="certificados_texto_validacao" rows="3"><?php echo Helpers::e($v('certificados_texto_validacao')); ?></textarea></label>
                <label style="grid-column:1/-1;">Texto de observações legais <textarea name="certificados_texto_observacoes_legais" rows="3"><?php echo Helpers::e($v('certificados_texto_observacoes_legais')); ?></textarea></label>
                <label style="grid-column:1/-1;">Texto quando certificado não está disponível <textarea name="certificados_texto_indisponivel" rows="2"><?php echo Helpers::e($v('certificados_texto_indisponivel')); ?></textarea></label>
                <label style="grid-column:1/-1;">Texto quando aluno não cumpre requisitos <textarea name="certificados_texto_requisitos_nao_cumpridos" rows="2"><?php echo Helpers::e($v('certificados_texto_requisitos_nao_cumpridos')); ?></textarea></label>
            </div>
        </section>

        <section class="status-card" style="margin-top:12px;">
            <h2 style="margin-top:0;">Templates</h2>
            <div class="form-grid">
                <div class="status-card" style="grid-column:1/-1;">
                    <p style="margin:0;"><strong>Gerenciar templates:</strong> crie/edite modelos com HTML e placeholders.</p>
                    <p class="muted" style="margin:8px 0 0 0;">
                        <a class="button-link" href="/admin/certificados/templates">Abrir CRUD de templates de certificados</a>
                    </p>
                </div>

                <details style="grid-column:1/-1;">
                    <summary><strong>Lista de placeholders disponíveis</strong></summary>
                    <div class="table-wrap" style="margin-top:8px;">
                        <table class="admin-table">
                            <thead><tr><th>Grupo</th><th>Placeholder</th><th>Descrição</th></tr></thead>
                            <tbody>
                            <?php foreach (($placeholders ?? array()) as $grupo => $items): ?>
                                <?php foreach ($items as $ph => $desc): ?>
                                    <tr>
                                        <td><?php echo Helpers::e($grupo); ?></td>
                                        <td style="font-family:Consolas, monospace;"><?php echo Helpers::e($ph); ?></td>
                                        <td><?php echo Helpers::e($desc); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </section>

        <?php
        $cancel_url = '/admin/configuracoes-globais/certificados';
        $show_save_and_new = false;
        $show_save_and_exit = false;
        $show_save_as_copy = false;
        require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
        ?>
    </form>
</div>


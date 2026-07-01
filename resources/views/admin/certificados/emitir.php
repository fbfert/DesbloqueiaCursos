<?php use App\Core\Helpers; ?>

<div class="admin-page">
<?php $configCertificados = isset($configCertificados) && is_array($configCertificados) ? $configCertificados : array(); ?>
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Emitir certificado</h1>
        <p class="admin-page__subtitle">Selecione uma inscrição apta.</p>
    </div>
</section>

<?php if (!empty($success)): ?>
    <section class="auth-message auth-message-success">
        <p><?php echo Helpers::e($success); ?></p>
    </section>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <section class="auth-message auth-message-error">
        <?php foreach ($errors as $error): ?>
            <p><?php echo Helpers::e($error); ?></p>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if (empty($configCertificados['certificados_habilitado']) || empty($configCertificados['certificados_emissao_habilitada'])): ?>
    <section class="status-card" style="margin-bottom:12px;border-left:4px solid #b45309;background:#fff7ed;">
        <strong>Emissão desativada</strong>
        <p class="muted" style="margin:8px 0 0 0;">A emissão de certificados está desativada nas configurações globais.</p>
    </section>
<?php endif; ?>

<section class="status-card">
    <strong>Emissão</strong>
    <form method="post" action="/admin/certificados/emitir" class="form-grid">
        <label>
            Inscrição apta
            <select name="inscricao_id">
                <?php foreach ($aptos as $apto): ?>
                    <?php
                    $alunoNome = !empty($apto['aluno_nome']) ? (string) $apto['aluno_nome'] : (!empty($apto['participante_nome']) ? (string) $apto['participante_nome'] : 'Aluno não identificado');
                    $alunoEmail = !empty($apto['aluno_email']) ? (string) $apto['aluno_email'] : '';
                    $cursoNome = !empty($apto['curso_nome']) ? (string) $apto['curso_nome'] : '';
                    $turmaNome = !empty($apto['turma_nome']) ? (string) $apto['turma_nome'] : '';
                    $pedidoCodigo = !empty($apto['pedido_codigo']) ? (string) $apto['pedido_codigo'] : '';

                    $label = $alunoNome;
                    if ($alunoEmail !== '') {
                        $label .= ' <' . $alunoEmail . '>';
                    }
                    $label .= ' — ' . $cursoNome;
                    if ($turmaNome !== '') {
                        $label .= ' — ' . $turmaNome;
                    }
                    if ($pedidoCodigo !== '') {
                        $label .= ' (' . $pedidoCodigo . ')';
                    }
                    ?>
                    <option value="<?php echo (int) $apto['id']; ?>" <?php echo !empty($selectedInscricaoId) && (int) $selectedInscricaoId === (int) $apto['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Template
            <select name="template_id">
                <?php foreach ($templates as $template): ?>
                    <option value="<?php echo (int) $template['id']; ?>" <?php echo !empty($template['padrao']) ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($template['nome']); ?><?php echo !empty($template['padrao']) ? ' - padrão' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="checkbox">
            <input type="checkbox" name="manter_codigo" value="1" checked>
            Manter código em reemissão
        </label>

        <div class="full cta-group">
            <button type="submit" class="button-link button-link--primary" <?php echo (empty($configCertificados['certificados_habilitado']) || empty($configCertificados['certificados_emissao_habilitada'])) ? 'disabled' : ''; ?>>Emitir</button>
            <a class="button-link button-link--ghost" href="/admin/certificados">Cancelar</a>
        </div>
    </form>
</section>
</div>


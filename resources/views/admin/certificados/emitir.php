<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Emitir certificado</h1>
    <p>Selecione uma inscricao apta.</p>
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

<form method="post" action="/admin/certificados/emitir" class="form-grid">
    <label>
        Inscricao apta
        <select name="inscricao_id">
            <?php foreach ($aptos as $apto): ?>
                <option value="<?php echo (int) $apto['id']; ?>" <?php echo (int) $selectedInscricaoId === (int) $apto['id'] ? 'selected' : ''; ?>>
                    <?php echo Helpers::e($apto['curso_nome'] . ' - ' . $apto['participante_nome'] . ' (' . $apto['pedido_codigo'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        Template
        <select name="template_id">
            <?php foreach ($templates as $template): ?>
                <option value="<?php echo (int) $template['id']; ?>" <?php echo !empty($template['padrao']) ? 'selected' : ''; ?>>
                    <?php echo Helpers::e($template['nome']); ?><?php echo !empty($template['padrao']) ? ' - padrao' : ''; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="checkbox">
        <input type="checkbox" name="manter_codigo" value="1" checked>
        Manter codigo em reemissao
    </label>

    <button type="submit">Emitir</button>
</form>

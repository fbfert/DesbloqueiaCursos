<?php use App\Core\Helpers; ?>

<div class="admin-page">
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

<section class="status-card">
    <strong>Emissão</strong>
    <form method="post" action="/admin/certificados/emitir" class="form-grid">
        <label>
            Inscrição apta
            <select name="inscricao_id">
                <?php foreach ($aptos as $apto): ?>
                    <option value="<?php echo (int) $apto['id']; ?>" <?php echo (int) $selectedInscriçãoId === (int) $apto['id'] ? 'selected' : ''; ?>>
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
            <button type="submit" class="button-link button-link--primary">Emitir</button>
            <a class="button-link button-link--ghost" href="/admin/certificados">Cancelar</a>
        </div>
    </form>
</section>
</div>


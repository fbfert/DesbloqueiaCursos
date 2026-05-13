<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Configurações de certificados</h1>
        <p class="admin-page__subtitle">Prefixo, texto e padrões de validação pública.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="post" action="/admin/configuracoes-globais/certificados" class="form-grid">
        <label>
            Prefixo do certificado
            <input type="text" name="prefixo_certificado" value="<?php echo htmlspecialchars((string) (isset($configuracao['prefixo_certificado']) ? $configuracao['prefixo_certificado'] : 'PRC'), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Título padrão
            <input type="text" name="titulo_padrao" value="<?php echo htmlspecialchars((string) (isset($configuracao['titulo_padrao']) ? $configuracao['titulo_padrao'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Texto de validação pública
            <textarea name="texto_validacao_publica" rows="5"><?php echo htmlspecialchars((string) (isset($configuracao['texto_validacao_publica']) ? $configuracao['texto_validacao_publica'] : ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>
        <?php $cancelUrl = '/admin/configuracoes-globais/certificados'; ?>
        <?php $showSaveAndNew = false; ?>
        <?php $showSaveAndExit = false; ?>
        <?php $showSaveAsCopy = false; ?>
        <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
    </form>
</section>
</div>


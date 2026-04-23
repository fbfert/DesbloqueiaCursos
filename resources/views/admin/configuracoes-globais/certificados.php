<section class="hero">
    <h1>Configuracoes de certificados</h1>
    <p>Prefixo, texto e padroes de validacao publica.</p>
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
            Titulo padrao
            <input type="text" name="titulo_padrao" value="<?php echo htmlspecialchars((string) (isset($configuracao['titulo_padrao']) ? $configuracao['titulo_padrao'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Texto de validacao publica
            <textarea name="texto_validacao_publica" rows="5"><?php echo htmlspecialchars((string) (isset($configuracao['texto_validacao_publica']) ? $configuracao['texto_validacao_publica'] : ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>
        <button type="submit">Salvar certificados</button>
    </form>
</section>

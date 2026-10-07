<section class="hero">
    <h1>Erro interno</h1>
    <p>Não foi possível concluir a solicitação.</p>

    <?php if (!empty($debug)): ?>
        <pre><?php echo htmlspecialchars($message . ' em ' . $file . ':' . $line, ENT_QUOTES, 'UTF-8'); ?></pre>
    <?php endif; ?>
</section>


<section class="hero">
    <h1>Polo Rainbow</h1>
    <p>Bootstrap PHP MVC preparado para evoluir para um portal de cursos e eventos.</p>
</section>

<?php if (!empty($success)): ?>
<section class="status-card">
    <strong>Estado</strong>
    <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
</section>
<?php endif; ?>

<?php if (!empty($usuarioNome)): ?>
<section class="status-card">
    <strong>Conta ativa</strong>
    <span><?php echo htmlspecialchars($usuarioNome, ENT_QUOTES, 'UTF-8'); ?></span>
    <form method="post" action="/logout" style="margin-top: 12px;">
        <button type="submit">Sair</button>
    </form>
</section>
<?php endif; ?>

<section class="status-grid" aria-label="Status da estrutura">
    <article class="status-card">
        <strong>MVC</strong>
        <span>Controllers, Services, Models e Views separados.</span>
    </article>
    <article class="status-card">
        <strong>API futura</strong>
        <span>Rotas web e API separadas desde o inicio.</span>
    </article>
    <article class="status-card">
        <strong>Storage privado</strong>
        <span>Arquivos sensiveis devem ficar fora da pasta publica.</span>
    </article>
</section>

<section class="hero">
    <h1>Polo Rainbow</h1>
    <p>Bootstrap PHP MVC preparado para evoluir para um portal de cursos e eventos.</p>
    <div class="cta-group">
        <a class="button-link" href="/cursos">Ver cursos</a>
        <a class="button-link button-link--ghost" href="/meus-cursos">Meus Cursos</a>
    </div>
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

<section class="page-header">
    <h2>Cursos ativos</h2>
    <p>Lista inicial para iniciar o fluxo de inscrição.</p>
</section>

<section class="card-grid">
    <?php if (empty($cursos)): ?>
        <article class="status-card">
            <strong>Sem cursos publicos</strong>
            <span>O catalogo ainda esta vazio.</span>
        </article>
    <?php else: ?>
        <?php foreach (array_slice($cursos, 0, 3) as $curso): ?>
            <article class="course-card">
                <div class="course-card__media">
                    <strong><?php echo htmlspecialchars($curso['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?php echo htmlspecialchars($curso['categoria_nome'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="course-card__body">
                    <p><?php echo htmlspecialchars($curso['descricao_curta'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="cta-group">
                        <a class="button-link" href="/cursos/detalhe?curso_id=<?php echo (int) $curso['id']; ?>">Abrir</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

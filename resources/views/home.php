<?php use App\Core\Helpers; ?>

<section class="hero hero--public">
    <div class="hero__content">
        <span class="eyebrow">Portal de cursos</span>
        <h1>Formações com turmas públicas, inscrição guiada e acesso separado por perfil.</h1>
        <p>O portal público consome o catálogo do backoffice sem expor dados administrativos. Aqui entram apenas cursos ativos, turmas abertas e a porta de entrada da inscrição.</p>
        <div class="cta-group">
            <a class="button-link" href="/cursos">Explorar cursos</a>
            <a class="button-link button-link--ghost" href="/como-funciona">Como funciona</a>
        </div>
    </div>
    <div class="hero__panel">
        <div class="hero-stat">
            <strong><?php echo count($cursos); ?></strong>
            <span>Cursos em destaque agora</span>
        </div>
        <div class="hero-stat">
            <strong>100%</strong>
            <span>Fluxo público separado do admin e da área do professor</span>
        </div>
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
    <div style="margin-top: 12px;">
        <a class="button-link button-link--ghost" href="/logout">Sair</a>
    </div>
</section>
<?php endif; ?>

<section class="status-grid" aria-label="Status da estrutura">
    <article class="status-card">
        <strong>Catálogo público</strong>
        <span>Lista apenas cursos ativos e publicáveis, sem depender de permissão administrativa.</span>
    </article>
    <article class="status-card">
        <strong>Detalhe seguro</strong>
        <span>O detalhe do curso exibe somente professor responsável e turmas abertas para inscrição.</span>
    </article>
    <article class="status-card">
        <strong>Inscrição inicial</strong>
        <span>O frontend encaminha a inscrição apenas para turma aberta e vinculada ao curso correto.</span>
    </article>
</section>

<section class="page-header">
    <h2>Destaques</h2>
</section>

<section class="card-grid">
    <?php if (empty($cursos)): ?>
        <article class="status-card">
            <strong>Sem cursos públicos</strong>
            <span>O catálogo ainda está vazio.</span>
        </article>
    <?php else: ?>
        <?php foreach ($cursos as $curso): ?>
            <article class="course-card">
                <?php if (!empty($curso['thumbnail'])): ?>
                    <div class="course-card__image">
                        <a href="/cursos/detalhe?curso_id=<?php echo (int) $curso['id']; ?>">
                            <img src="<?php echo Helpers::e($curso['thumbnail']); ?>" alt="<?php echo Helpers::e($curso['nome']); ?>">
                        </a>
                    </div>
                <?php endif; ?>
                <div class="course-card__media">
                    <strong><?php echo Helpers::e($curso['nome']); ?></strong>
                    <span><?php echo Helpers::e($curso['categoria_nome'] ?: 'Sem categoria'); ?></span>
                </div>
                <div class="course-card__body">
                    <div class="pill-row">
                        <span class="pill"><?php echo Helpers::e($curso['tipo']); ?></span>
                        <span class="pill"><?php echo Helpers::e($curso['modalidade']); ?></span>
                        <span class="pill"><?php echo (int) $curso['total_turmas_abertas']; ?> turma(s) aberta(s)</span>
                    </div>
                    <p><?php echo Helpers::e($curso['descricao_curta']); ?></p>
                    <div class="cta-group">
                        <a class="button-link button-link--ghost" href="/cursos/detalhe?curso_id=<?php echo (int) $curso['id']; ?>">Ver detalhes</a>
                        <?php if (!empty($curso['turmas_abertas'][0]['id'])): ?>
                            <a class="button-link" href="/inscricao?curso_id=<?php echo (int) $curso['id']; ?>&turma_id=<?php echo (int) $curso['turmas_abertas'][0]['id']; ?>">Inscreva-se já</a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="cta-group" style="margin-top: 20px;">
    <a class="button-link" href="https://polorainbow.com.br/cursos">Ver todos os cursos</a>
</section>


<?php use App\Core\Helpers; ?>

<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title"><?php echo Helpers::e($pagina['titulo']); ?></h1>
            <p class="admin-page__subtitle">Visualização e manutenção da página.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link" href="/admin/paginas/editar?pagina_id=<?php echo (int) $pagina['id']; ?>">Editar</a>
            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($pagina['rota']); ?>" target="_blank" rel="noopener">Abrir página</a>
        </div>
    </header>

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
        <dl class="summary-list">
            <dt>Slug</dt><dd><?php echo Helpers::e($pagina['slug']); ?></dd>
            <dt>Rota</dt><dd><?php echo Helpers::e($pagina['rota']); ?></dd>
            <dt>Status</dt><dd><?php echo Helpers::e($pagina['status']); ?></dd>
            <dt>Ordem</dt><dd><?php echo (int) $pagina['ordem']; ?></dd>
            <dt>Resumo</dt><dd><?php echo Helpers::e((string) $pagina['resumo']); ?></dd>
        </dl>
    </section>

    <section class="status-card" style="margin-top:12px;">
        <h2>Conteúdo HTML</h2>
        <textarea rows="16" readonly style="width:100%; font-family:Consolas, monospace;"><?php echo Helpers::e((string) $pagina['conteudo_html']); ?></textarea>
    </section>

    <section class="status-card" style="margin-top:12px;">
        <h2>Excluir página</h2>
        <form method="post" action="/admin/paginas/excluir" class="admin-form">
            <input type="hidden" name="id" value="<?php echo (int) $pagina['id']; ?>">
            <label>
                Justificativa obrigatória
                <textarea name="justificativa" rows="3" required></textarea>
            </label>
            <button type="submit">Excluir e enviar para lixeira</button>
        </form>
    </section>
</section>

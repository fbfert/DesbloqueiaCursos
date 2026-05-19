<?php use App\Core\Helpers; ?>
<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Pré-visualização do template</h1>
            <p class="admin-page__subtitle"><?php echo Helpers::e($template['nome'] ?? ''); ?></p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/certificados/templates">Voltar</a>
            <?php if (!empty($template['id'])): ?>
                <a class="button-link" href="/admin/certificados/templates/editar?template_id=<?php echo (int) $template['id']; ?>">Editar</a>
            <?php endif; ?>
        </div>
    </header>

    <section class="status-card">
        <p class="muted" style="margin-top:0;">
            Pré-visualização com dados simulados. Placeholders sem valor viram texto vazio.
        </p>
        <div class="status-card" style="margin-top:12px;background:#fff;">
            <style>
                <?php echo (string) ($rendered_css ?? ''); ?>
            </style>
            <div style="padding:16px;background:<?php echo Helpers::e($template['cor_fundo'] ?? '#ffffff'); ?>;color:<?php echo Helpers::e($template['cor_texto'] ?? '#111827'); ?>;">
                <?php echo (string) ($rendered_html ?? ''); ?>
            </div>
        </div>
    </section>
</section>


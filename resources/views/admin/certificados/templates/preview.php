<?php use App\Core\Helpers; ?>
<?php
$logoPreview = trim((string) ($preview_logo_url ?? ($template['logo'] ?? '')));
$backgroundPreview = trim((string) ($preview_background_url ?? ($template['imagem_fundo'] ?? '')));
?>
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
        <p class="muted" style="margin-top:0;">
            <?php if ($logoPreview !== ''): ?>
                Este template usa logo própria.
            <?php else: ?>
                Este template herda a logo específica dos certificados ou a logo institucional/global.
            <?php endif; ?>
        </p>
        <p class="muted" style="margin-top:0;">
            <?php if ($backgroundPreview !== ''): ?>
                Este template também usa imagem de fundo própria.
            <?php else: ?>
                Sem imagem de fundo própria definida.
            <?php endif; ?>
        </p>
        <p class="muted" style="margin-top:0;">
            O PDF final terá duas páginas: a primeira institucional e a segunda definida pelo HTML editável do template, com conteúdo programático.
        </p>
        <div class="status-card" style="margin-top:12px;background:#fff;">
            <style>
                <?php echo (string) ($rendered_css ?? ''); ?>
                .certificado-logo-institucional img[src^="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/L9kAAAAASUVORK5CYII="]{
                    width:1px !important;
                    height:1px !important;
                    max-width:1px !important;
                    max-height:1px !important;
                    opacity:0 !important;
                }
            </style>
            <div style="<?php echo Helpers::e(isset($preview_wrapper_style) ? $preview_wrapper_style : 'padding:16px;background:#ffffff;color:#111827;'); ?>">
                <?php echo (string) ($rendered_html ?? ''); ?>
            </div>
        </div>
        <?php if (!empty($rendered_second_page_html)): ?>
            <div class="status-card" style="margin-top:12px;background:#fff;">
                <div style="<?php echo Helpers::e(isset($preview_wrapper_style) ? $preview_wrapper_style : 'padding:16px;background:#ffffff;color:#111827;'); ?> page-break-before:always;">
                    <?php echo (string) $rendered_second_page_html; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</section>

<?php use App\Core\Helpers; ?>

<div class="front-section-stack">
    <section class="page-header front-section">
        <h1><?php echo Helpers::e($pagina['titulo']); ?></h1>
        <?php if (!empty($pagina['resumo'])): ?>
            <p><?php echo Helpers::e($pagina['resumo']); ?></p>
        <?php endif; ?>
    </section>

    <section class="status-card front-card front-section">
        <?php echo (string) $pagina['conteudo_html']; ?>
    </section>
</div>

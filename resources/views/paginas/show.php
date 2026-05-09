<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1><?php echo Helpers::e($pagina['titulo']); ?></h1>
    <?php if (!empty($pagina['resumo'])): ?>
        <p><?php echo Helpers::e($pagina['resumo']); ?></p>
    <?php endif; ?>
</section>

<section class="status-card">
    <?php echo (string) $pagina['conteudo_html']; ?>
</section>

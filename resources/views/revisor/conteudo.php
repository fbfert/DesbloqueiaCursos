<?php
use App\Core\Helpers;
use App\Support\HtmlEmbedRenderer;

$retorno = '/revisor/conteudo?item_id=' . (int) $item['id'];
?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo Helpers::e($item['titulo']); ?></h1>
        <p class="admin-page__subtitle">
            <?php echo Helpers::e($curso['nome']); ?> · <?php echo Helpers::e($item['modulo_titulo']); ?>
        </p>
    </div>
    <div class="admin-page__actions">
        <a class="button-link button-link--ghost" href="/revisor/curso?curso_id=<?php echo (int) $curso['id']; ?>">Voltar ao curso</a>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Conteúdo como o aluno vê</strong>
    <?php if ($corpo === null || $corpo === ''): ?>
        <p class="muted">Este conteúdo ainda não foi produzido, ou é de um tipo que não se lê por aqui (<?php echo Helpers::e($item['tipo']); ?>).</p>
    <?php elseif ($item['tipo'] === 'html'): ?>
        <?php
        // Mesmo iframe do aluno: sandbox sem allow-same-origin. O revisor ve
        // exatamente o que sera publicado, e o isolamento continua o mesmo.
        ?>
        <div class="conteudo-html-embed">
            <iframe
                class="conteudo-html-embed__frame"
                id="revisao-html-frame-<?php echo (int) $item['id']; ?>"
                sandbox="allow-scripts allow-popups"
                title="<?php echo Helpers::e($item['titulo']); ?>"
                loading="lazy"
                srcdoc="<?php echo Helpers::e(HtmlEmbedRenderer::wrap($corpo, 'revisao-html-frame-' . (int) $item['id'])); ?>"
            ></iframe>
        </div>
    <?php else: ?>
        <div class="conteudo-texto"><?php echo Helpers::renderSafeHtml($corpo); ?></div>
    <?php endif; ?>
</section>

<?php require BASE_PATH . '/resources/views/revisor/_painel_comentarios.php'; ?>
</div>

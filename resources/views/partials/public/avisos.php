<?php use App\Core\Helpers; ?>
<?php
$avisos = isset($avisos) && is_array($avisos) ? $avisos : array();
if (empty($avisos)) {
    return;
}
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
?>

<section class="status-card avisos-feed" id="avisos">
    <header class="avisos-feed__header">
        <h2>Avisos</h2>
        <p>Mensagens importantes para sua conta e seus cursos.</p>
    </header>
    <div class="avisos-list">
        <?php foreach ($avisos as $aviso): ?>
            <article class="aviso-card<?php echo !empty($aviso['destaque']) ? ' aviso-card--destaque' : ''; ?>">
                <div class="aviso-card__top">
                    <div>
                        <strong><?php echo Helpers::e(!empty($aviso['titulo']) ? $aviso['titulo'] : 'Aviso'); ?></strong>
                        <?php if (!empty($aviso['mostrar_inicio']) || !empty($aviso['mostrar_fim'])): ?>
                            <small>
                                <?php if (!empty($aviso['mostrar_inicio'])): ?>
                                    Início <?php echo Helpers::e(date('d/m/Y H:i', strtotime((string) $aviso['mostrar_inicio']))); ?>
                                <?php endif; ?>
                                <?php if (!empty($aviso['mostrar_fim'])): ?>
                                    <?php echo !empty($aviso['mostrar_inicio']) ? ' · ' : ''; ?>
                                    Fim <?php echo Helpers::e(date('d/m/Y H:i', strtotime((string) $aviso['mostrar_fim']))); ?>
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($aviso['destaque'])): ?>
                        <span class="pill pill--alert">Destaque</span>
                    <?php endif; ?>
                </div>
                <p class="aviso-card__message"><?php echo nl2br(Helpers::e($aviso['mensagem'])); ?></p>
                <div class="aviso-card__actions">
                    <?php if (!empty($aviso['link_url'])): ?>
                        <a class="button-link" href="<?php echo Helpers::e($aviso['link_url']); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo Helpers::e(!empty($aviso['link_rotulo']) ? $aviso['link_rotulo'] : 'Abrir aviso'); ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($aviso['permitir_ocultar'])): ?>
                        <form method="post" action="/avisos/ocultar" class="admin-form" style="display:inline;">
                            <?php echo $csrfField; ?>
                            <input type="hidden" name="aviso_id" value="<?php echo (int) $aviso['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo Helpers::e($requestUri); ?>">
                            <button type="submit">Ocultar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

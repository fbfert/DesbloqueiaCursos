<?php use App\Core\Helpers; ?>
<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Descadastro de lembretes de recuperação</h1>
            <p class="admin-page__subtitle">Você pode parar de receber lembretes de pedidos incompletos quando quiser.</p>
        </div>
    </header>

    <section class="status-card">
        <?php if (!empty($ok)): ?>
            <p>Pronto. Você não receberá mais lembretes de recuperação de pedidos incompletos.</p>
        <?php else: ?>
            <p>Não foi possível confirmar este descadastro. O link pode ter expirado ou ser inválido.</p>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <p class="muted"><?php echo Helpers::e($message); ?></p>
        <?php endif; ?>

        <div class="admin-page__actions" style="margin-top:16px;">
            <a class="button-link button-link--primary" href="/">Voltar ao site</a>
        </div>
    </section>
</section>

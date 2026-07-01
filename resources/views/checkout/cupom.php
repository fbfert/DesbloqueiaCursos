<?php use App\Core\Helpers; ?>

<div class="front-section-stack">
    <section class="page-header front-section">
        <h1>Aplicar cupom</h1>
        <p>Pedido <?php echo Helpers::e($pedido['codigo']); ?></p>
    </section>

    <?php if (empty($loggedIn)): ?>
        <section class="notice front-card front-section">
            <strong>Entre para aplicar o cupom</strong>
            <p>O cupom precisa ser validado em uma conta autenticada.</p>
            <div class="cta-group">
                <a class="button-link" href="/login">Entrar</a>
                <a class="button-link button-link--ghost" href="/cadastro">Criar conta</a>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($loggedIn)): ?>
        <form class="admin-form checkout-form front-card" method="post" action="/checkout/cupom">
            <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
            <label>
                Código do cupom
                <input type="text" name="cupom_codigo" placeholder="Digite o código">
            </label>
            <button type="submit">Aplicar</button>
        </form>
    <?php endif; ?>
</div>

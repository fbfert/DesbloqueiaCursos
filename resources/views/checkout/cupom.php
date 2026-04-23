<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Aplicar cupom</h1>
    <p>Pedido <?php echo Helpers::e($pedido['codigo']); ?></p>
</section>

<?php if (empty($loggedIn)): ?>
    <section class="notice">
        <strong>Entre para aplicar o cupom</strong>
        <p>O cupom precisa ser validado em uma conta autenticada.</p>
        <div class="cta-group">
            <a class="button-link" href="/login">Entrar</a>
            <a class="button-link button-link--ghost" href="/cadastro">Criar conta</a>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($loggedIn)): ?>
    <form class="admin-form checkout-form" method="post" action="/checkout/cupom">
        <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
        <label>
            Codigo do cupom
            <input type="text" name="cupom_codigo" placeholder="Digite o codigo">
        </label>
        <button type="submit">Aplicar</button>
    </form>
<?php endif; ?>

<?php use App\Core\Helpers; ?>

<section class="page-header">
    <h1>Inscrição</h1>
    <p><?php echo Helpers::e($curso['nome']); ?></p>
</section>

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

<?php if (empty($loggedIn)): ?>
    <section class="notice">
        <strong>Entre para continuar</strong>
        <p>Você pode revisar o curso, mas precisa entrar na conta para iniciar a compra.</p>
        <div class="cta-group">
            <a class="button-link" href="/login">Entrar</a>
            <a class="button-link button-link--ghost" href="/cadastro">Criar conta</a>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($loggedIn)): ?>
    <section class="checkout-panel">
        <h2>Resumo da inscricao</h2>
        <dl class="summary-list">
            <dt>Curso</dt>
            <dd><?php echo Helpers::e($curso['nome']); ?></dd>
            <?php if (!empty($curso['turma_selecionada']['nome'])): ?>
                <dt>Turma</dt>
                <dd><?php echo Helpers::e($curso['turma_selecionada']['nome']); ?></dd>
                <dt>Status</dt>
                <dd><?php echo Helpers::e($curso['turma_selecionada']['status']); ?></dd>
            <?php endif; ?>
            <dt>Valor</dt>
            <dd>R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></dd>
        </dl>
    </section>

    <form class="admin-form checkout-form" method="post" action="/inscricao">
        <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
        <input type="hidden" name="turma_id" value="<?php echo !empty($curso['turma_selecionada']['id']) ? (int) $curso['turma_selecionada']['id'] : ''; ?>">

        <section class="checkout-panel">
            <h2>Dados do pagamento</h2>
            <label>
                Nome do pagador
                <input type="text" name="pagador_nome" value="<?php echo Helpers::e($usuarioNome); ?>">
            </label>
            <label>
                CPF do pagador
                <input type="text" name="pagador_cpf" placeholder="000.000.000-00">
            </label>
            <label>
                E-mail do pagador
                <input type="email" name="pagador_email" value="<?php echo Helpers::e(isset($usuarioEmail) ? $usuarioEmail : ''); ?>">
            </label>
            <label>
                Telefone
                <input type="text" name="pagador_telefone">
            </label>
            <label>
                Cidade
                <input type="text" name="pagador_cidade">
            </label>
            <label>
                Estado
                <input type="text" name="pagador_estado" maxlength="2">
            </label>
            <label>
                Tipo do pedido
                <select name="tipo_pedido">
                    <option value="propria">Compra propria</option>
                    <option value="terceiros">Compra para terceiros</option>
                    <option value="lote">Compra em lote</option>
                </select>
            </label>
            <label>
                Quantidade de vagas
                <input type="number" name="quantidade" min="1" value="1">
            </label>
            <label>
                Observações públicas
                <textarea name="observacoes_publicas" rows="4"></textarea>
            </label>
        </section>

        <button type="submit">Avancar para participantes</button>
    </form>
<?php endif; ?>


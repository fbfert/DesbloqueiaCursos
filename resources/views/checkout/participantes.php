<?php use App\Core\Helpers; ?>

<div class="front-section-stack">
    <section class="page-header front-section">
        <h1>Participantes</h1>
        <p><?php echo Helpers::e($pedido['codigo']); ?></p>
    </section>

    <?php if (!empty($errors)): ?>
        <section class="auth-message auth-message-error front-section">
            <?php foreach ($errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <div class="front-card-section front-section">
    <section class="checkout-panel front-card">
        <h2>Resumo do pedido</h2>
        <dl class="summary-list">
            <dt>Pagador</dt>
            <dd><?php echo Helpers::e($pedido['pagador_nome']); ?></dd>
            <dt>Status</dt>
            <dd><?php echo Helpers::e($pedido['status']); ?></dd>
            <dt>Total</dt>
            <dd><?php echo Helpers::e($pedido['total']); ?></dd>
        </dl>
    </section>

    <?php if (empty($loggedIn)): ?>
        <section class="notice front-card">
            <strong>Entre para concluir</strong>
            <p>O cadastro dos participantes continua disponível depois do login.</p>
            <div class="cta-group">
                <a class="button-link" href="/login">Entrar</a>
                <a class="button-link button-link--ghost" href="/cadastro">Criar conta</a>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($loggedIn)): ?>
        <form class="admin-form checkout-form front-card-list" method="post" action="/checkout/participantes?pedido_id=<?php echo (int) $pedido['id']; ?>">
            <?php for ($i = 0; $i < $quantidade; $i++): ?>
                <?php
                $nomeParticipante = $i === 0 ? $pedido['pagador_nome'] : '';
                $cpfParticipante = $i === 0 && !empty($pedido['pagador_cpf']) ? (string) $pedido['pagador_cpf'] : '';
                $emailParticipante = $i === 0 ? $pedido['pagador_email'] : '';
                $telefoneParticipante = $i === 0 && !empty($pedido['pagador_telefone']) ? (string) $pedido['pagador_telefone'] : '';

                if ($i === 0 && !empty($participantePrefill)) {
                    $nomeParticipante = isset($participantePrefill['nome']) ? $participantePrefill['nome'] : $nomeParticipante;
                    if (isset($participantePrefill['cpf']) && trim((string) $participantePrefill['cpf']) !== '') {
                        $cpfParticipante = $participantePrefill['cpf'];
                    }
                    $emailParticipante = isset($participantePrefill['email']) ? $participantePrefill['email'] : $emailParticipante;
                    if (isset($participantePrefill['telefone']) && trim((string) $participantePrefill['telefone']) !== '') {
                        $telefoneParticipante = $participantePrefill['telefone'];
                    }
                }
                ?>
                <section class="checkout-panel front-card">
                    <h2>Participante <?php echo $i + 1; ?></h2>
                    <input type="hidden" name="participantes[<?php echo $i; ?>][pedido_item_id]" value="<?php echo !empty($pedido['itens'][0]['id']) ? (int) $pedido['itens'][0]['id'] : ''; ?>">
                    <label>
                        Nome
                        <input type="text" name="participantes[<?php echo $i; ?>][nome]" value="<?php echo Helpers::e($nomeParticipante); ?>">
                    </label>
                    <label>
                        CPF
                        <input type="text" id="<?php echo $i === 0 ? 'participante-1-cpf' : ''; ?>" name="participantes[<?php echo $i; ?>][cpf]" value="<?php echo Helpers::e($cpfParticipante); ?>" <?php echo $i === 0 ? 'data-skip-old-input="1"' : ''; ?>>
                    </label>
                    <label>
                        E-mail
                        <input type="email" name="participantes[<?php echo $i; ?>][email]" value="<?php echo Helpers::e($emailParticipante); ?>">
                    </label>
                    <label>
                        Telefone
                        <input type="text" id="<?php echo $i === 0 ? 'participante-1-telefone' : ''; ?>" name="participantes[<?php echo $i; ?>][telefone]" value="<?php echo Helpers::e($telefoneParticipante); ?>" <?php echo $i === 0 ? 'data-skip-old-input="1"' : ''; ?>>
                    </label>
                </section>
            <?php endfor; ?>

            <button type="submit">Salvar participantes</button>
        </form>
    <?php endif; ?>
    </div>
</div>

<?php if (!empty($loggedIn)): ?>
    <script>
    (function () {
        try {
            var campoCpf = document.getElementById('participante-1-cpf');
            var campoTelefone = document.getElementById('participante-1-telefone');
            if (campoCpf && !campoCpf.value) {
                campoCpf.value = sessionStorage.getItem('checkout_pagador_cpf') || '';
            }
            if (campoTelefone && !campoTelefone.value) {
                campoTelefone.value = sessionStorage.getItem('checkout_pagador_telefone') || '';
            }
        } catch (e) {}
    })();
    </script>
<?php endif; ?>

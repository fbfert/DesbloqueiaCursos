<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">E-mails</h1>
            <p class="admin-page__subtitle">Acompanhe a fila, falhas e envios recentes do sistema.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/emails/modelos">Modelos de e-mail</a>
            <a class="button-link button-link--ghost" href="/admin/emails/fila">Fila e Histórico</a>
            <form method="post" action="/admin/emails/reenviar-pendentes" style="display:inline;">
                <?php echo $csrfField; ?>
                <button type="submit" class="button-link" onclick="return window.confirm('Reenviar todos os e-mails pendentes e falhos?');">Reenviar pendentes</button>
            </form>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <div class="panel-header">
            <div>
                <h2>Configuração SMTP</h2>
                <p class="muted">Ajuste do servidor de saída e remetentes padrão.</p>
            </div>
        </div>

        <form method="post" action="/admin/emails" class="form-grid">
            <?php echo $csrfField; ?>
            <label>
                Nome
                <input type="text" name="nome" value="<?php echo htmlspecialchars((string) (isset($configuracao['nome']) ? $configuracao['nome'] : 'SMTP principal'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>

            <label>
                Host SMTP
                <input type="text" name="host" value="<?php echo htmlspecialchars((string) (isset($configuracao['host']) ? $configuracao['host'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
            </label>

            <label>
                Porta
                <input type="number" name="porta" value="<?php echo htmlspecialchars((string) (isset($configuracao['port']) ? $configuracao['port'] : 587), ENT_QUOTES, 'UTF-8'); ?>">
            </label>

            <label>
                Usuário SMTP
                <input type="text" name="usuario" value="<?php echo htmlspecialchars((string) (isset($configuracao['username']) ? $configuracao['username'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
            </label>

            <label>
                Senha SMTP
                <input type="password" name="senha" value="">
            </label>

            <label>
                Criptografia
                <select name="criptografia">
                    <?php foreach (array('tls', 'ssl', 'nenhuma') as $option): ?>
                        <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (isset($configuracao['encryption']) && $configuracao['encryption'] === $option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                E-mail de origem
                <input type="email" name="from_email" value="<?php echo htmlspecialchars((string) (isset($configuracao['from_email']) ? $configuracao['from_email'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
            </label>

            <label>
                Nome de origem
                <input type="text" name="from_name" value="<?php echo htmlspecialchars((string) (isset($configuracao['from_name']) ? $configuracao['from_name'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
            </label>

            <label>
                Reply-to
                <input type="email" name="reply_to_email" value="<?php echo htmlspecialchars((string) (isset($configuracao['reply_to']) ? $configuracao['reply_to'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
            </label>

            <label class="checkbox">
                <input type="checkbox" name="ativo" value="1" <?php echo !empty($configuracao['enabled']) ? 'checked' : ''; ?>>
                SMTP ativo
            </label>

            <label class="checkbox">
                <input type="checkbox" name="fila_ativa" value="1" <?php echo !empty($configuracao['queue_processing']) ? 'checked' : ''; ?>>
                Processar fila
            </label>

            <?php
            $cancel_url = '/admin/emails';
            $show_save_as_copy = false;
            $save_label = 'Salvar configuração';
            require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
            ?>
        </form>
    </section>

    <section class="card-grid" style="margin-top:16px;">
        <?php $resumo = isset($resumo) && is_array($resumo) ? $resumo : array(); ?>
        <article class="status-card">
            <strong>Pendentes de envio</strong>
            <span style="font-size:24px;font-weight:800;"><?php echo (int) ($resumo['pendentes'] ?? 0); ?></span>
            <small class="muted">E-mails pendentes ou com falha aguardando reenvio.</small>
            <a href="/admin/emails/fila?status=pendente">Ver pendentes</a>
        </article>
        <article class="status-card">
            <strong>Falhas hoje</strong>
            <span style="font-size:24px;font-weight:800;"><?php echo (int) ($resumo['falhas_hoje'] ?? 0); ?></span>
            <small class="muted">Ocorrências registradas hoje.</small>
            <a href="/admin/emails/fila?status=falhou">Ver falhas</a>
        </article>
        <article class="status-card">
            <strong>Falhas esta semana</strong>
            <span style="font-size:24px;font-weight:800;"><?php echo (int) ($resumo['falhas_semana'] ?? 0); ?></span>
            <small class="muted">Falhas desde segunda-feira.</small>
            <a href="/admin/emails/fila?status=falhou">Ver falhas</a>
        </article>
        <article class="status-card">
            <strong>Falhas este mês</strong>
            <span style="font-size:24px;font-weight:800;"><?php echo (int) ($resumo['falhas_mes'] ?? 0); ?></span>
            <small class="muted">Falhas desde o início do mês.</small>
            <a href="/admin/emails/fila?status=falhou">Ver falhas</a>
        </article>
        <article class="status-card">
            <strong>Enviados hoje</strong>
            <span style="font-size:24px;font-weight:800;"><?php echo (int) ($resumo['enviados_hoje'] ?? 0); ?></span>
            <small class="muted">Mensagens enviadas com sucesso hoje.</small>
            <a href="/admin/emails/fila?status=enviado">Ver enviados</a>
        </article>
        <article class="status-card">
            <strong>Enviados esta semana</strong>
            <span style="font-size:24px;font-weight:800;"><?php echo (int) ($resumo['enviados_semana'] ?? 0); ?></span>
            <small class="muted">Envios desde segunda-feira.</small>
            <a href="/admin/emails/fila?status=enviado">Ver enviados</a>
        </article>
        <article class="status-card">
            <strong>Enviados este mês</strong>
            <span style="font-size:24px;font-weight:800;"><?php echo (int) ($resumo['enviados_mes'] ?? 0); ?></span>
            <small class="muted">Envios desde o início do mês.</small>
            <a href="/admin/emails/fila?status=enviado">Ver enviados</a>
        </article>
    </section>
</div>

<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">E-mails</h1>
            <p class="admin-page__subtitle">Configuração SMTP básica e histórico de envios.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/emails/modelos">Modelos de e-mail</a>
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

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Fila e histórico</h2>
        </div>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Evento</th>
                        <th>Destinatário</th>
                        <th>Assunto</th>
                        <th>Status</th>
                        <th>Tentativas</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($emails)): ?>
                        <tr>
                            <td colspan="6">Nenhum envio encontrado.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($emails as $email): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $email['evento'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $email['destinatario_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $email['assunto'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="badge badge-<?php echo htmlspecialchars((string) $email['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $email['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo (int) $email['tentativas']; ?></td>
                            <td><?php echo htmlspecialchars((string) $email['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

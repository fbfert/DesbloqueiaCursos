<section class="panel">
    <div class="panel-header">
        <div>
            <h1>Emails transacionais</h1>
            <p>Configuracao SMTP basica e historico de envios.</p>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <div><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/emails" class="form-grid">
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
            Usuario SMTP
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

        <button type="submit">Salvar configuracao</button>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Fila e historico</h2>
            <p>Ultimos envios registrados.</p>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Evento</th>
                    <th>Destinatario</th>
                    <th>Assunto</th>
                    <th>Status</th>
                    <th>Tentativas</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
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

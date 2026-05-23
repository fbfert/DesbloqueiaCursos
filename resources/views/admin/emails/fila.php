<?php
use App\Core\Helpers;

$filters = isset($filters) && is_array($filters) ? $filters : array();
$pagination = isset($pagination) && is_array($pagination) ? $pagination : array();
$page = (int) ($pagination['page'] ?? 1);
$pages = (int) ($pagination['pages'] ?? 1);
$status = isset($filters['status']) ? (string) $filters['status'] : '';
$q = isset($filters['q']) ? (string) $filters['q'] : '';
$de = isset($filters['de']) ? (string) $filters['de'] : '';
$ate = isset($filters['ate']) ? (string) $filters['ate'] : '';

$buildQuery = function (array $override = array()) use ($filters) {
    $merged = array_merge($filters, $override);
    $parts = array();
    foreach ($merged as $key => $value) {
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }
        $parts[] = urlencode($key) . '=' . urlencode($value);
    }
    return $parts ? ('?' . implode('&', $parts)) : '';
};
?>

<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Fila e histórico de e-mails</h1>
            <p class="admin-page__subtitle">Filtre e reenvie e-mails pendentes ou com falha.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/emails">Voltar</a>
            <a class="button-link button-link--ghost" href="/admin/emails/modelos">Modelos</a>
        </div>
    </header>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <form method="get" action="/admin/emails/fila" class="form-grid">
            <label>
                Status
                <select name="status">
                    <option value="">(Todos)</option>
                    <option value="pendente" <?php echo $status === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                    <option value="falhou" <?php echo $status === 'falhou' ? 'selected' : ''; ?>>Falhou</option>
                    <option value="enviado" <?php echo $status === 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                </select>
            </label>
            <label>
                Busca (destinatário/assunto/evento)
                <input type="text" name="q" value="<?php echo Helpers::e($q); ?>" placeholder="ex.: @gmail, comprovante, pedido">
            </label>
            <label>
                De
                <input type="date" name="de" value="<?php echo Helpers::e($de); ?>">
            </label>
            <label>
                Até
                <input type="date" name="ate" value="<?php echo Helpers::e($ate); ?>">
            </label>
            <div class="cta-group full">
                <button type="submit" class="button-link button-link--primary">Filtrar</button>
                <a class="button-link button-link--ghost" href="/admin/emails/fila">Limpar filtros</a>
            </div>
        </form>
    </section>

    <section class="status-card" style="margin-top:12px;">
        <form method="post" action="/admin/emails/reenviar-selecionados" id="emails-reenviar-selecionados-form">
            <?php echo $csrfField; ?>
            <input type="hidden" name="justificativa" id="emails-justificativa" value="">
            <div class="cta-group" style="justify-content:space-between;align-items:center;">
                <div>
                    <button type="submit" class="button-link" onclick="return confirmarReenvioSelecionados();">Reenviar selecionados</button>
                    <button type="submit" class="button-link button-link--ghost" formaction="/admin/emails/excluir-falhas-selecionadas" onclick="return confirmarExclusaoSelecionados();">Excluir falhas selecionadas</button>
                    <small class="muted" style="margin-left:10px;">Reenviáveis: pendente/falhou.</small>
                </div>
                <div class="muted">
                    Total: <?php echo (int) ($pagination['total'] ?? 0); ?>
                </div>
            </div>

            <div class="table-wrap" style="margin-top:12px;">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" onclick="toggleTodos(this)"></th>
                        <th>ID</th>
                        <th>Destinatário</th>
                        <th>Assunto</th>
                        <th>Evento</th>
                        <th>Status</th>
                        <th>Tentativas</th>
                        <th>Último erro</th>
                        <th>Criado em</th>
                        <th>Enviado/Falhou</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($emails)): ?>
                        <tr><td colspan="11">Nenhum e-mail encontrado.</td></tr>
                    <?php else: foreach ($emails as $email): ?>
                        <?php
                        $emailId = (int) ($email['id'] ?? 0);
                        $emailStatus = (string) ($email['status'] ?? '');
                        $reenviavel = in_array($emailStatus, array('pendente', 'falhou'), true);
                        $excluivel = $emailStatus === 'falhou';
                        $dataTentativa = !empty($email['enviado_em']) ? (string) $email['enviado_em'] : (!empty($email['falhou_em']) ? (string) $email['falhou_em'] : '');
                        $ultimoErro = !empty($email['ultimo_erro']) ? (string) $email['ultimo_erro'] : '';
                        if ($ultimoErro !== '') {
                            $ultimoErro = function_exists('mb_strimwidth')
                                ? mb_strimwidth($ultimoErro, 0, 80, '...')
                                : (strlen($ultimoErro) > 80 ? substr($ultimoErro, 0, 77) . '...' : $ultimoErro);
                        }
                        ?>
                        <tr>
                            <td>
                                <?php if ($reenviavel): ?>
                                    <input type="checkbox" name="ids[]" value="<?php echo $emailId; ?>">
                                <?php endif; ?>
                            </td>
                            <td><?php echo $emailId; ?></td>
                            <td><?php echo Helpers::e($email['destinatario_email'] ?? ''); ?></td>
                            <td><?php echo Helpers::e($email['assunto'] ?? ''); ?></td>
                            <td>
                                <small class="muted"><?php echo Helpers::e($email['evento'] ?? ''); ?></small><br>
                                <small class="muted"><?php echo Helpers::e($email['template'] ?? ''); ?></small>
                            </td>
                            <td><span class="pill"><?php echo Helpers::e($emailStatus); ?></span></td>
                            <td><?php echo (int) ($email['tentativas'] ?? 0); ?></td>
                            <td><?php echo Helpers::e($ultimoErro); ?></td>
                            <td><?php echo Helpers::e($email['created_at'] ?? ''); ?></td>
                            <td><?php echo Helpers::e($dataTentativa); ?></td>
                            <td>
                                <?php if ($reenviavel): ?>
                                    <form method="post" action="/admin/emails/reenviar" style="display:inline;">
                                        <?php echo $csrfField; ?>
                                        <input type="hidden" name="id" value="<?php echo $emailId; ?>">
                                        <button type="submit" class="button-link button-link--ghost" onclick="return confirmarAcaoCritica({ palavra: 'REENVIAR', pergunta: 'Você conferiu o reenvio deste e-mail?' });">Reenviar</button>
                                    </form>
                                    <?php if ($excluivel): ?>
                                        <button type="submit"
                                                class="button-link button-link--ghost"
                                                name="id"
                                                value="<?php echo $emailId; ?>"
                                                formaction="/admin/emails/excluir-falha"
                                                onclick="return confirmarExclusaoIndividual();">Excluir</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <?php if ($pages > 1): ?>
            <div class="cta-group" style="margin-top:12px;justify-content:flex-end;gap:8px;">
                <?php if ($page > 1): ?>
                    <a class="button-link button-link--ghost" href="/admin/emails/fila<?php echo $buildQuery(array('page' => $page - 1)); ?>">Anterior</a>
                <?php endif; ?>
                <span class="muted">Página <?php echo $page; ?> de <?php echo $pages; ?></span>
                <?php if ($page < $pages): ?>
                    <a class="button-link button-link--ghost" href="/admin/emails/fila<?php echo $buildQuery(array('page' => $page + 1)); ?>">Próxima</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

<script>
function toggleTodos(master) {
    var form = document.getElementById('emails-reenviar-selecionados-form');
    if (!form) return;
    var inputs = form.querySelectorAll('input[type=\"checkbox\"][name=\"ids[]\"]');
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].checked = master.checked;
    }
}

function confirmarReenvioSelecionados() {
    var form = document.getElementById('emails-reenviar-selecionados-form');
    if (!form) return false;
    var checked = form.querySelectorAll('input[type=\"checkbox\"][name=\"ids[]\"]:checked');
    if (!checked || checked.length === 0) {
        window.alert('Selecione ao menos um e-mail para reenviar.');
        return false;
    }
    return confirmarAcaoCritica({ palavra: 'REENVIAR', pergunta: 'Você conferiu o reenvio de ' + checked.length + ' e-mails selecionados?' });
}

function confirmarExclusaoIndividual() {
    var reason = window.prompt('Informe a justificativa para excluir este e-mail com falha da fila:');
    if (!reason) {
        window.alert('A justificativa é obrigatória.');
        return false;
    }
    var input = document.getElementById('emails-justificativa');
    if (input) input.value = reason;
    return confirmarAcaoCritica({ palavra: 'EXCLUIR', pergunta: 'Você conferiu a exclusão deste e-mail com falha?' });
}

function confirmarExclusaoSelecionados() {
    var form = document.getElementById('emails-reenviar-selecionados-form');
    if (!form) return false;
    var checked = form.querySelectorAll('input[type=\"checkbox\"][name=\"ids[]\"]:checked');
    if (!checked || checked.length === 0) {
        window.alert('Selecione ao menos um e-mail para excluir.');
        return false;
    }

    var reason = window.prompt('Informe a justificativa para excluir os e-mails com falha selecionados:');
    if (!reason) {
        window.alert('A justificativa é obrigatória.');
        return false;
    }

    var input = document.getElementById('emails-justificativa');
    if (input) input.value = reason;

    return confirmarAcaoCritica({ palavra: 'EXCLUIR', pergunta: 'Você conferiu a exclusão de ' + checked.length + ' e-mails selecionados?' });
}
</script>

<?php
$campanha = isset($campanha) && is_array($campanha) ? $campanha : array();
$beneficiarios = isset($beneficiarios) && is_array($beneficiarios) ? $beneficiarios : array();
$canManage = !empty($can_manage);
$canCancel = !empty($can_cancel);
?>
<div class="admin-page">
    <section class="admin-page__header admin-page__header--with-metrics">
        <div class="admin-page__content">
            <div>
                <h1 class="admin-page__title"><?php echo htmlspecialchars($campanha['titulo'] ?? 'Presente', ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="admin-page__subtitle"><?php echo htmlspecialchars($campanha['justificativa'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="admin-page__actions">
                <a class="button-link" href="/admin/promocionais/presentes">Voltar</a>
                <?php if ($canManage): ?>
                    <a class="button-link button-link--primary" href="/admin/promocionais/presentes/criar">Novo presente</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <div class="admin-grid admin-grid--metrics">
            <div class="admin-metric-card">
                <strong><?php echo htmlspecialchars($campanha['curso_nome'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Curso</span>
            </div>
            <div class="admin-metric-card">
                <strong><?php echo htmlspecialchars(!empty($campanha['turma_nome']) ? $campanha['turma_nome'] : 'Curso inteiro', ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Turma</span>
            </div>
            <div class="admin-metric-card">
                <strong><?php echo htmlspecialchars($campanha['acesso_tipo'] ?? 'sem_prazo', ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Prazo de acesso</span>
            </div>
            <div class="admin-metric-card">
                <strong><?php echo htmlspecialchars($campanha['status'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Status</span>
            </div>
        </div>
        <div class="admin-warning-box" style="margin-top: 16px;">
            Este pedido foi concedido como presente e não gera financeiro, rateio ou comissão.
        </div>
    </section>

    <section class="status-card">
        <div class="admin-page__content">
            <div>
                <strong>Mensagem de e-mail utilizada</strong>
                <p class="muted"><?php echo htmlspecialchars($campanha['email_assunto'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <div class="table-wrap" style="margin-top: 16px;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>E-mail</th>
                        <th>Perfil</th>
                        <th>Cidade</th>
                        <th>Pedido</th>
                        <th>Inscrição</th>
                        <th>Status</th>
                        <th>Concedido em</th>
                        <th>Expira em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($beneficiarios)): ?>
                        <tr><td colspan="10">Nenhum beneficiário encontrado.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($beneficiarios as $beneficiario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($beneficiario['usuario_nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($beneficiario['usuario_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($beneficiario['usuario_perfil'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($beneficiario['usuario_cidade'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <a href="/admin/pedidos/show?pedido_id=<?php echo (int) ($beneficiario['pedido_id'] ?? 0); ?>">#<?php echo htmlspecialchars($beneficiario['pedido_codigo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a><br>
                                <small class="muted">R$ <?php echo number_format((float) ($beneficiario['pedido_total'] ?? 0), 2, ',', '.'); ?></small>
                            </td>
                            <td>#<?php echo (int) ($beneficiario['inscricao_id'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars($beneficiario['status'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo !empty($beneficiario['criado_em']) ? date('d/m/Y H:i', strtotime($beneficiario['criado_em'])) : '-'; ?></td>
                            <td><?php echo !empty($beneficiario['acesso_expira_em']) ? date('d/m/Y H:i', strtotime($beneficiario['acesso_expira_em'])) : 'Sem prazo'; ?></td>
                            <td>
                                <?php if ($canCancel && ($beneficiario['status'] ?? '') === 'ativo'): ?>
                                    <button type="button" class="button-link button-link--danger" onclick="cancelarIndividual(<?php echo (int) $campanha['id']; ?>, <?php echo (int) $beneficiario['id']; ?>)">Cancelar</button>
                                <?php else: ?>
                                    <span class="muted">Sem ação</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($canCancel && !empty($beneficiarios)): ?>
        <section class="status-card" id="cancelar">
            <strong>Cancelamento em lote</strong>
            <p class="muted">Selecione os beneficiários, informe a justificativa e confirme a operação.</p>
            <form method="post" action="/admin/promocionais/presentes/cancelar-lote" id="presentes-cancelar-lote-form">
                <?php echo $csrfField; ?>
                <input type="hidden" name="campanha_id" value="<?php echo (int) $campanha['id']; ?>">
                <div class="admin-form-grid" style="margin-top: 16px;">
                    <label>
                        <span>Tipo de cancelamento</span>
                        <select name="cancelamento_tipo" required>
                            <option value="cancelar_inscricao">Cancelar apenas a inscrição</option>
                            <option value="cancelar_pedido_inscricao">Cancelar pedido e inscrição</option>
                            <option value="bloquear_acesso">Bloquear acesso ao curso</option>
                        </select>
                    </label>
                    <label class="admin-form-grid__full">
                        <span>Justificativa</span>
                        <textarea name="justificativa" rows="4" required></textarea>
                    </label>
                </div>

                <div class="table-wrap" style="margin-top: 16px;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selecionar_beneficiarios"></th>
                                <th>Beneficiário</th>
                                <th>Status</th>
                                <th>Pedido</th>
                                <th>Inscrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($beneficiarios as $beneficiario): ?>
                                <tr>
                                    <td>
                                        <?php if (($beneficiario['status'] ?? '') === 'ativo'): ?>
                                            <input type="checkbox" name="beneficiario_ids[]" value="<?php echo (int) $beneficiario['id']; ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($beneficiario['usuario_nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($beneficiario['status'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>#<?php echo htmlspecialchars($beneficiario['pedido_codigo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>#<?php echo (int) ($beneficiario['inscricao_id'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="admin-page__actions" style="margin-top: 16px;">
                    <button type="submit" class="button-link button-link--danger">Cancelar selecionados</button>
                </div>
            </form>
        </section>
    <?php endif; ?>
</div>

<form method="post" action="/admin/promocionais/presentes/cancelar" id="presentes-cancelar-individual-form" style="display:none;">
    <?php echo $csrfField; ?>
    <input type="hidden" name="campanha_id" id="presente-campanha-id" value="">
    <input type="hidden" name="beneficiario_id" id="presente-beneficiario-id" value="">
    <input type="hidden" name="cancelamento_tipo" id="presente-cancelamento-tipo" value="">
    <input type="hidden" name="justificativa" id="presente-justificativa" value="">
</form>

<script>
(function () {
    var selecionar = document.getElementById('selecionar_beneficiarios');
    var form = document.getElementById('presentes-cancelar-lote-form');
    if (selecionar && form) {
        selecionar.addEventListener('change', function () {
            var checks = form.querySelectorAll('input[name="beneficiario_ids[]"]');
            checks.forEach(function (checkbox) {
                checkbox.checked = selecionar.checked;
            });
        });
    }
})();

function cancelarIndividual(campanhaId, beneficiarioId) {
    var tipo = window.prompt('Informe o tipo de cancelamento:\n1 - cancelar_inscricao\n2 - cancelar_pedido_inscricao\n3 - bloquear_acesso', 'cancelar_inscricao');
    if (tipo === null) {
        return false;
    }

    tipo = tipo.trim();
    if (tipo === '1') {
        tipo = 'cancelar_inscricao';
    } else if (tipo === '2') {
        tipo = 'cancelar_pedido_inscricao';
    } else if (tipo === '3') {
        tipo = 'bloquear_acesso';
    }

    var justificativa = window.prompt('Informe a justificativa do cancelamento:');
    if (justificativa === null) {
        return false;
    }

    justificativa = justificativa.trim();
    if (justificativa === '') {
        window.alert('A justificativa é obrigatória.');
        return false;
    }

    document.getElementById('presente-campanha-id').value = String(campanhaId);
    document.getElementById('presente-beneficiario-id').value = String(beneficiarioId);
    document.getElementById('presente-cancelamento-tipo').value = tipo;
    document.getElementById('presente-justificativa').value = justificativa;
    document.getElementById('presentes-cancelar-individual-form').submit();
    return false;
}
</script>

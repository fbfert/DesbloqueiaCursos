<?php
$preview = isset($preview) && is_array($preview) ? $preview : array();
$totais = isset($preview['totais']) && is_array($preview['totais']) ? $preview['totais'] : array();
$aptos = isset($preview['beneficiarios_aptos']) && is_array($preview['beneficiarios_aptos']) ? $preview['beneficiarios_aptos'] : array();
$ignorados = isset($preview['ignorados']) && is_array($preview['ignorados']) ? $preview['ignorados'] : array();
$payload = isset($payload) && is_array($payload) ? $payload : array();
?>
<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Confirmar presente</h1>
            <p class="admin-page__subtitle">Revise os dados antes de criar pedidos aprovados de valor R$ 0,00.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link" href="/admin/promocionais/presentes/criar">Voltar</a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <div class="admin-grid admin-grid--metrics">
            <div class="admin-metric-card">
                <strong><?php echo htmlspecialchars($payload['titulo'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Título da campanha</span>
            </div>
            <div class="admin-metric-card">
                <strong><?php echo htmlspecialchars($payload['curso_nome'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Curso</span>
            </div>
            <div class="admin-metric-card">
                <strong><?php echo htmlspecialchars($payload['turma_nome'] ?? 'Curso inteiro', ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Turma</span>
            </div>
            <div class="admin-metric-card">
                <strong><?php echo htmlspecialchars($payload['acesso_tipo'] ?? 'sem_prazo', ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Prazo de acesso</span>
            </div>
        </div>

        <div class="admin-warning-box" style="margin-top: 16px;">
            Esta ação criará pedidos aprovados de valor R$ 0,00, sem cobrança, sem comprovante PIX e sem geração de financeiro, rateios ou comissões.
        </div>

        <div class="admin-page__content" style="margin-top: 16px;">
            <div class="admin-summary-list">
                <div><strong><?php echo (int) ($totais['selecionados'] ?? 0); ?></strong><span>selecionados</span></div>
                <div><strong><?php echo (int) ($totais['aptos'] ?? 0); ?></strong><span>aptos</span></div>
                <div><strong><?php echo (int) ($totais['ignorados'] ?? 0); ?></strong><span>ignorados</span></div>
            </div>
            <p class="muted"><?php echo htmlspecialchars($payload['justificativa'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="table-wrap" style="margin-top: 16px;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th colspan="5">Beneficiários aptos</th>
                    </tr>
                    <tr>
                        <th>ID</th>
                        <th>Usuário</th>
                        <th>E-mail</th>
                        <th>Cidade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($aptos)): ?>
                        <tr><td colspan="5">Nenhum usuário apto para o presente.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($aptos as $usuario): ?>
                        <tr>
                            <td><?php echo (int) $usuario['id']; ?></td>
                            <td><?php echo htmlspecialchars($usuario['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usuario['cidade'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usuario['status'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-wrap" style="margin-top: 16px;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th colspan="5">Usuários ignorados</th>
                    </tr>
                    <tr>
                        <th>ID</th>
                        <th>Usuário</th>
                        <th>E-mail</th>
                        <th>Cidade</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ignorados)): ?>
                        <tr><td colspan="5">Nenhum usuário ignorado.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($ignorados as $usuario): ?>
                        <tr>
                            <td><?php echo (int) $usuario['id']; ?></td>
                            <td><?php echo htmlspecialchars($usuario['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usuario['cidade'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usuario['motivo'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <form method="post" action="/admin/promocionais/presentes/store" style="margin-top: 16px;">
            <?php echo $csrfField; ?>
            <input type="hidden" name="preview_token" value="<?php echo htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="admin-page__actions">
                <a class="button-link" href="/admin/promocionais/presentes/criar">Editar seleção</a>
                <button type="submit" class="button-link button-link--primary">Confirmar concessão</button>
            </div>
        </form>
    </section>
</div>

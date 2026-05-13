<?php
$cuponsAtivos = isset($cupons) && is_array($cupons) ? $cupons : array();
$cuponsInativos = isset($cupons_inativos) && is_array($cupons_inativos) ? $cupons_inativos : array();
?>
<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Cupons</h1>
        <p class="admin-page__subtitle">Gestão administrativa de cupons, regras e usos.</p>
    </div>
    <div class="admin-page__actions">
        <a class="button-link button-link--primary" href="/admin/cupons/criar">Novo cupom</a>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Cupons ativos</strong>
    <div class="table-wrap">
        <table class="admin-table admin-table--cupons-lista">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Validade</th>
                    <th>Desconto</th>
                    <th>Status</th>
                    <th>Usos</th>
                    <th>Total descontado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cuponsAtivos)): ?>
                    <tr>
                        <td colspan="9">Nenhum cupom ativo cadastrado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($cuponsAtivos as $cupom): ?>
                    <?php
                    $escopoCupom = isset($cupom['escopo']) && $cupom['escopo'] === 'cursos_especificos'
                        ? 'Cursos específicos (' . (int) ($cupom['total_cursos'] ?? 0) . ')'
                        : 'Todo o site';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cupom['codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($cupom['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($cupom['tipo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($escopoCupom, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php echo htmlspecialchars($cupom['desconto_tipo'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php echo ' - ' . number_format((float) $cupom['valor_desconto'], 2, ',', '.'); ?>
                        </td>
                        <td><?php echo htmlspecialchars($cupom['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $cupom['total_usos']; ?></td>
                        <td>R$ <?php echo number_format((float) $cupom['total_descontos'], 2, ',', '.'); ?></td>
                        <td>
                            <a href="/admin/cupons/resumo?cupom_id=<?php echo (int) $cupom['id']; ?>">Resumo</a>
                            <span aria-hidden="true"> | </span>
                            <a href="/admin/cupons/editar?cupom_id=<?php echo (int) $cupom['id']; ?>">Editar</a>
                            <span aria-hidden="true"> | </span>
                            <form method="post" action="/admin/cupons/status" style="display:inline-block;">
                                <?php echo $csrfField; ?>
                                <input type="hidden" name="id" value="<?php echo (int) $cupom['id']; ?>">
                                <input type="hidden" name="status" value="inativo">
                                <button type="submit" class="button-link">Inativar</button>
                            </form>
                            <span aria-hidden="true"> | </span>
                            <a href="#" onclick="return excluirCupom(<?php echo (int) $cupom['id']; ?>);">Lixeira</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<details class="status-card" style="margin-top:16px;">
    <summary style="cursor:pointer; font-weight:700;">Cupons inativos (<?php echo count($cuponsInativos); ?>)</summary>
    <div style="margin-top:16px;">
        <div class="table-wrap">
            <table class="admin-table admin-table--cupons-lista">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Validade</th>
                        <th>Desconto</th>
                        <th>Status</th>
                        <th>Usos</th>
                        <th>Total descontado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cuponsInativos)): ?>
                        <tr>
                            <td colspan="9">Nenhum cupom inativo.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($cuponsInativos as $cupom): ?>
                        <?php
                        $escopoCupom = isset($cupom['escopo']) && $cupom['escopo'] === 'cursos_especificos'
                            ? 'Cursos específicos (' . (int) ($cupom['total_cursos'] ?? 0) . ')'
                            : 'Todo o site';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cupom['codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($cupom['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($cupom['tipo'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($escopoCupom, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo htmlspecialchars($cupom['desconto_tipo'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php echo ' - ' . number_format((float) $cupom['valor_desconto'], 2, ',', '.'); ?>
                            </td>
                            <td><?php echo htmlspecialchars($cupom['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $cupom['total_usos']; ?></td>
                            <td>R$ <?php echo number_format((float) $cupom['total_descontos'], 2, ',', '.'); ?></td>
                            <td>
                                <a href="/admin/cupons/resumo?cupom_id=<?php echo (int) $cupom['id']; ?>">Resumo</a>
                                <span aria-hidden="true"> | </span>
                                <a href="/admin/cupons/editar?cupom_id=<?php echo (int) $cupom['id']; ?>">Editar</a>
                                <span aria-hidden="true"> | </span>
                                <form method="post" action="/admin/cupons/status" style="display:inline-block;">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $cupom['id']; ?>">
                                    <input type="hidden" name="status" value="ativo">
                                    <button type="submit" class="button-link">Reativar</button>
                                </form>
                                <span aria-hidden="true"> | </span>
                                <a href="#" onclick="return excluirCupom(<?php echo (int) $cupom['id']; ?>);">Lixeira</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</details>
</div>

<form method="post" action="/admin/cupons/excluir" id="form-excluir-cupom" style="display:none;">
    <input type="hidden" name="id" id="excluir-cupom-id" value="">
    <input type="hidden" name="justificativa" id="excluir-cupom-justificativa" value="">
</form>

<script>
function excluirCupom(cupomId) {
    var justificativa = window.prompt('Informe a justificativa para enviar o cupom à lixeira:');
    if (justificativa === null) {
        return false;
    }
    justificativa = justificativa.trim();
    if (justificativa === '') {
        window.alert('A justificativa é obrigatória.');
        return false;
    }

    document.getElementById('excluir-cupom-id').value = String(cupomId);
    document.getElementById('excluir-cupom-justificativa').value = justificativa;
    document.getElementById('form-excluir-cupom').submit();
    return false;
}
</script>

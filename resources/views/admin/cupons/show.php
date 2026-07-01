<?php
$cupomCursos = isset($cupom_cursos) ? $cupom_cursos : array();
$escopoCupom = isset($cupom['escopo']) && $cupom['escopo'] === 'cursos_especificos'
    ? 'Cursos específicos (' . count($cupomCursos) . ')'
    : 'Todo o site';
$formatarTelefonePagador = function ($telefone) {
    $telefone = preg_replace('/\D+/', '', (string) $telefone);

    if ($telefone === '') {
        return '';
    }

    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
    }

    if (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }

    return $telefone;
};
?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo htmlspecialchars((string) $cupom['nome'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="admin-page__subtitle"><?php echo htmlspecialchars((string) $cupom['codigo'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $cupom['status'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Resumo</strong>
    <p>
        <strong>Tipo:</strong> <?php echo htmlspecialchars((string) $cupom['tipo'], ENT_QUOTES, 'UTF-8'); ?><br>
        <strong>Validade:</strong> <?php echo htmlspecialchars($escopoCupom, ENT_QUOTES, 'UTF-8'); ?><br>
        <strong>Desconto:</strong> <?php echo htmlspecialchars((string) $cupom['desconto_tipo'], ENT_QUOTES, 'UTF-8'); ?> - R$ <?php echo number_format((float) $cupom['valor_desconto'], 2, ',', '.'); ?><br>
        <strong>Usos:</strong> <?php echo (int) $resumo['total_usos']; ?><br>
        <strong>Total descontado:</strong> R$ <?php echo number_format((float) $resumo['total_descontos'], 2, ',', '.'); ?><br>
        <strong>Link promocional:</strong> <?php echo htmlspecialchars((string) $cupom['link_promocional'], ENT_QUOTES, 'UTF-8'); ?>
    </p>
    <p>
        <a href="/admin/cupons/editar?cupom_id=<?php echo (int) $cupom['id']; ?>">Editar cupom</a>
    </p>
</section>

<section class="status-card">
    <strong>Restrições</strong>
    <ul>
        <?php if (!empty($cupomCursos)): ?>
            <li><strong>Cursos vinculados</strong></li>
            <?php foreach ($cupomCursos as $cupomCurso): ?>
                <li>
                    <?php echo htmlspecialchars((string) $cupomCurso['curso_nome'], ENT_QUOTES, 'UTF-8'); ?>
                    <small>(#<?php echo (int) $cupomCurso['curso_id']; ?> | <?php echo htmlspecialchars((string) $cupomCurso['curso_status'], ENT_QUOTES, 'UTF-8'); ?>)</small>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php foreach ($relacoes as $relacao): ?>
            <li><?php echo htmlspecialchars($relacao['tipo_relacao'] . ': ' . $relacao['valor_relacao'], ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
        <?php if (empty($relacoes) && empty($cupomCursos)): ?>
            <li>Nenhuma restrição cadastrada.</li>
        <?php endif; ?>
    </ul>
</section>

<section class="status-card">
    <strong>Usos recentes</strong>
    <div class="table-wrap">
        <table class="admin-table admin-table--cupons-usos">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Pagador</th>
                    <th>Valor descontado</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usos as $uso): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $uso['pedido_codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php echo htmlspecialchars((string) $uso['pagador_nome'], ENT_QUOTES, 'UTF-8'); ?><br>
                            <small><?php echo htmlspecialchars((string) $uso['pagador_email'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php if (!empty($uso['pagador_telefone'])): ?>
                                <br><small><?php echo htmlspecialchars($formatarTelefonePagador((string) $uso['pagador_telefone']), ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>R$ <?php echo number_format((float) $uso['valor_desconto'], 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars((string) $uso['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($usos)): ?>
                    <tr>
                        <td colspan="4">Ainda sem usos registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="status-card">
    <strong>Histórico</strong>
    <div class="table-wrap">
        <table class="admin-table admin-table--cupons-historico">
            <thead>
                <tr>
                    <th>Ação</th>
                    <th>Observação</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historico as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $item['acao'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $item['observacao'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $item['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($historico)): ?>
                    <tr>
                        <td colspan="3">Sem histórico.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>


<section class="hero">
    <h1><?php echo htmlspecialchars((string) $cupom['nome'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><?php echo htmlspecialchars((string) $cupom['codigo'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $cupom['status'], ENT_QUOTES, 'UTF-8'); ?></p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Resumo</strong>
    <p>
        <strong>Tipo:</strong> <?php echo htmlspecialchars((string) $cupom['tipo'], ENT_QUOTES, 'UTF-8'); ?><br>
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
    <strong>Restricoes</strong>
    <ul>
        <?php foreach ($relacoes as $relacao): ?>
            <li><?php echo htmlspecialchars($relacao['tipo_relacao'] . ': ' . $relacao['valor_relacao'], ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
        <?php if (empty($relacoes)): ?>
            <li>Nenhuma restricao cadastrada.</li>
        <?php endif; ?>
    </ul>
</section>

<section class="status-card">
    <strong>Usos recentes</strong>
    <div class="table-wrap">
        <table class="admin-table">
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
    <strong>Historico</strong>
    <div class="table-wrap">
        <table class="admin-table">
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
                        <td colspan="3">Sem historico.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>


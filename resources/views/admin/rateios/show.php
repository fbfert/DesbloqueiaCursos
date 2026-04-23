<?php use App\Core\Helpers; ?>

<section class="hero">
    <h1>Rateio <?php echo Helpers::e($rateio['competencia']); ?></h1>
    <p>Detalhamento da apuracao e dos participantes vinculados.</p>
</section>

<section class="status-card">
    <dl class="summary-list">
        <dt>Curso</dt><dd><?php echo Helpers::e($rateio['curso_nome']); ?></dd>
        <dt>Turma</dt><dd><?php echo Helpers::e($rateio['turma_nome'] ?? ''); ?></dd>
        <dt>Base bruta</dt><dd>R$ <?php echo number_format((float) $rateio['base_bruta'], 2, ',', '.'); ?></dd>
        <dt>Desconto cupons</dt><dd>R$ <?php echo number_format((float) $rateio['desconto_cupons'], 2, ',', '.'); ?></dd>
        <dt>Base liquida</dt><dd>R$ <?php echo number_format((float) $rateio['base_liquida'], 2, ',', '.'); ?></dd>
        <dt>Percentual</dt><dd><?php echo number_format((float) $rateio['percentual_total'], 2, ',', '.'); ?>%</dd>
        <dt>Valor rateado</dt><dd>R$ <?php echo number_format((float) $rateio['valor_rateio_total'], 2, ',', '.'); ?></dd>
        <dt>Status</dt><dd><?php echo Helpers::e($rateio['status']); ?></dd>
    </dl>
</section>

<section class="status-card">
    <div class="split-actions">
        <a href="/admin/rateios">Voltar</a>
        <a href="/admin/financeiro/repasses?apuracao_id=<?php echo (int) $rateio['apuracao_id']; ?>">Ver repasses</a>
    </div>
</section>

<section class="status-card">
    <strong>Participantes do rateio</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Professor</th>
                    <th>Tipo fiscal</th>
                    <th>Percentual</th>
                    <th>Base</th>
                    <th>Valor rateado</th>
                    <th>Liquido</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($participantes)): ?>
                    <tr><td colspan="7">Nenhum participante encontrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($participantes as $participante): ?>
                    <tr>
                        <td><?php echo Helpers::e($participante['usuario_nome']); ?></td>
                        <td><?php echo Helpers::e($participante['tipo_fiscal']); ?></td>
                        <td><?php echo number_format((float) $participante['percentual'], 2, ',', '.'); ?>%</td>
                        <td>R$ <?php echo number_format((float) $participante['valor_base'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $participante['valor_rateado'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $participante['valor_liquido'], 2, ',', '.'); ?></td>
                        <td><?php echo Helpers::e($participante['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

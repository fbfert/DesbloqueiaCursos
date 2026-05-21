<?php use App\Core\Helpers; ?>

<section class="hero">
    <h1>Meu dashboard</h1>
    <p>Visao restrita aos seus cursos, turmas, repasses e espelhos.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Período</strong>
    <form method="get" action="/professor/dashboard" class="form-grid">
        <label>
            Período
            <select name="periodo">
                <option value="hoje" <?php echo ($filters['periodo'] ?? '') === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                <option value="semana" <?php echo ($filters['periodo'] ?? '') === 'semana' ? 'selected' : ''; ?>>Semana</option>
                <option value="mes" <?php echo ($filters['periodo'] ?? '') === 'mes' ? 'selected' : ''; ?>>Mês</option>
                <option value="custom" <?php echo ($filters['periodo'] ?? '') === 'custom' ? 'selected' : ''; ?>>Personalizado</option>
            </select>
        </label>
        <label>
            Data inicial
            <input type="date" name="data_inicio" value="<?php echo Helpers::e($filters['data_inicio'] ?? ''); ?>">
        </label>
        <label>
            Data final
            <input type="date" name="data_fim" value="<?php echo Helpers::e($filters['data_fim'] ?? ''); ?>">
        </label>
        <div class="full">
            <button type="submit">Aplicar</button>
        </div>
    </form>
</section>

<section class="status-grid">
    <?php foreach (($cards ?? array()) as $card): ?>
        <article class="status-card">
            <strong><?php echo Helpers::e($card['label']); ?></strong>
            <p class="dashboard-kpi__value"><?php echo Helpers::e($card['value']); ?></p>
            <p class="dashboard-kpi__sub"><?php echo Helpers::e($card['subvalue']); ?></p>
        </article>
    <?php endforeach; ?>
    <article class="status-card">
        <strong>Avaliações textuais aguardando correção</strong>
        <p class="dashboard-kpi__value"><?php echo (int) ($conteudo_avaliacoes_pendentes ?? 0); ?></p>
        <p class="dashboard-kpi__sub"><a href="/professor/area-curso/conteudo/avaliacoes/pendentes">Ver pendências</a></p>
    </article>
</section>

<section class="grid-2">
    <article class="status-card">
        <strong>Meus cursos</strong>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th>Slug</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cursos)): ?>
                        <tr><td colspan="3">Nenhum curso atribuido.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($cursos as $curso): ?>
                        <tr>
                            <td><?php echo Helpers::e($curso['nome']); ?></td>
                            <td><?php echo Helpers::e($curso['slug']); ?></td>
                            <td><?php echo Helpers::e($curso['tipo']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="status-card">
        <strong>Minhas turmas</strong>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Turma</th>
                        <th>Curso</th>
                        <th>Codigo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($turmas)): ?>
                        <tr><td colspan="3">Nenhuma turma atribuido.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($turmas as $turma): ?>
                        <tr>
                            <td><?php echo Helpers::e($turma['nome']); ?></td>
                            <td><?php echo Helpers::e($turma['curso_nome']); ?></td>
                            <td><?php echo Helpers::e($turma['codigo']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="status-card">
    <strong>Repasses</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Competência</th>
                    <th>Bruto</th>
                    <th>Retido</th>
                    <th>Líquido</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($repasses)): ?>
                    <tr><td colspan="5">Nenhum repasse encontrado no periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($repasses as $repasse): ?>
                    <tr>
                        <td><?php echo Helpers::e($repasse['competencia']); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['valor_bruto'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['valor_retenido'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $repasse['valor_liquido'], 2, ',', '.'); ?></td>
                        <td><?php echo Helpers::e($repasse['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="status-card">
    <strong>Espelhos de RPA</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Competência</th>
                    <th>Nome</th>
                    <th>Bruto</th>
                    <th>Líquido</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($espelhos)): ?>
                    <tr><td colspan="5">Nenhum espelho encontrado no periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($espelhos as $espelho): ?>
                    <tr>
                        <td><?php echo Helpers::e($espelho['competencia']); ?></td>
                        <td><?php echo Helpers::e($espelho['nome']); ?></td>
                        <td>R$ <?php echo number_format((float) $espelho['valor_bruto'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format((float) $espelho['valor_liquido'], 2, ',', '.'); ?></td>
                        <td><?php echo Helpers::e($espelho['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>


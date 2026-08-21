<?php use App\Core\Helpers; ?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo Helpers::e($curso['nome']); ?></h1>
        <p class="admin-page__subtitle">
            <?php echo (int) $curso['carga_horaria']; ?> horas ·
            situação <strong><?php echo Helpers::e($curso['status']); ?></strong>
            <?php if ($errosAbertos > 0): ?>
                · <strong><?php echo (int) $errosAbertos; ?> erro(s) em aberto</strong>
            <?php endif; ?>
        </p>
    </div>
    <div class="admin-page__actions">
        <a class="button-link button-link--ghost" href="/revisor">Voltar</a>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<?php if (empty($modulos)): ?>
    <section class="status-card">
        <strong>Este curso ainda não tem módulos.</strong>
    </section>
<?php endif; ?>

<?php foreach ($modulos as $modulo): ?>
    <section class="status-card">
        <strong><?php echo Helpers::e($modulo['titulo']); ?></strong>
        <?php if (empty($modulo['itens'])): ?>
            <p class="muted">Módulo sem conteúdos.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Conteúdo</th>
                            <th>Tipo</th>
                            <th>Situação</th>
                            <th>Tamanho</th>
                            <th>Apontamentos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modulo['itens'] as $item): ?>
                            <?php
                            $itemId = (int) $item['id'];
                            $contagem = isset($comentariosPorItem[$itemId]) ? $comentariosPorItem[$itemId] : null;
                            $ehQuiz = $item['tipo'] === 'quiz';
                            $produzido = $ehQuiz
                                ? ((int) $item['total_questoes'] > 0)
                                : ((int) $item['bytes_html'] > 30000);
                            ?>
                            <tr>
                                <td><?php echo Helpers::e($item['titulo']); ?></td>
                                <td><span class="badge"><?php echo Helpers::e($item['tipo']); ?></span></td>
                                <td>
                                    <?php if ($produzido): ?>
                                        <span class="badge">pronto</span>
                                    <?php else: ?>
                                        <span class="muted">não produzido</span>
                                    <?php endif; ?>
                                </td>
                                <td class="muted">
                                    <?php if ($ehQuiz): ?>
                                        <?php echo (int) $item['total_questoes']; ?> questões
                                    <?php elseif ((int) $item['bytes_html'] > 0): ?>
                                        <?php echo number_format((int) $item['bytes_html'] / 1024, 0, ',', '.'); ?> KB
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($contagem): ?>
                                        <?php echo (int) $contagem['total']; ?>
                                        <?php if ((int) $contagem['abertos'] > 0): ?>
                                            <span class="muted">(<?php echo (int) $contagem['abertos']; ?> em aberto)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$produzido): ?>
                                        <span class="muted">aguardando produção</span>
                                    <?php elseif ($ehQuiz): ?>
                                        <a class="button-link" href="/revisor/questoes?item_id=<?php echo $itemId; ?>">Revisar questões</a>
                                    <?php else: ?>
                                        <a class="button-link" href="/revisor/conteudo?item_id=<?php echo $itemId; ?>">Revisar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
</div>

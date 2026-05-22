<?php use App\Core\Helpers; ?>
<section class="page-header">
    <h1>Avaliações textuais pendentes</h1>
    <p>Entregas aguardando correção.</p>
</section>
<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>
<section class="status-card">
    <div class="table-wrap">
        <table class="admin-table">
            <thead><tr><th>Entrega</th><th>Curso</th><th>Turma</th><th>Aluno</th><th>Avaliação</th><th>Tentativa</th><th>Enviado em</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
            <?php if (empty($entregas)): ?><tr><td colspan="9">Sem pendências no momento.</td></tr><?php endif; ?>
            <?php foreach ($entregas as $entrega): ?>
                <tr>
                    <td>#<?php echo (int) $entrega['id']; ?></td>
                    <td><?php echo Helpers::e((string) ($entrega['curso_nome'] ?? $entrega['curso_evento_id'] ?? '')); ?></td>
                    <td><?php echo Helpers::e((string) ($entrega['turma_nome'] ?? '-')); ?></td>
                    <td><?php echo Helpers::e((string) ($entrega['aluno_nome'] ?? $entrega['aluno_id'] ?? '')); ?></td>
                    <td><?php echo Helpers::e((string) ($entrega['item_titulo'] ?? '-')); ?></td>
                    <td><?php echo (int) ($entrega['tentativa'] ?? 0); ?></td>
                    <td><?php echo Helpers::e((string) ($entrega['enviado_em'] ?? '-')); ?></td>
                    <td><?php echo Helpers::e((string) ($entrega['status'] ?? '')); ?></td>
                    <td><a class="button-link" href="/professor/area-curso/conteudo/avaliacao/corrigir?id=<?php echo (int) $entrega['id']; ?>">Corrigir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

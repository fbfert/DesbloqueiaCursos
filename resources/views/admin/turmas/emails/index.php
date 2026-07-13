<?php use App\Core\Helpers; ?>
<?php use App\Core\Session; ?>
<?php use App\Services\RbacService; ?>

<?php
$canManage = (new RbacService())->userHasPermission(Session::get('usuario_id'), 'conteudo.gerenciar');
$turma = isset($turma) && is_array($turma) ? $turma : array();
$comunicados = isset($comunicados) && is_array($comunicados) ? $comunicados : array();
$totalDestinatarios = isset($total_destinatarios) ? (int) $total_destinatarios : 0;
$turmaId = isset($turma['id']) ? (int) $turma['id'] : 0;

$formatarDataHora = function ($valor) {
    $valor = trim((string) $valor);
    if ($valor === '' || $valor === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime($valor);

    return $timestamp ? date('d/m/Y H:i', $timestamp) : '-';
};
?>

<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">E-mails da turma</h1>
            <p class="admin-page__subtitle">
                <?php echo Helpers::e($turma['nome']); ?>
                <?php if (!empty($turma['codigo'])): ?>
                    (<?php echo Helpers::e($turma['codigo']); ?>)
                <?php endif; ?>
                &middot; <?php echo Helpers::e($turma['curso_nome']); ?>
                &middot; <?php echo $totalDestinatarios; ?> aluno(s) matriculado(s) com e-mail válido
            </p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/turmas">Voltar</a>
            <?php if ($canManage): ?>
                <a class="button-link" href="/admin/turmas/emails/novo?turma_id=<?php echo $turmaId; ?>">Enviar novo e-mail</a>
            <?php endif; ?>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Assunto</th>
                        <th>Enviado em</th>
                        <th>Por</th>
                        <th>Destinatários</th>
                        <th>Enviados</th>
                        <th>Falhas</th>
                        <th>Pendentes</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comunicados)): ?>
                        <tr>
                            <td colspan="8">Nenhum e-mail enviado para esta turma até agora.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($comunicados as $comunicado): ?>
                            <tr>
                                <td><?php echo Helpers::e($comunicado['assunto']); ?></td>
                                <td><?php echo Helpers::e($formatarDataHora($comunicado['created_at'])); ?></td>
                                <td><?php echo Helpers::e($comunicado['criado_por_nome'] ?? '-'); ?></td>
                                <td><?php echo (int) $comunicado['total_destinatarios']; ?></td>
                                <td><?php echo (int) $comunicado['enviados']; ?></td>
                                <td><?php echo (int) $comunicado['falhas']; ?></td>
                                <td><?php echo (int) $comunicado['pendentes']; ?></td>
                                <td>
                                    <div class="split-actions">
                                        <a href="/admin/turmas/emails/detalhe?lote_id=<?php echo (int) $comunicado['id']; ?>">Ver</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

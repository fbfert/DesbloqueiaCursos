<?php use App\Core\Helpers; ?>

<?php
$lote = isset($lote) && is_array($lote) ? $lote : array();
$resumo = isset($resumo) && is_array($resumo) ? $resumo : array();
$destinatarios = isset($destinatarios) && is_array($destinatarios) ? $destinatarios : array();

$loteId = isset($lote['id']) ? (int) $lote['id'] : 0;
$turmaId = isset($lote['turma_id']) ? (int) $lote['turma_id'] : 0;
$total = isset($resumo['total']) ? (int) $resumo['total'] : 0;
$enviados = isset($resumo['enviados']) ? (int) $resumo['enviados'] : 0;
$falhas = isset($resumo['falhas']) ? (int) $resumo['falhas'] : 0;
$pendentes = isset($resumo['pendentes']) ? (int) $resumo['pendentes'] : 0;

$statusLabels = array(
    'pendente' => 'Pendente',
    'enviado' => 'Enviado',
    'falhou' => 'Falhou',
);

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
            <h1 class="admin-page__title"><?php echo Helpers::e($lote['assunto']); ?></h1>
            <p class="admin-page__subtitle">
                <?php echo Helpers::e($lote['turma_nome']); ?>
                <?php if (!empty($lote['turma_codigo'])): ?>
                    (<?php echo Helpers::e($lote['turma_codigo']); ?>)
                <?php endif; ?>
                &middot; criado em <?php echo Helpers::e($formatarDataHora($lote['created_at'])); ?>
                <?php if (!empty($lote['criado_por_nome'])): ?>
                    por <?php echo Helpers::e($lote['criado_por_nome']); ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/turmas/emails?turma_id=<?php echo $turmaId; ?>">Voltar</a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card">
        <h2 class="admin-section__title">Progresso do envio</h2>

        <div class="turma-email-progresso"
             data-turma-email-progresso
             data-lote-id="<?php echo $loteId; ?>"
             data-token="<?php echo Helpers::e($csrfToken); ?>"
             data-total="<?php echo $total; ?>"
             data-enviados="<?php echo $enviados; ?>"
             data-falhas="<?php echo $falhas; ?>"
             data-pendentes="<?php echo $pendentes; ?>">

            <div class="turma-email-progresso__barra" role="progressbar"
                 aria-valuemin="0" aria-valuemax="<?php echo $total; ?>"
                 aria-valuenow="<?php echo $enviados + $falhas; ?>">
                <div class="turma-email-progresso__preenchimento"
                     data-progresso-barra
                     style="width: <?php echo $total > 0 ? (int) round((($enviados + $falhas) / $total) * 100) : 0; ?>%"></div>
            </div>

            <p class="turma-email-progresso__texto" data-progresso-texto>
                <?php if ($pendentes > 0): ?>
                    Preparando envio de <?php echo $pendentes; ?> e-mail(s)...
                <?php else: ?>
                    Envio concluído: <?php echo $enviados; ?> enviado(s), <?php echo $falhas; ?> falha(s), de <?php echo $total; ?> destinatário(s).
                <?php endif; ?>
            </p>

            <p class="muted" data-progresso-aviso <?php echo $pendentes > 0 ? '' : 'hidden'; ?>>
                Mantenha esta página aberta até o envio terminar.
            </p>
        </div>
    </section>

    <section class="status-card">
        <details>
            <summary>
                <h2 class="admin-section__title">Conteúdo enviado</h2>
            </summary>
            <div class="email-preview">
                <?php echo Helpers::renderSafeHtml($lote['corpo_html'], 'full'); ?>
            </div>
        </details>
    </section>

    <section class="status-card">
        <h2 class="admin-section__title">Destinatários (<?php echo count($destinatarios); ?>)</h2>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Aluno</th>
                        <th>E-mail</th>
                        <th>Status</th>
                        <th>Enviado em</th>
                        <th>Erro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($destinatarios)): ?>
                        <tr><td colspan="5">Nenhum destinatário registrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($destinatarios as $destinatario): ?>
                            <?php $status = (string) $destinatario['status']; ?>
                            <tr>
                                <td><?php echo Helpers::e($destinatario['destinatario_nome'] ?? '-'); ?></td>
                                <td><?php echo Helpers::e($destinatario['destinatario_email']); ?></td>
                                <td><?php echo Helpers::e(isset($statusLabels[$status]) ? $statusLabels[$status] : $status); ?></td>
                                <td><?php echo Helpers::e($formatarDataHora($destinatario['enviado_em'])); ?></td>
                                <td><?php echo Helpers::e($destinatario['ultimo_erro'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($falhas > 0): ?>
            <p class="muted">
                E-mails que falharam podem ser reenviados pela
                <a href="/admin/emails/fila">fila de e-mails</a>.
            </p>
        <?php endif; ?>
    </section>
</div>

<script src="/assets/js/turma-email-envio.js?v=20260713" defer></script>
